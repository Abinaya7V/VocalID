<?php
// ============================================================
// mark_attendance.php — Attendance Marking API Endpoint
// Called via fetch() from voice.php (and optionally class.php)
// Accepts POST JSON body, saves attendance record to database
// Returns JSON response
// ============================================================

// Always start session (to optionally verify student is logged in)
session_start();

// Set response type to JSON — important for fetch() to parse it correctly
header('Content-Type: application/json');

// Include database connection
require_once 'db.php';

// ============================================================
// Only accept POST requests — reject everything else
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ============================================================
// Read and parse the JSON body sent from JavaScript fetch()
// file_get_contents('php://input') reads the raw POST body
// ============================================================
$raw_body = file_get_contents('php://input');
$data     = json_decode($raw_body, true);   // Decode JSON to PHP array

// ============================================================
// Also support traditional form POST (from class.php manual form)
// ============================================================
if (empty($data)) {
    $data = $_POST;
}

// ============================================================
// Validate required fields
// ============================================================
$student_name = trim($data['student_name'] ?? '');
$roll_no      = strtoupper(trim($data['roll_no'] ?? ''));
$confidence   = intval($data['confidence'] ?? 0);
$class_id     = trim($data['class_id'] ?? '');      // Optional: can be passed from class.php

if (empty($student_name) || empty($roll_no)) {
    echo json_encode(['success' => false, 'message' => 'Student name and roll number are required.']);
    exit;
}

// ============================================================
// Determine the class_id if not provided
// Look it up from the students table using the roll number
// ============================================================
if (empty($class_id)) {
    $stmt = $conn->prepare("SELECT class_id FROM students WHERE roll_no = ?");
    $stmt->bind_param("s", $roll_no);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $class_id = $res['class_id'] ?? 'class1';   // Default to class1 if not found
}

// ============================================================
// Get today's date and current time
// These will be stored in the attendance record
// ============================================================
$today = date('Y-m-d');   // Format: 2024-11-25
$now   = date('H:i:s');   // Format: 14:35:20

// ============================================================
// Check if attendance is already marked for this student today
// in this class — prevent duplicate records
// ============================================================
$stmt = $conn->prepare(
    "SELECT id FROM attendance WHERE roll_no = ? AND class_id = ? AND date = ?"
);
$stmt->bind_param("sss", $roll_no, $class_id, $today);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    // Already marked — return success but with informative message
    echo json_encode([
        'success' => true,
        'message' => "Attendance already marked for $student_name today.",
        'already_marked' => true
    ]);
    exit;
}

// ============================================================
// Insert attendance record into database
// ============================================================
$status = 'present';   // Student is present (they completed voice enrollment)

$stmt = $conn->prepare(
    "INSERT INTO attendance (student_name, roll_no, class_id, date, time, status, confidence)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param(
    "ssssssi",       // s=string, i=integer
    $student_name,
    $roll_no,
    $class_id,
    $today,
    $now,
    $status,
    $confidence
);

if ($stmt->execute()) {
    // ✅ Success — attendance saved
    $stmt->close();

    // Also update student's enrolled flag if not already set
    $conn->query("UPDATE students SET enrolled = 1 WHERE roll_no = '$roll_no'");

    echo json_encode([
        'success'    => true,
        'message'    => "Attendance marked for $student_name at $now.",
        'student'    => $student_name,
        'roll_no'    => $roll_no,
        'class_id'   => $class_id,
        'date'       => $today,
        'time'       => $now,
        'confidence' => $confidence
    ]);
} else {
    // ❌ Database error
    $stmt->close();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save attendance. Database error: ' . $conn->error
    ]);
}

$conn->close();
?>

<?php
// ============================================================
// reset_attendance.php — Reset Today's Attendance (API)
// Called from class.php via fetch() POST
// Only accessible to logged-in faculty
// ============================================================
session_start();
header('Content-Type: application/json');

// Guard: Must be logged in as faculty
if (!isset($_SESSION['faculty_logged_in']) || $_SESSION['faculty_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

require_once 'db.php';

// Read JSON body
$data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$class_id = trim($data['class_id'] ?? '');

// Validate class_id
if (!in_array($class_id, ['class1', 'class2'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid class ID']);
    exit;
}

$today = date('Y-m-d');

// Delete today's attendance for this class
$stmt = $conn->prepare("DELETE FROM attendance WHERE class_id = ? AND date = ?");
$stmt->bind_param("ss", $class_id, $today);

if ($stmt->execute()) {
    $deleted = $stmt->affected_rows;
    $stmt->close();
    echo json_encode([
        'success' => true,
        'message' => "Reset complete. $deleted records deleted.",
        'deleted' => $deleted
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>

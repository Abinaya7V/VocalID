<?php
// ============================================================
// student-login.php — Student Registration & Login Page
// Students enter their details → saved to DB → redirected to voice.php
// ============================================================
session_start();
require_once 'db.php';

$error_msg   = '';
$success_msg = '';

// ============================================================
// Handle POST request (form submission)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Step 1: Sanitize inputs
    $name  = trim($_POST['student_name']  ?? '');
    $email = trim($_POST['student_email'] ?? '');
    $roll  = strtoupper(trim($_POST['student_roll'] ?? ''));
    $dept  = trim($_POST['student_dept']  ?? '');

    // Step 2: Server-side validation (never trust only client-side validation)
    if (empty($name) || empty($email) || empty($roll) || empty($dept)) {
        $error_msg = 'All fields are required.';
    } elseif (!preg_match('/^[A-Za-z ]{3,}$/', $name)) {
        $error_msg = 'Name must contain only letters and be at least 3 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please enter a valid email address.';
    } else {
        // Step 3: Check if student already exists by roll number
        $stmt = $conn->prepare("SELECT id, name, roll_no, enrolled FROM students WHERE roll_no = ? OR email = ?");
        $stmt->bind_param("ss", $roll, $email);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            // Student already registered — save to session and redirect to voice enrollment
            $_SESSION['student_name']     = $existing['name'];
            $_SESSION['student_roll']     = $existing['roll_no'];
            $_SESSION['student_email']    = $email;
            $_SESSION['student_dept']     = $dept;
            $_SESSION['student_db_id']    = $existing['id'];
            $_SESSION['student_enrolled'] = (bool)$existing['enrolled'];
            header('Location: voice.php');
            exit;
        } else {
            // Step 4: Insert new student into database
            // Assign class based on department (CS → class1, IT → class2, others → class1 by default)
            $class_id = ($dept === 'IT') ? 'class2' : 'class1';

            $stmt = $conn->prepare(
                "INSERT INTO students (name, email, roll_no, department, class_id, enrolled) VALUES (?, ?, ?, ?, ?, 0)"
            );
            $stmt->bind_param("sssss", $name, $email, $roll, $dept, $class_id);

            if ($stmt->execute()) {
                $student_id = $conn->insert_id;   // Get the new student's database ID

                // Step 5: Save student info to PHP session
                $_SESSION['student_name']     = $name;
                $_SESSION['student_roll']     = $roll;
                $_SESSION['student_email']    = $email;
                $_SESSION['student_dept']     = $dept;
                $_SESSION['student_db_id']    = $student_id;
                $_SESSION['student_enrolled'] = false;

                $stmt->close();
                header('Location: voice.php');
                exit;
            } else {
                $error_msg = 'Registration failed. Please try again.';
                $stmt->close();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VocalID — Student Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    #app { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:40px 20px; }
    .error-msg { color:var(--accent-rose); font-size:.8rem; margin-top:8px; background:rgba(255,77,109,.08); border:1px solid rgba(255,77,109,.2); border-radius:8px; padding:10px 14px; }
    .label-accent { font-size:.68rem; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:var(--accent-emerald); }
  </style>
</head>
<body>
<div id="app">
  <div class="auth-panel">
    <a href="index.php" class="btn-ghost-vocalid mb-4 px-0">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg> Back
    </a>
    <div class="auth-icon" style="background:rgba(0,229,160,.1);border-color:rgba(0,229,160,.2);">
      <svg width="22" height="22" fill="none" stroke="#00e5a0" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
    </div>
    <p class="label-accent mb-2">Student Registration</p>
    <h2 style="font-size:1.9rem;margin-bottom:6px;">Your Details</h2>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:32px;">Register to begin voice enrollment</p>

    <form method="POST" action="student-login.php">
      <div class="mb-3">
        <label class="v-label">Full Name</label>
        <input type="text" name="student_name" required class="v-input"
               placeholder="Enter your full name"
               value="<?= htmlspecialchars($_POST['student_name'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="v-label">Email Address</label>
        <input type="email" name="student_email" required class="v-input"
               placeholder="you@college.edu"
               value="<?= htmlspecialchars($_POST['student_email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="v-label">Roll Number</label>
        <input type="text" name="student_roll" required class="v-input"
               placeholder="e.g. CS001"
               value="<?= htmlspecialchars($_POST['student_roll'] ?? '') ?>">
      </div>
      <div class="mb-4">
        <label class="v-label">Department</label>
        <select name="student_dept" required class="v-input">
          <option value="">Select department</option>
          <option value="SS"  <?= (($_POST['student_dept'] ?? '') === 'SS' ? 'selected' : '') ?>>MSc</option>
          <option value="CS"  <?= (($_POST['student_dept'] ?? '') === 'CS' ? 'selected' : '') ?>>Computer Science</option>
          <option value="IT"  <?= (($_POST['student_dept'] ?? '') === 'IT' ? 'selected' : '') ?>>Information Technology</option>
          <option value="EC"  <?= (($_POST['student_dept'] ?? '') === 'EC' ? 'selected' : '') ?>>Electronics</option>
          <option value="ME"  <?= (($_POST['student_dept'] ?? '') === 'ME' ? 'selected' : '') ?>>Mechanical</option>
          <option value="CE"  <?= (($_POST['student_dept'] ?? '') === 'CE' ? 'selected' : '') ?>>Civil</option>
        </select>
      </div>

      <!-- Error message display -->
      <?php if (!empty($error_msg)): ?>
        <p class="error-msg"><?= htmlspecialchars($error_msg) ?></p>
      <?php endif; ?>

      <button type="submit" class="btn-vocalid emerald w-100 justify-content-center">
        Begin Voice Enrollment
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
      </button>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

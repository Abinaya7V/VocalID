<?php
// ============================================================
// faculty-login.php — Faculty Login Page
// Validates credentials against the MySQL database
// Uses password_verify() to check bcrypt-hashed passwords
// ============================================================
session_start();

// If already logged in, redirect to dashboard (no need to login again)
if (isset($_SESSION['faculty_logged_in']) && $_SESSION['faculty_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// --- Include database connection ---
require_once 'db.php';

$error_msg = '';   // Will hold any error message to display

// ============================================================
// Handle POST request (form submission)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Step 1: Get and sanitize form inputs
    // trim() removes extra spaces; htmlspecialchars() prevents XSS attacks
    $faculty_id = trim($_POST['faculty_id'] ?? '');
    $password   = $_POST['faculty_password'] ?? '';

    // Step 2: Basic validation
    if (empty($faculty_id) || empty($password)) {
        $error_msg = 'Please enter both Faculty ID and password.';
    } else {
        // Step 3: Look up the user in the database
        // Use prepared statement to prevent SQL injection attacks
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $faculty_id);   // "s" = string type
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // User found — now verify password
            $user = $result->fetch_assoc();

            // Step 4: password_verify() compares plain text with bcrypt hash
            if (password_verify($password, $user['password'])) {
                // ✅ Login successful! Set session variables
                $_SESSION['faculty_logged_in'] = true;
                $_SESSION['faculty_id']         = $user['username'];
                $_SESSION['faculty_db_id']      = $user['id'];
                $_SESSION['faculty_role']       = $user['role'];

                // Step 5: Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                // Wrong password
                $error_msg = 'Invalid Faculty ID or password. Please try again.';
            }
        } else {
            // No user found with that ID
            $error_msg = 'Invalid Faculty ID or password. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VocalID — Faculty Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    #app { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:40px 20px; }
    .error-msg { color:var(--accent-rose); font-size:.8rem; margin-top:8px; background:rgba(255,77,109,.08); border:1px solid rgba(255,77,109,.2); border-radius:8px; padding:10px 14px; }
    .label-accent { font-size:.68rem; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:var(--accent-cyan); }
    .demo-hint { font-size:.72rem; color:var(--text-muted); text-align:center; margin-top:14px; padding:10px; background:rgba(0,212,255,.04); border:1px solid rgba(0,212,255,.1); border-radius:8px; }
  </style>
</head>
<body>
<div id="app">
  <div class="auth-panel">
    <a href="index.php" class="btn-ghost-vocalid mb-4 px-0">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg> Back
    </a>
    <div class="auth-icon">
      <svg width="22" height="22" fill="none" stroke="#00d4ff" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/></svg>
    </div>
    <p class="label-accent mb-2">Faculty Authentication</p>
    <h2 style="font-size:1.9rem;margin-bottom:6px;">Sign In</h2>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:32px;">Access the faculty control panel</p>

    <!-- 
      Form POSTs to this same page (faculty-login.php)
      PHP at the top handles the login logic
    -->
    <form method="POST" action="faculty-login.php">

      <div class="mb-3">
        <label class="v-label">Faculty ID</label>
        <!-- value="..." re-populates field if login fails -->
        <input type="text" name="faculty_id" required class="v-input"
               placeholder="e.g. FAC-2024-001"
               value="<?= htmlspecialchars($_POST['faculty_id'] ?? '') ?>">
      </div>

      <div class="mb-1">
        <label class="v-label">Password</label>
        <input type="password" name="faculty_password" required class="v-input"
               placeholder="Enter your password">
      </div>

      <!-- Show error message if login failed -->
      <?php if (!empty($error_msg)): ?>
        <p class="error-msg"><?= htmlspecialchars($error_msg) ?></p>
      <?php endif; ?>

      <div class="mt-4">
        <button type="submit" class="btn-vocalid w-100 justify-content-center">
          Continue to Dashboard
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </div>

      <!-- Demo credentials hint -->
      <div class="demo-hint">
        Demo: <strong style="color:var(--accent-cyan);">FAC-2024-001</strong> &nbsp;|&nbsp; Password: <strong style="color:var(--accent-cyan);">password</strong>
      </div>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

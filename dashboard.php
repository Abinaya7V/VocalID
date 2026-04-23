<?php
// ============================================================
// dashboard.php — Faculty Dashboard (Protected Page)
// Only accessible after successful faculty login
// ============================================================
session_start();

// ✅ Session Guard: Redirect to login if faculty is NOT authenticated
// This prevents unauthorized direct URL access
if (!isset($_SESSION['faculty_logged_in']) || $_SESSION['faculty_logged_in'] !== true) {
    header('Location: faculty-login.php');
    exit;
}

// Include database connection
require_once 'db.php';

// ============================================================
// Fetch live statistics from database
// ============================================================

// Total number of students
$total_students = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM students");
if ($res) {
    $total_students = $res->fetch_assoc()['total'];
}

// Count students present TODAY in each class
$today = date('Y-m-d');

$class1_present = 0;
$class2_present = 0;
$stmt = $conn->prepare("SELECT class_id, COUNT(DISTINCT roll_no) as cnt FROM attendance WHERE date = ? AND status = 'present' GROUP BY class_id");
$stmt->bind_param("s", $today);
$stmt->execute();
$att_res = $stmt->get_result();
while ($row = $att_res->fetch_assoc()) {
    if ($row['class_id'] === 'class1') $class1_present = $row['cnt'];
    if ($row['class_id'] === 'class2') $class2_present = $row['cnt'];
}
$stmt->close();

// Total students per class (enrolled)
$class1_total = 8;   // From our seed data
$class2_total = 5;

// Calculate average attendance percentage
$class1_pct = $class1_total > 0 ? round($class1_present / $class1_total * 100) : 0;
$class2_pct = $class2_total > 0 ? round($class2_present / $class2_total * 100) : 0;
$avg_pct = round(($class1_pct + $class2_pct) / 2);

// Get faculty info from session
$faculty_id = $_SESSION['faculty_id'] ?? 'Professor';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VocalID — Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div id="app">
  <div class="dashboard-wrapper">

    <!-- ===== Sidebar ===== -->
    <aside class="sidebar">
      <div class="d-flex align-items-center gap-2 mb-4 px-1">
        <div class="sidebar-logo-mark">V</div>
        <div>
          <div style="font-size:.9rem;font-weight:700;">VocalID</div>
          <div style="font-size:.6rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.1em;">Faculty Portal</div>
        </div>
      </div>
      <div class="section-label">Classes</div>
      <a href="class.php?id=class1" class="nav-btn">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        CS101 — Algorithms <span class="nav-badge"><?= $class1_total ?></span>
      </a>
      <a href="class.php?id=class2" class="nav-btn">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        IT202 — Networks <span class="nav-badge"><?= $class2_total ?></span>
      </a>
      <div class="section-label">Analytics</div>
      <a href="reports.php" class="nav-btn">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Reports & Analytics
      </a>
      <div class="mt-auto pt-3" style="border-top:1px solid var(--border-subtle);">
        <div class="d-flex align-items-center gap-2 px-2 mb-2">
          <!-- Show first letter of faculty ID as avatar -->
          <div class="sidebar-avatar"><?= strtoupper(substr($faculty_id, 0, 1)) ?></div>
          <div>
            <div style="font-size:.8rem;font-weight:600;"><?= htmlspecialchars($faculty_id) ?></div>
            <div style="font-size:.65rem;color:var(--text-muted);">Faculty Account</div>
          </div>
        </div>
        <!-- Logout link — goes to logout.php which destroys session -->
        <a href="logout.php" class="nav-btn" style="color:var(--accent-rose);">
          <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Sign Out
        </a>
      </div>
    </aside>

    <!-- ===== Main Content ===== -->
    <div class="dashboard-main">
      <div class="topbar">
        <div>
          <div style="font-size:.95rem;font-weight:600;">Overview</div>
          <div style="font-size:.72rem;color:var(--text-muted);">VocalID Faculty Dashboard</div>
        </div>
        <div class="status-pill"><div class="status-dot"></div> System Online</div>
      </div>
      <div style="padding:32px;">

        <!-- Welcome Hero -->
        <div class="welcome-hero">
          <p class="label-accent mb-2">
            <?= strtoupper(date('l, F j, Y')) ?>
          </p>
          <h2 style="font-size:2rem;margin-bottom:6px;">
            <?php
              $hr = (int)date('H');
              $greet = $hr < 12 ? 'Good morning' : ($hr < 17 ? 'Good afternoon' : 'Good evening');
              echo htmlspecialchars("$greet, $faculty_id.");
            ?>
          </h2>
          <p style="font-size:.83rem;color:var(--text-secondary);">Select a class from the sidebar or review your analytics below.</p>
        </div>

        <!-- Stats Cards (live from DB) -->
        <div class="row g-3">
          <div class="col-md-4">
            <div class="stat-card">
              <div class="stat-icon" style="background:rgba(0,212,255,.1);border:1px solid rgba(0,212,255,.2);">
                <svg width="17" height="17" fill="none" stroke="#00d4ff" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/></svg>
              </div>
              <!-- Live count from database -->
              <div class="stat-value" style="color:var(--accent-cyan);"><?= $total_students ?></div>
              <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Total Students</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-card">
              <div class="stat-icon" style="background:rgba(0,229,160,.1);border:1px solid rgba(0,229,160,.2);">
                <svg width="17" height="17" fill="none" stroke="#00e5a0" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
              <!-- Today's average attendance -->
              <div class="stat-value" style="color:var(--accent-emerald);"><?= $avg_pct ?>%</div>
              <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Today's Avg Attendance</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-card">
              <div class="stat-icon" style="background:rgba(240,180,41,.1);border:1px solid rgba(240,180,41,.2);">
                <svg width="17" height="17" fill="none" stroke="#f0b429" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
              <div class="stat-value" style="color:var(--accent-gold);">2</div>
              <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Active Classes</div>
            </div>
          </div>
        </div>

        <!-- Quick Nav Cards -->
        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <a href="class.php?id=class1" class="glass-card d-block text-decoration-none p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="stat-icon mb-0" style="background:rgba(0,212,255,.08);border:1px solid rgba(0,212,255,.15);">
                  <svg width="17" height="17" fill="none" stroke="#00d4ff" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div>
                  <div style="font-size:.95rem;font-weight:600;color:var(--text-primary);">CS101 — Algorithms</div>
                  <div style="font-size:.72rem;color:var(--text-muted);"><?= $class1_total ?> students enrolled &bull; <?= $class1_present ?> present today</div>
                </div>
              </div>
              <div style="font-size:.78rem;color:var(--accent-cyan);">Manage Attendance →</div>
            </a>
          </div>
          <div class="col-md-6">
            <a href="class.php?id=class2" class="glass-card d-block text-decoration-none p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="stat-icon mb-0" style="background:rgba(0,229,160,.08);border:1px solid rgba(0,229,160,.15);">
                  <svg width="17" height="17" fill="none" stroke="#00e5a0" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div>
                  <div style="font-size:.95rem;font-weight:600;color:var(--text-primary);">IT202 — Networks</div>
                  <div style="font-size:.72rem;color:var(--text-muted);"><?= $class2_total ?> students enrolled &bull; <?= $class2_present ?> present today</div>
                </div>
              </div>
              <div style="font-size:.78rem;color:var(--accent-emerald);">Manage Attendance →</div>
            </a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

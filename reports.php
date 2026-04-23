<?php
// ============================================================
// reports.php — Reports & Analytics Page (Protected)
// Fetches ALL attendance data from the database and displays it
// ============================================================
session_start();

// Guard: Redirect if not logged in
if (!isset($_SESSION['faculty_logged_in']) || $_SESSION['faculty_logged_in'] !== true) {
    header('Location: faculty-login.php');
    exit;
}

require_once 'db.php';

$today = date('Y-m-d');

// ============================================================
// Fetch today's attendance stats per class
// ============================================================
$stats = ['class1' => ['present'=>0,'absent'=>0,'total'=>0], 'class2' => ['present'=>0,'absent'=>0,'total'=>0]];

// Count students per class
$res = $conn->query("SELECT class_id, COUNT(*) as total FROM students GROUP BY class_id");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (isset($stats[$row['class_id']])) {
            $stats[$row['class_id']]['total'] = $row['total'];
        }
    }
}

// Count today's attendance per class
$stmt = $conn->prepare("SELECT class_id, status, COUNT(*) as cnt FROM attendance WHERE date = ? GROUP BY class_id, status");
$stmt->bind_param("s", $today);
$stmt->execute();
$att_res = $stmt->get_result();
while ($row = $att_res->fetch_assoc()) {
    $cid = $row['class_id'];
    if (isset($stats[$cid])) {
        $stats[$cid][$row['status']] = $row['cnt'];
    }
}
$stmt->close();

// Calculate percentages
foreach ($stats as &$s) {
    $s['pct'] = $s['total'] > 0 ? round(($s['present'] / $s['total']) * 100) : 0;
}
unset($s);

$overall_pct = round(($stats['class1']['pct'] + $stats['class2']['pct']) / 2);

// ============================================================
// Fetch all attendance records (with filter support)
// ============================================================
$filter_class = $_GET['class'] ?? 'all';
$filter_date  = $_GET['date']  ?? '';

// Build query based on filters
$where_clauses = [];
$bind_types    = '';
$bind_values   = [];

if ($filter_class !== 'all') {
    $where_clauses[] = 'a.class_id = ?';
    $bind_types  .= 's';
    $bind_values[] = $filter_class;
}
if (!empty($filter_date)) {
    $where_clauses[] = 'a.date = ?';
    $bind_types  .= 's';
    $bind_values[] = $filter_date;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$sql = "SELECT a.student_name, a.roll_no, a.class_id, a.date, a.time, a.status, a.confidence
        FROM attendance a
        $where_sql
        ORDER BY a.date DESC, a.time DESC";

$attendance_records = [];

if (!empty($bind_types)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($bind_types, ...$bind_values);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
    }
}

// ============================================================
// Top performers: students with highest attendance rate
// ============================================================
$top_performers = [];
$perf_res = $conn->query(
    "SELECT student_name,
            COUNT(*) as total_days,
            SUM(status='present') as present_days,
            ROUND(SUM(status='present') / COUNT(*) * 100, 0) as attendance_pct
     FROM attendance
     GROUP BY student_name
     ORDER BY attendance_pct DESC
     LIMIT 5"
);
if ($perf_res) {
    while ($row = $perf_res->fetch_assoc()) {
        $top_performers[] = $row;
    }
}

$faculty_id = $_SESSION['faculty_id'] ?? 'Professor';

// Helper: export as CSV
$do_export = isset($_GET['export']) && $_GET['export'] === 'csv';
if ($do_export) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="VocalID_Report_' . $today . '.csv"');
    echo "Class,Roll No,Student Name,Date,Time,Status,Confidence\n";
    foreach ($attendance_records as $rec) {
        $class_label = $rec['class_id'] === 'class1' ? 'CS101' : 'IT202';
        echo "{$class_label},{$rec['roll_no']},{$rec['student_name']},{$rec['date']},{$rec['time']},{$rec['status']},{$rec['confidence']}%\n";
    }
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VocalID — Reports & Analytics</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .label-accent { font-size:.68rem; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:var(--accent-cyan); }
    .report-card { background:rgba(255,255,255,.02); border:1px solid var(--border-subtle); border-radius:14px; padding:22px; height:100%; }
    .weekly-row { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
    .bar-bg  { flex:1; height:4px; background:rgba(255,255,255,.06); border-radius:4px; overflow:hidden; }
    .bar-fill{ height:100%; background:linear-gradient(90deg,var(--accent-cyan),var(--accent-emerald)); border-radius:4px; }
    .perf-row { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
    .rank-badge { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.68rem; font-weight:700; flex-shrink:0; }
    .conf-wrap { display:flex; align-items:center; gap:6px; }
    .conf-bg  { flex:1; height:3px; background:rgba(255,255,255,.07); border-radius:3px; overflow:hidden; min-width:50px; }
    .conf-fill{ height:100%; background:linear-gradient(90deg,var(--accent-cyan),var(--accent-emerald)); border-radius:3px; }
  </style>
</head>
<body>
<div id="app">
  <div class="dashboard-wrapper">
    <!-- Sidebar -->
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
        CS101 — Algorithms <span class="nav-badge">8</span>
      </a>
      <a href="class.php?id=class2" class="nav-btn">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        IT202 — Networks <span class="nav-badge">5</span>
      </a>
      <div class="section-label">Analytics</div>
      <a href="reports.php" class="nav-btn active">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Reports & Analytics
      </a>
      <div class="mt-auto pt-3" style="border-top:1px solid var(--border-subtle);">
        <a href="dashboard.php" class="nav-btn">
          <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          Dashboard
        </a>
        <a href="logout.php" class="nav-btn" style="color:var(--accent-rose);">
          <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Sign Out
        </a>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="dashboard-main">
      <div class="topbar">
        <div>
          <div style="font-size:.95rem;font-weight:600;">Reports & Analytics</div>
          <div style="font-size:.72rem;color:var(--text-muted);">Attendance insights & performance</div>
        </div>
        <div class="status-pill"><div class="status-dot"></div> Live Data</div>
      </div>
      <div style="padding:32px;">

        <div class="d-flex justify-content-between align-items-start mb-4 gap-3 flex-wrap">
          <div>
            <p class="label-accent mb-2">Analytics</p>
            <h2 style="font-size:1.7rem;margin-bottom:6px;">Reports & Insights</h2>
            <p style="font-size:.82rem;color:var(--text-muted);">Live attendance data from database. Updated in real time.</p>
          </div>
          <!-- Export button — adds ?export=csv to URL, PHP handles the download -->
          <a href="reports.php?export=csv&class=<?= urlencode($filter_class) ?>" class="btn-vocalid emerald flex-shrink-0">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            Export CSV
          </a>
        </div>

        <!-- Summary Stats (live from DB) -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-md-3">
            <div class="stat-card text-center">
              <div class="stat-value" style="color:var(--accent-cyan);"><?= $stats['class1']['present'] ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">CS101 Present</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-card text-center">
              <div class="stat-value" style="color:var(--accent-rose);"><?= $stats['class1']['absent'] ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">CS101 Absent</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-card text-center">
              <div class="stat-value" style="color:var(--accent-emerald);"><?= $stats['class2']['present'] ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">IT202 Present</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="stat-card text-center">
              <div class="stat-value" style="color:var(--accent-rose);"><?= $stats['class2']['absent'] ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">IT202 Absent</div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <!-- Top Performers (live from DB) -->
          <div class="col-md-6">
            <div class="report-card">
              <div style="font-size:.85rem;font-weight:600;margin-bottom:20px;">Top Performers (All Time)</div>
              <?php
              $rank_styles = [
                1 => 'background:rgba(240,180,41,.15);color:var(--accent-gold);border:1px solid rgba(240,180,41,.3);',
                2 => 'background:rgba(255,255,255,.06);color:var(--text-secondary);border:1px solid var(--border-subtle);',
                3 => 'background:rgba(205,127,50,.1);color:#cd7f32;border:1px solid rgba(205,127,50,.3);',
              ];
              foreach ($top_performers as $i => $perf):
                $rank  = $i + 1;
                $style = $rank_styles[$rank] ?? 'background:rgba(255,255,255,.04);color:var(--text-muted);border:1px solid var(--border-subtle);';
              ?>
              <div class="perf-row">
                <div class="rank-badge" style="<?= $style ?>"><?= $rank ?></div>
                <span style="font-size:.82rem;color:var(--text-secondary);flex:1;"><?= htmlspecialchars($perf['student_name']) ?></span>
                <span style="font-size:.78rem;font-family:var(--font-mono);color:var(--accent-emerald);font-weight:700;"><?= $perf['attendance_pct'] ?>%</span>
              </div>
              <?php endforeach; ?>
              <?php if (empty($top_performers)): ?>
                <p style="font-size:.8rem;color:var(--text-muted);text-align:center;padding:20px 0;">No attendance data yet. Start marking attendance!</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Class Statistics -->
          <div class="col-md-6">
            <div class="report-card">
              <div style="font-size:.85rem;font-weight:600;margin-bottom:20px;">Class Statistics (Today)</div>
              <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                  <span style="font-size:.8rem;color:var(--text-secondary);">CS101 — Algorithms</span>
                  <span style="font-size:.76rem;font-family:var(--font-mono);color:var(--accent-cyan);"><?= $stats['class1']['pct'] ?>%</span>
                </div>
                <div style="height:4px;background:rgba(255,255,255,.05);border-radius:4px;overflow:hidden;">
                  <div style="height:100%;width:<?= $stats['class1']['pct'] ?>%;background:linear-gradient(90deg,var(--accent-cyan),var(--accent-emerald));border-radius:4px;"></div>
                </div>
              </div>
              <div>
                <div class="d-flex justify-content-between mb-1">
                  <span style="font-size:.8rem;color:var(--text-secondary);">IT202 — Networks</span>
                  <span style="font-size:.76rem;font-family:var(--font-mono);color:var(--accent-cyan);"><?= $stats['class2']['pct'] ?>%</span>
                </div>
                <div style="height:4px;background:rgba(255,255,255,.05);border-radius:4px;overflow:hidden;">
                  <div style="height:100%;width:<?= $stats['class2']['pct'] ?>%;background:linear-gradient(90deg,var(--accent-cyan),var(--accent-emerald));border-radius:4px;"></div>
                </div>
              </div>
              <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border-subtle);">
                <div class="d-flex justify-content-between">
                  <span style="font-size:.78rem;color:var(--text-muted);">Overall Average (Today)</span>
                  <span style="font-size:.85rem;font-family:var(--font-mono);color:var(--accent-emerald);font-weight:700;"><?= $overall_pct ?>%</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Detailed Attendance Table (live from DB) -->
        <div class="v-table-wrap mt-4">
          <div class="v-table-head d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span style="font-size:.88rem;font-weight:600;">
              All Attendance Records
              <span style="font-size:.72rem;color:var(--text-muted);font-weight:400;">(<?= count($attendance_records) ?> records)</span>
            </span>
            <!-- Filter form — GET request, PHP handles filtering -->
            <form method="GET" action="reports.php" class="d-flex gap-2 align-items-center">
              <select name="class" class="v-input" style="width:auto;padding:7px 12px;font-size:.78rem;">
                <option value="all"   <?= $filter_class === 'all'    ? 'selected' : '' ?>>All Classes</option>
                <option value="class1"<?= $filter_class === 'class1' ? 'selected' : '' ?>>CS101</option>
                <option value="class2"<?= $filter_class === 'class2' ? 'selected' : '' ?>>IT202</option>
              </select>
              <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>"
                     class="v-input" style="width:auto;padding:7px 12px;font-size:.78rem;">
              <button type="submit" class="btn-vocalid" style="padding:8px 16px;font-size:.78rem;">Filter</button>
              <a href="reports.php" class="btn-ghost-vocalid" style="padding:8px 14px;font-size:.78rem;">Clear</a>
            </form>
          </div>
          <div style="overflow-x:auto;">
            <table>
              <thead>
                <tr>
                  <th>Class</th>
                  <th>Roll No</th>
                  <th>Student Name</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Status</th>
                  <th>Confidence</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($attendance_records)): ?>
                  <tr>
                    <td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">
                      No attendance records found. <?= $filter_class !== 'all' || $filter_date ? 'Try adjusting filters.' : 'Start marking attendance!' ?>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($attendance_records as $rec):
                    $class_label = $rec['class_id'] === 'class1' ? 'CS101' : 'IT202';
                    $badge_cls   = $rec['status'] === 'present' ? 'badge-present' : 'badge-absent';
                    $conf        = (int)$rec['confidence'];
                  ?>
                  <tr>
                    <td style="font-size:.75rem;color:var(--accent-cyan);font-family:var(--font-mono);"><?= $class_label ?></td>
                    <td class="td-roll"><?= htmlspecialchars($rec['roll_no']) ?></td>
                    <td class="td-name"><?= htmlspecialchars($rec['student_name']) ?></td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($rec['date']) ?></td>
                    <td style="font-size:.78rem;color:var(--text-muted);font-family:var(--font-mono);"><?= htmlspecialchars($rec['time']) ?></td>
                    <td><span class="badge-v <?= $badge_cls ?>"><?= ucfirst($rec['status']) ?></span></td>
                    <td>
                      <?php if ($conf > 0): ?>
                        <div class="conf-wrap">
                          <div class="conf-bg"><div class="conf-fill" style="width:<?= $conf ?>%"></div></div>
                          <span style="font-size:.76rem;font-family:var(--font-mono);color:var(--text-secondary);"><?= $conf ?>%</span>
                        </div>
                      <?php else: ?>—<?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

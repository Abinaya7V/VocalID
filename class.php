<?php
// ============================================================
// class.php — Class Attendance Management (Protected)
// Faculty can view students, mark present/absent manually,
// and use voice recognition to mark attendance
// ============================================================
session_start();

// Guard: Requires faculty login
if (!isset($_SESSION['faculty_logged_in']) || $_SESSION['faculty_logged_in'] !== true) {
    header('Location: faculty-login.php');
    exit;
}

require_once 'db.php';

// ============================================================
// Get class ID from URL: class.php?id=class1
// Validate it to prevent injection
// ============================================================
$class_id = $_GET['id'] ?? 'class1';
if (!in_array($class_id, ['class1', 'class2'])) {
    $class_id = 'class1';
}

// Class metadata
$class_info = [
    'class1' => ['name' => 'CS101 — Algorithms',    'dept' => 'CS', 'color' => 'var(--accent-cyan)'],
    'class2' => ['name' => 'IT202 — Networks',       'dept' => 'IT', 'color' => 'var(--accent-emerald)'],
];
$current_class = $class_info[$class_id];

// ============================================================
// Fetch students for this class from database
// ============================================================
$students = [];
$stmt = $conn->prepare("SELECT id, name, roll_no, department FROM students WHERE class_id = ? ORDER BY roll_no ASC");
$stmt->bind_param("s", $class_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// ============================================================
// Fetch today's attendance records for this class
// ============================================================
$today = date('Y-m-d');
$attendance_today = [];  // ['roll_no' => ['status'=>..., 'time'=>..., 'confidence'=>...]]

$stmt = $conn->prepare("SELECT roll_no, status, time, confidence FROM attendance WHERE class_id = ? AND date = ?");
$stmt->bind_param("ss", $class_id, $today);
$stmt->execute();
$att_result = $stmt->get_result();
while ($row = $att_result->fetch_assoc()) {
    $attendance_today[$row['roll_no']] = $row;
}
$stmt->close();

// Counts
$present_count = count(array_filter($attendance_today, fn($a) => $a['status'] === 'present'));
$absent_count  = count(array_filter($attendance_today, fn($a) => $a['status'] === 'absent'));
$total         = count($students);
$pending_count = $total - count($attendance_today);

$faculty_id = $_SESSION['faculty_id'] ?? 'Professor';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VocalID — <?= htmlspecialchars($current_class['name']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .label-accent { font-size:.68rem; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:var(--accent-cyan); }
    .mic-btn { width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg,var(--accent-cyan),#0099cc); border:none; display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 0 30px rgba(0,212,255,.2); transition:transform .25s, box-shadow .25s; margin:0 auto; }
    .mic-btn:hover { transform:scale(1.06); box-shadow:0 0 60px rgba(0,212,255,.45); }
    .mic-btn.listening { animation:micPulse 1.5s infinite; }
    .waveform { display:flex; align-items:center; justify-content:center; gap:4px; height:52px; }
    .wave-bar { width:3px; background:linear-gradient(180deg,var(--accent-cyan),rgba(0,212,255,0.3)); border-radius:3px; animation:waveAnim 1.2s ease-in-out infinite; }
    .wave-bar:nth-child(1){height:14px;animation-delay:0s}   .wave-bar:nth-child(2){height:26px;animation-delay:.1s}
    .wave-bar:nth-child(3){height:20px;animation-delay:.2s}  .wave-bar:nth-child(4){height:38px;animation-delay:.3s}
    .wave-bar:nth-child(5){height:48px;animation-delay:.4s}  .wave-bar:nth-child(6){height:32px;animation-delay:.5s}
    .voice-panel { background:rgba(255,255,255,.02); border:1px solid var(--border-subtle); border-radius:16px; padding:28px; text-align:center; margin-bottom:24px; }
    .api-log { font-size:.72rem; color:var(--text-muted); font-family:var(--font-mono); margin-top:10px; min-height:20px; }
    tr.row-present td { background:rgba(0,229,160,.04); }
    tr.row-absent  td { background:rgba(255,77,109,.04); }
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
      <a href="class.php?id=class1" class="nav-btn <?= $class_id === 'class1' ? 'active' : '' ?>">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        CS101 — Algorithms <span class="nav-badge">8</span>
      </a>
      <a href="class.php?id=class2" class="nav-btn <?= $class_id === 'class2' ? 'active' : '' ?>">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        IT202 — Networks <span class="nav-badge">5</span>
      </a>
      <div class="section-label">Analytics</div>
      <a href="reports.php" class="nav-btn">
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
          <div style="font-size:.95rem;font-weight:600;"><?= htmlspecialchars($current_class['name']) ?></div>
          <div style="font-size:.72rem;color:var(--text-muted);"><?= date('l, F j, Y') ?> &bull; <?= $total ?> students</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <div class="status-pill"><div class="status-dot"></div> System Online</div>
        </div>
      </div>

      <div style="padding:32px;">

        <!-- Stats row -->
        <div class="row g-3 mb-4">
          <div class="col-4">
            <div class="stat-card text-center">
              <div class="stat-value" style="color:var(--accent-emerald);"><?= $present_count ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;">Present</div>
            </div>
          </div>
          <div class="col-4">
            <div class="stat-card text-center">
              <div class="stat-value" style="color:var(--accent-rose);"><?= $absent_count ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;">Absent</div>
            </div>
          </div>
          <div class="col-4">
            <div class="stat-card text-center">
              <div class="stat-value" style="color:var(--accent-gold);"><?= $pending_count ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;">Pending</div>
            </div>
          </div>
        </div>

        <!-- Voice Recognition Panel -->
        <div class="voice-panel">
          <p class="label-accent mb-3">Voice Recognition — Mark Attendance</p>
          <button class="mic-btn mb-3" id="mic-btn" onclick="startVoiceRecognition()">
            <svg width="28" height="28" fill="none" stroke="#000" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
          </button>
          <div id="voice-waveform" class="waveform mb-2" style="display:none;">
            <div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div>
            <div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div>
          </div>
          <div id="voice-status" style="font-size:.82rem;color:var(--text-secondary);">Click microphone to start voice detection</div>
          <p class="api-log" id="voice-log"></p>
        </div>

        <!-- Student Attendance Table -->
        <div class="v-table-wrap">
          <div class="v-table-head d-flex justify-content-between align-items-center">
            <span style="font-size:.88rem;font-weight:600;"><?= htmlspecialchars($current_class['name']) ?> — Today's Attendance</span>
            <button onclick="resetAttendance()" class="btn-sm-danger">Reset Today</button>
          </div>
          <div style="overflow-x:auto;">
            <table id="student-table">
              <thead>
                <tr>
                  <th>Roll No</th>
                  <th>Student Name</th>
                  <th>Status</th>
                  <th>Time Marked</th>
                  <th>Confidence</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $student):
                  $roll = $student['roll_no'];
                  $att  = $attendance_today[$roll] ?? null;
                  $status    = $att['status']     ?? 'pending';
                  $time_mark = $att['time']        ?? '—';
                  $conf      = $att['confidence']  ?? 0;
                  $row_class = $status === 'present' ? 'row-present' : ($status === 'absent' ? 'row-absent' : '');
                  $badge_cls = $status === 'present' ? 'badge-present' : ($status === 'absent' ? 'badge-absent' : 'badge-pending');
                  $badge_lbl = ucfirst($status);
                ?>
                <tr class="<?= $row_class ?>" id="row-<?= htmlspecialchars($roll) ?>">
                  <td class="td-roll"><?= htmlspecialchars($roll) ?></td>
                  <td class="td-name"><?= htmlspecialchars($student['name']) ?></td>
                  <td><span class="badge-v <?= $badge_cls ?>" id="badge-<?= htmlspecialchars($roll) ?>"><?= $badge_lbl ?></span></td>
                  <td style="font-size:.78rem;color:var(--text-muted);font-family:var(--font-mono);" id="time-<?= htmlspecialchars($roll) ?>">
                    <?= $time_mark ?>
                  </td>
                  <td id="conf-<?= htmlspecialchars($roll) ?>">
                    <?php if ($conf > 0): ?>
                      <div class="conf-wrap">
                        <div class="conf-bg"><div class="conf-fill" style="width:<?= $conf ?>%"></div></div>
                        <span style="font-size:.76rem;font-family:var(--font-mono);color:var(--text-secondary);"><?= $conf ?>%</span>
                      </div>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td>
                    <!-- Manual mark buttons — POST to mark_attendance.php -->
                    <button onclick="manualMark('<?= htmlspecialchars($student['name']) ?>', '<?= htmlspecialchars($roll) ?>', 'present')"
                            class="btn-sm-success me-1">Present</button>
                    <button onclick="manualMark('<?= htmlspecialchars($student['name']) ?>', '<?= htmlspecialchars($roll) ?>', 'absent')"
                            class="btn-sm-danger">Absent</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Current class ID from PHP (safe JSON encoding)
const CLASS_ID = <?= json_encode($class_id) ?>;

// ============================================================
// manualMark() — Faculty manually marks a student present/absent
// Sends data to mark_attendance.php via fetch()
// ============================================================
function manualMark(studentName, rollNo, status) {
  fetch('mark_attendance.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      student_name: studentName,
      roll_no:      rollNo,
      class_id:     CLASS_ID,
      status:       status,
      confidence:   0       // Manual mark has no confidence score
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      updateRowUI(rollNo, status, data.time || '—', 0);
    } else {
      alert('Error: ' + (data.message || 'Could not mark attendance.'));
    }
  })
  .catch(() => alert('Network error. Please try again.'));
}

// ============================================================
// startVoiceRecognition() — Uses Web Speech API to detect name
// After recognition, finds matching student and marks present
// ============================================================
function startVoiceRecognition() {
  const statusEl = document.getElementById('voice-status');
  const logEl    = document.getElementById('voice-log');
  const wf       = document.getElementById('voice-waveform');
  const micBtn   = document.getElementById('mic-btn');

  // Check browser support
  if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
    statusEl.textContent = '⚠️ Voice recognition not supported in this browser. Use Chrome.';
    return;
  }

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  const recognition = new SpeechRecognition();
  recognition.lang = 'en-IN';     // Set to Indian English for better accuracy
  recognition.continuous = false;
  recognition.interimResults = false;

  micBtn.classList.add('listening');
  wf.style.display = 'flex';
  statusEl.style.color = 'var(--accent-cyan)';
  statusEl.textContent = '🎙️ Listening... Say the student\'s name clearly.';

  recognition.onresult = function(event) {
    const transcript = event.results[0][0].transcript.trim();
    const confidence = Math.round(event.results[0][0].confidence * 100);
    logEl.textContent = `Heard: "${transcript}" (${confidence}% confidence)`;
    statusEl.textContent = `Detected: "${transcript}" — searching for student...`;

    // ============================================================
    // Match spoken name against student list
    // Simple contains-match (case-insensitive)
    // ============================================================
    const rows = document.querySelectorAll('#student-table tbody tr');
    let matched = false;

    rows.forEach(row => {
      const nameCell = row.querySelector('.td-name');
      const rollCell = row.querySelector('.td-roll');
      if (!nameCell || !rollCell) return;

      const name = nameCell.textContent.trim().toLowerCase();
      const roll = rollCell.textContent.trim();
      const spoken = transcript.toLowerCase();

      // Check if any word of spoken transcript matches student name
      if (name.includes(spoken) || spoken.includes(name.split(' ')[0].toLowerCase())) {
        matched = true;
        const studentName = nameCell.textContent.trim();
        statusEl.textContent = `✅ Matched: ${studentName} — marking present...`;

        // Send to backend
        fetch('mark_attendance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            student_name: studentName,
            roll_no:      roll,
            class_id:     CLASS_ID,
            confidence:   confidence || 90
          })
        })
        .then(r => r.json())
        .then(data => {
          logEl.textContent = data.message || 'Attendance saved.';
          if (data.success) {
            updateRowUI(roll, 'present', data.time || new Date().toLocaleTimeString(), confidence || 90);
          }
        });
      }
    });

    if (!matched) {
      statusEl.style.color = 'var(--accent-rose)';
      statusEl.textContent = `❌ No match found for "${transcript}". Try again.`;
    }

    micBtn.classList.remove('listening');
    wf.style.display = 'none';
  };

  recognition.onerror = function(e) {
    micBtn.classList.remove('listening');
    wf.style.display = 'none';
    statusEl.style.color = 'var(--accent-rose)';
    statusEl.textContent = '⚠️ Voice recognition error: ' + e.error;
  };

  recognition.onend = function() {
    micBtn.classList.remove('listening');
    wf.style.display = 'none';
  };

  recognition.start();
}

// ============================================================
// updateRowUI() — Updates the table row in the browser without page reload
// ============================================================
function updateRowUI(rollNo, status, time, confidence) {
  const row    = document.getElementById('row-' + rollNo);
  const badge  = document.getElementById('badge-' + rollNo);
  const timeEl = document.getElementById('time-' + rollNo);
  const confEl = document.getElementById('conf-' + rollNo);

  if (!row) return;

  // Update row background color
  row.className = status === 'present' ? 'row-present' : 'row-absent';

  // Update badge
  badge.className = 'badge-v ' + (status === 'present' ? 'badge-present' : 'badge-absent');
  badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);

  // Update time
  timeEl.textContent = time || new Date().toLocaleTimeString();

  // Update confidence bar if available
  if (confidence > 0) {
    confEl.innerHTML = `<div class="conf-wrap">
      <div class="conf-bg"><div class="conf-fill" style="width:${confidence}%"></div></div>
      <span style="font-size:.76rem;font-family:var(--font-mono);color:var(--text-secondary);">${confidence}%</span>
    </div>`;
  }
}

// ============================================================
// resetAttendance() — Clears today's attendance for this class
// ============================================================
function resetAttendance() {
  if (!confirm('Reset all attendance for today in this class? This cannot be undone.')) return;

  fetch('reset_attendance.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ class_id: CLASS_ID })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      location.reload();   // Reload to show fresh state
    } else {
      alert('Reset failed: ' + (data.message || 'Unknown error'));
    }
  });
}
</script>
</body>
</html>

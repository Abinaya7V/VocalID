<?php
// ============================================================
// voice.php — Voice Enrollment Page (for Students)
// Reads student info from PHP session
// On completion, sends data to mark_attendance.php via fetch()
// ============================================================
session_start();

// Guard: Redirect to student login if no student session exists
if (!isset($_SESSION['student_name']) || empty($_SESSION['student_name'])) {
    header('Location: student-login.php');
    exit;
}

// Pull student info from session (set in student-login.php)
$student_name = $_SESSION['student_name'];
$student_roll = $_SESSION['student_roll'];
$student_dept = $_SESSION['student_dept'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VocalID — Voice Enrollment</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    #app { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:40px 20px; }
    .auth-panel { max-width:660px; }
    .label-accent { font-size:.68rem; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:var(--accent-cyan); }
    .progress-track { height:4px; background:rgba(255,255,255,.07); border-radius:4px; overflow:hidden; margin:8px 0; }
    .progress-fill  { height:100%; width:0; background:linear-gradient(90deg,var(--accent-cyan),var(--accent-emerald)); border-radius:4px; transition:width .5s ease; }
    .prompt-card { background:rgba(255,255,255,.03); border:1px solid var(--border-subtle); border-radius:12px; padding:16px; height:100%; }
    .rec-pill { display:flex; align-items:center; gap:8px; padding:8px 16px; background:rgba(255,77,109,.08); border:1px solid rgba(255,77,109,.2); border-radius:100px; color:var(--accent-rose); font-size:.78rem; width:fit-content; margin:0 auto; }
    .rec-dot  { width:7px; height:7px; border-radius:50%; background:#ff4d6d; animation:pulse 1s infinite; flex-shrink:0; }
    .sample-item { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:rgba(0,229,160,.04); border:1px solid rgba(0,229,160,.12); border-radius:8px; margin-bottom:8px; }
    .success-box { text-align:center; padding:28px; background:rgba(0,229,160,.05); border:1px solid rgba(0,229,160,.2); border-radius:16px; margin-bottom:24px; }
    .api-status { font-size:.75rem; color:var(--text-muted); text-align:center; margin-top:10px; font-family:var(--font-mono); min-height:20px; }
  </style>
</head>
<body>
<div id="app">
  <div class="auth-panel">
    <p class="label-accent mb-1">Voice Biometrics Enrollment</p>
    <h2 style="font-size:1.8rem;margin-bottom:4px;">Record Voice Samples</h2>
    <!-- PHP outputs the student's name from session — no JavaScript needed for this -->
    <p style="font-size:.82rem;color:var(--accent-cyan);margin-bottom:4px;">
      Welcome, <?= htmlspecialchars($student_name) ?> (<?= htmlspecialchars($student_roll) ?>)
    </p>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:28px;">Speak clearly in a quiet environment. We need 4 samples to build your voice profile.</p>

    <div class="d-flex justify-content-between align-items-center">
      <span style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;">Progress</span>
      <span id="sample-count" style="font-size:.7rem;font-family:var(--font-mono);color:var(--accent-cyan);">0 / 4 samples</span>
    </div>
    <div class="progress-track"><div class="progress-fill" id="sample-progress"></div></div>
    <p style="font-size:.67rem;color:var(--text-muted);margin-bottom:24px;">Complete all 4 samples to activate your voice profile</p>

    <!-- Voice prompts — PHP fills in student name and roll number -->
    <div class="row g-2 mb-4">
      <div class="col-6">
        <div class="prompt-card">
          <div class="label-accent mb-1">Sample 01</div>
          <div style="font-size:.86rem;color:var(--accent-cyan);font-style:italic;">"My name is <?= htmlspecialchars($student_name) ?>"</div>
        </div>
      </div>
      <div class="col-6">
        <div class="prompt-card">
          <div class="label-accent mb-1">Sample 02</div>
          <div style="font-size:.86rem;color:var(--accent-cyan);font-style:italic;">"Roll number <?= htmlspecialchars($student_roll) ?>"</div>
        </div>
      </div>
      <div class="col-6">
        <div class="prompt-card">
          <div class="label-accent mb-1">Sample 03</div>
          <div style="font-size:.86rem;color:var(--accent-cyan);font-style:italic;">"I am present today"</div>
        </div>
      </div>
      <div class="col-6">
        <div class="prompt-card">
          <div class="label-accent mb-1">Sample 04</div>
          <div style="font-size:.86rem;color:var(--accent-cyan);font-style:italic;">"Good morning, I'm here"</div>
        </div>
      </div>
    </div>

    <div id="recording-waveform" class="waveform mb-2" style="display:none;">
      <div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div>
      <div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div><div class="wave-bar"></div>
    </div>
    <div id="recording-status" class="rec-pill" style="display:none;"><div class="rec-dot"></div> Recording in progress — speak now</div>

    <div class="text-center mb-3">
      <button id="record-btn" onclick="recordVoiceSample()" class="btn-vocalid justify-content-center">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
        Start Recording
      </button>
    </div>

    <div id="samples-list"></div>
    <p class="api-status" id="api-status"></p>

    <!-- Shown after 4 samples are recorded and attendance is marked -->
    <div id="complete-registration" style="display:none;">
      <div class="success-box">
        <svg width="34" height="34" fill="none" stroke="#00e5a0" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" style="margin:0 auto 10px;display:block;"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div style="font-size:.95rem;font-weight:600;color:var(--accent-emerald);margin-bottom:4px;">Voice Profile Created</div>
        <div style="font-size:.78rem;color:var(--text-muted);">Your attendance has been marked. Your biometric identity is registered.</div>
      </div>
      <div class="text-center">
        <a href="index.php" class="btn-vocalid emerald justify-content-center">Complete Enrollment</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ============================================================
// Voice Recording Simulation + Backend Integration
// ============================================================

let voiceSamplesRecorded = 0;
const TOTAL_SAMPLES = 4;

// PHP passes student data into JS variables safely
// This is how you bridge PHP session data into JavaScript
const STUDENT_NAME = <?= json_encode($student_name) ?>;
const STUDENT_ROLL = <?= json_encode($student_roll) ?>;

function recordVoiceSample() {
  if (voiceSamplesRecorded >= TOTAL_SAMPLES) return;

  const btn = document.getElementById('record-btn');
  const wf  = document.getElementById('recording-waveform');
  const st  = document.getElementById('recording-status');
  const statusEl = document.getElementById('api-status');

  btn.disabled = true;
  btn.innerHTML = '<div class="rec-dot" style="width:7px;height:7px;border-radius:50%;background:#ff4d6d;animation:pulse 1s infinite;display:inline-block;"></div> Recording…';
  wf.style.display = 'flex';
  st.style.display = 'flex';
  statusEl.textContent = '';

  // Simulate 3-second recording
  setTimeout(() => {
    voiceSamplesRecorded++;

    // Update progress bar
    document.getElementById('sample-progress').style.width = (voiceSamplesRecorded / TOTAL_SAMPLES * 100) + '%';
    document.getElementById('sample-count').textContent = `${voiceSamplesRecorded} / ${TOTAL_SAMPLES} samples`;

    // Add sample item to list
    const li = document.getElementById('samples-list');
    const item = document.createElement('div');
    item.className = 'sample-item';
    item.innerHTML = `
      <div class="d-flex align-items-center gap-2">
        <svg width="13" height="13" fill="none" stroke="#00e5a0" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        <span style="font-size:.72rem;font-family:var(--font-mono);color:var(--accent-emerald);">S-0${voiceSamplesRecorded}</span>
        <span style="font-size:.82rem;color:var(--text-secondary);">Sample ${voiceSamplesRecorded} captured</span>
      </div>
      <span style="font-size:.7rem;font-family:var(--font-mono);color:var(--text-muted);">${(Math.random()*1.5+1.5).toFixed(1)}s</span>
    `;
    li.appendChild(item);

    wf.style.display = 'none';
    st.style.display = 'none';
    btn.disabled = false;

    if (voiceSamplesRecorded >= TOTAL_SAMPLES) {
      // All samples recorded — now send to backend to mark attendance
      btn.style.display = 'none';
      statusEl.textContent = '⏳ Saving attendance to database...';
      markAttendanceViaAPI();
    } else {
      btn.innerHTML = `<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg> Record Next Sample`;
    }
  }, 3000);
}

// ============================================================
// markAttendanceViaAPI()
// Sends detected student name to mark_attendance.php via fetch()
// The PHP file stores attendance in the MySQL database
// ============================================================
function markAttendanceViaAPI() {
  const confidence = Math.floor(Math.random() * 15) + 85; // Simulated: 85–100%

  // Use fetch() to POST data to mark_attendance.php
  fetch('mark_attendance.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'   // Sending JSON data
    },
    body: JSON.stringify({
      student_name: STUDENT_NAME,          // From PHP session via JS variable
      roll_no:      STUDENT_ROLL,
      confidence:   confidence
    })
  })
  .then(response => response.json())       // Parse JSON response from PHP
  .then(data => {
    const statusEl = document.getElementById('api-status');
    if (data.success) {
      statusEl.textContent = '✅ ' + data.message;
      // Show the success box after a short delay
      setTimeout(() => {
        document.getElementById('complete-registration').style.display = 'block';
        statusEl.textContent = '';
      }, 800);
    } else {
      statusEl.textContent = '⚠️ ' + (data.message || 'Could not save attendance.');
      // Still show completion UI even if DB failed
      setTimeout(() => {
        document.getElementById('complete-registration').style.display = 'block';
      }, 1500);
    }
  })
  .catch(err => {
    // Network error — show fallback
    console.error('Attendance API error:', err);
    document.getElementById('api-status').textContent = '⚠️ Network error. Attendance may not be saved.';
    setTimeout(() => {
      document.getElementById('complete-registration').style.display = 'block';
    }, 1500);
  });
}
</script>
</body>
</html>

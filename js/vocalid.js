// ============================================================
// vocalid.js — Frontend Utility Module
// NOTE: Most logic (auth, attendance, data) has moved to PHP/MySQL.
// This file now only handles lightweight UI helpers.
// ============================================================

const VocalID = {

  // ============================================================
  // showToast() — Show a temporary notification message
  // Usage: VocalID.showToast('Attendance marked!', 'success')
  // Types: 'success' | 'error' | 'info'
  // ============================================================
  showToast(message, type = 'info', duration = 3500) {
    // Remove any existing toast
    const existing = document.getElementById('vocalid-toast');
    if (existing) existing.remove();

    const colors = {
      success: { bg: 'rgba(0,229,160,.1)', border: 'rgba(0,229,160,.25)', color: '#00e5a0' },
      error:   { bg: 'rgba(255,77,109,.1)',border: 'rgba(255,77,109,.25)', color: '#ff4d6d' },
      info:    { bg: 'rgba(0,212,255,.1)', border: 'rgba(0,212,255,.25)',  color: '#00d4ff' },
    };
    const c = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.id = 'vocalid-toast';
    toast.style.cssText = `
      position: fixed; bottom: 28px; right: 28px; z-index: 9999;
      padding: 14px 20px; border-radius: 12px;
      background: ${c.bg}; border: 1px solid ${c.border}; color: ${c.color};
      font-size: .82rem; font-family: 'Trebuchet MS', sans-serif;
      box-shadow: 0 8px 32px rgba(0,0,0,.4);
      animation: fadeUp .35s ease forwards;
      max-width: 320px; line-height: 1.5;
      backdrop-filter: blur(12px);
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    // Auto-remove after duration
    setTimeout(() => {
      toast.style.animation = 'fadeOut .3s ease forwards';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  // ============================================================
  // formatTime() — Format a time string nicely
  // Input: "14:35:20" → Output: "2:35 PM"
  // ============================================================
  formatTime(timeStr) {
    if (!timeStr || timeStr === '—') return '—';
    const [h, m] = timeStr.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour = h % 12 || 12;
    return `${hour}:${String(m).padStart(2, '0')} ${ampm}`;
  },

  // ============================================================
  // formatDate() — Format a date string nicely
  // Input: "2024-11-25" → Output: "Nov 25, 2024"
  // ============================================================
  formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
  },

  // ============================================================
  // setButtonLoading() — Show loading state on a button
  // Usage: VocalID.setButtonLoading(btn, true, 'Saving...')
  // ============================================================
  setButtonLoading(btn, isLoading, loadingText = 'Loading...') {
    if (isLoading) {
      btn._originalHTML = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<span style="display:inline-flex;align-items:center;gap:8px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             style="animation:spin .8s linear infinite;">
          <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
        </svg>
        ${loadingText}
      </span>`;
    } else {
      btn.disabled = false;
      btn.innerHTML = btn._originalHTML || btn.innerHTML;
    }
  },

  // ============================================================
  // confirmAction() — Show a styled confirm dialog
  // Returns true/false (synchronous, uses native confirm for now)
  // ============================================================
  confirmAction(message) {
    return window.confirm(message);
  },

  // ============================================================
  // getGreeting() — Returns time-appropriate greeting
  // ============================================================
  getGreeting(name = '') {
    const hr = new Date().getHours();
    const greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
    return name ? `${greet}, ${name}.` : greet + '.';
  },

  // ============================================================
  // initDashboardClock() — Updates a live clock element
  // Usage: VocalID.initDashboardClock('#clock-el')
  // ============================================================
  initDashboardClock(selector) {
    const el = document.querySelector(selector);
    if (!el) return;
    const update = () => {
      el.textContent = new Date().toLocaleTimeString('en-IN', {
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      });
    };
    update();
    setInterval(update, 1000);
  }
};

// Add spin keyframe if not already in stylesheet
if (!document.getElementById('vocalid-spin-style')) {
  const style = document.createElement('style');
  style.id = 'vocalid-spin-style';
  style.textContent = `
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes fadeOut { from { opacity:1; transform:translateY(0); } to { opacity:0; transform:translateY(8px); } }
  `;
  document.head.appendChild(style);
}

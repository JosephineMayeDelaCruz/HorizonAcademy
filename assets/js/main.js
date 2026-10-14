

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// TOAST NOTIFICATIONS
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
window.showToast = function(msg, duration = 3000) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  
  // Set the message text and make the toast visible.
  toast.textContent = msg;
  toast.classList.add('show');
  
  // Hide the toast after the specified duration.
  setTimeout(() => toast.classList.remove('show'), duration);
};

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// TABLE SELECTION
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Allow users to click on table rows to select/deselect them for bulk actions.
document.querySelectorAll('.data-table tbody tr').forEach(row => {
  row.addEventListener('click', () => {
    const table = row.closest('table');
    // Deselect all other rows in this table.
    table.querySelectorAll('tbody tr').forEach(r => r.classList.remove('selected'));
    // Toggle the selected state on the clicked row.
    row.classList.toggle('selected');
  });
});

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// TABLE FILTERING / SEARCH
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
window.filterTable = function(input, tableId) {
  const searchValue = input.value.toLowerCase();
  const table = document.getElementById(tableId);
  
  if (!table) return;
  
  // Show rows that contain the search text, hide those that don't.
  table.querySelectorAll('tbody tr').forEach(row => {
    const rowText = row.textContent.toLowerCase();
    row.style.display = rowText.includes(searchValue) ? '' : 'none';
  });
};

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PASSWORD VISIBILITY TOGGLE
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
window.togglePw = function(id, btn) {
  const input = document.getElementById(id);
  if (!input) return;
  
  // Switch between password and text input types.
  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  // Update button icon to show current state.
  btn.textContent = isPassword ? '🙈' : '👁';
};

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// INITIALIZATION
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// When the page finishes loading, log a confirmation message to the browser console.
document.addEventListener('DOMContentLoaded', () => {
  console.log('✓ Horizon Academy Portal initialized');
});

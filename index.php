<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LOGIN PAGE - Entry point for all users
 * ═══════════════════════════════════════════════════════════
 * First, users select their role (Admin/Faculty/Student), then enter credentials.
 */

// Import authentication functions.
require_once __DIR__ . '/config/auth.php';

// 
// If the user is already logged in, immediately redirect them to their role-specific dashboard.
if (isLoggedIn()) {
    // Map each role to its dashboard URL.
    $role_map = [
        'admin'      => url('admin/dashboard.php'),
        'faculty'    => url('faculty/dashboard.php'),
        'student'    => url('student/dashboard.php'),
        'registrar'  => url('registrar/dashboard.php'),
        'cashier'    => url('cashier/dashboard.php'),
        'department' => url('department/dashboard.php'),
    ];
    // Redirect to the appropriate dashboard based on their role.
    header('Location: ' . ($role_map[$_SESSION['role']] ?? url('index.php')));
    exit;
}

// 
// Get any flash messages from the previous request to show errors.
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Horizon Academy - Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="bg-blobs">
    <span></span><span></span><span></span><span></span><span></span>
  </div>

  <div id="page-role" class="page auth-page active">
    <div class="auth-card">
      <div class="logo-row">
        <span class="logo-icon">🎓</span>
        <span class="logo-text">Horizon Academy</span>
      </div>
      <div class="logo-sub">School System</div>

      <div class="role-question">Who are you?</div>

      <div class="role-cards">
        <div class="role-card" onclick="chooseRole('admin')">
          <span style="font-size:2.8rem;"></span>
          <span>Administrator</span>
        </div>

        <div class="role-card" onclick="chooseRole('faculty')">
          <span style="font-size:2.8rem;"></span>
          <span>Faculty</span>
        </div>

        <div class="role-card" onclick="chooseRole('student')">
          <span style="font-size:2.8rem;"></span>
          <span>Student</span>
        </div>

        <div class="role-card" onclick="chooseRole('registrar')">
          <span style="font-size:2.8rem;"></span>
          <span>Registrar</span>
        </div>

        <div class="role-card" onclick="chooseRole('cashier')">
          <span style="font-size:2.8rem;"></span>
          <span>Cashier</span>
        </div>

        <div class="role-card" onclick="chooseRole('department')">
          <span style="font-size:2.8rem;"></span>
          <span>Department</span>
        </div>
      </div>
    </div>
  </div>

  <div id="page-login" class="page auth-page" style="display:none">
    <div class="auth-card">
      <div class="logo-row">
        <span class="logo-icon">🎓</span>
        <span class="logo-text">Horizon Academy</span>
      </div>

      <div class="login-title" id="login-title">Login</div>

      <?php if (!empty($flash['msg'])): ?>
        <div class="error-msg show"><?= htmlspecialchars($flash['msg']) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= url('auth/login.php') ?>" id="login-form" onsubmit="return validateLogin()">
        <input type="hidden" name="role" id="role-field" value="">

        <div class="input-group">
          <span class="input-icon">👤</span>
          <input type="text" name="username" id="username-field"
                 placeholder="Username" autocomplete="off" required autofocus>
        </div>

        <div class="input-group">
          <span class="input-icon">🔒</span>
          <input type="password" name="password" id="password-field"
                 placeholder="Password" required>
          <button type="button" class="eye-btn" onclick="togglePassword('password-field', this)">
            Show
          </button>
        </div>

        <button type="submit" class="btn-login">Login</button>
      </form>

      <!-- Forgot password link. -->
      <div class="forgot-pw" onclick="showMessage('Please contact the administrator to reset your password.')">
        Forgot Password?
      </div>

      <!-- Back to role selection link. -->
      <div class="back-link" onclick="backToRole()">
        <- Back to role selection
      </div>
    </div>
  </div>

  <!-- Toast notification container. -->
  <div class="toast" id="toast"></div>

  <!-- JavaScript code for login page interactions. -->
  <script>
    // Map role values to display titles for consistency.
    const loginTitles = {
      admin:      'Administrator Login',
      faculty:    'Faculty Login',
      student:    'Student Login',
      registrar:  'Registrar Login',
      cashier:    'Cashier Login',
      department: 'Department Login'
    };

    // 
    // Switch from role selection to login form when user chooses a role.
    function chooseRole(selectedRole) {
      // Hide role selection and show login form.
      document.getElementById('page-role').style.display = 'none';
      document.getElementById('page-login').style.display = 'flex';
      // Store the selected role in a hidden form field.
      document.getElementById('role-field').value = selectedRole;
      // Update the login title based on selected role.
      document.getElementById('login-title').textContent = loginTitles[selectedRole];

      // Change username placeholder text based on role (students use student numbers).
      const placeholderText = selectedRole === 'student'
        ? 'Student Number (e.g., 2403938)'
        : 'Username';
      document.getElementById('username-field').placeholder = placeholderText;
      // Focus on username field for quick typing.
      document.getElementById('username-field').focus();
    }

    // 
    // Go back to role selection screen and clear form.
    function backToRole() {
      // Hide login form and show role cards again.
      document.getElementById('page-login').style.display = 'none';
      document.getElementById('page-role').style.display = 'flex';
      // Clear all form inputs.
      document.getElementById('login-form').reset();
    }

    // 
    // Toggle password field between hidden and visible text.
    function togglePassword(fieldId, buttonElement) {
      const passwordInput = document.getElementById(fieldId);
      // Switch input type between password and text.
      const isHidden = passwordInput.type === 'password';
      passwordInput.type = isHidden ? 'text' : 'password';
      // Update button text to show current state.
      buttonElement.textContent = isHidden ? 'Hide' : 'Show';
    }

    // 
    // Check login form has all required fields filled before sending.
    function validateLogin() {
      const userValue = document.getElementById('username-field').value.trim();
      const passwordValue = document.getElementById('password-field').value.trim();
      // Show error if username or password is empty.
      if (!userValue || !passwordValue) {
        showMessage('Please enter both username and password');
        return false;
      }
      return true;
    }

    // 
    // Show a temporary notification message at the bottom of the screen.
    function showMessage(text) {
      const toastElement = document.getElementById('toast');
      // Set the message text.
      toastElement.textContent = text;
      // Make the notification visible.
      toastElement.classList.add('show');
      // Hide after 2.5 seconds.
      setTimeout(() => toastElement.classList.remove('show'), 2500);
    }

    // 
    // Allow pressing Enter key to submit login form when on login page.
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && document.getElementById('page-login').style.display === 'flex') {
        document.getElementById('login-form').requestSubmit();
      }
    });
  </script>
</body>
</html>

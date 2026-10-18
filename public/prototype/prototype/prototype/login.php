<?php
// One sign-in page for every account. After signing in, the server sends each person to their role's dashboard.
require __DIR__ . '/../api/config.php';

$user = current_user();
if ($user) redirect(dashboard_url($user)); // already signed in

$customerMode = ($_GET['audience'] ?? '') === 'customer';
$notices = [
    'expired' => 'Your session has ended. Please sign in again.',
    'signed_out' => 'You have signed out.',
    'idle' => 'You were signed out after ' . SESSION_IDLE_MINUTES . ' minutes without activity. Please sign in again.',
];
$notice = $notices[$_GET['notice'] ?? ''] ?? '';
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#B01D54">
  <title>Sign in · EL Glamorous Face and Body Clinic</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
  <style>
    /* Shared EL Glamorous Face and Body Clinic design tokens — keep in sync with admin-view/admin.php, staff-view/staff.php and customer-view/index.php. */
    :root {
      --deep-magenta: #B01D54;
      --brand-pink: #E23C86;
      --soft-blush: #FDF1F6;
      --dark-plum: #2A1420;
      --muted-plum: #765363;
      --line: #EFD9E3;
      --canvas: #FFFBFD;
      --green: #28664C;
      --green-bg: #EAF6EF;
      --red: #922B45;
      --red-bg: #FFF0F3;
      --shadow: 0 12px 36px rgba(94,16,52,.08);
      --radius: 18px;
    }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--canvas); color: var(--dark-plum); font: 14px/1.5 "DM Sans", Arial, sans-serif; -webkit-font-smoothing: antialiased; }
    button, input { font: inherit; }
    button { cursor: pointer; }
    :focus-visible { outline: 3px solid rgba(226,60,134,.45); outline-offset: 3px; }
    .login-card { width: min(380px,100%); padding: 24px 24px 22px; border: 1px solid var(--line); border-radius: var(--radius); background: white; box-shadow: var(--shadow); }
    .login-card img { width: 140px; height: 116px; object-fit: contain; display: block; margin: 0 auto 12px; }
    .eyebrow { margin: 0 0 5px; color: var(--brand-pink); text-transform: uppercase; letter-spacing: .15em; font-size: 9px; font-weight: 800; text-align: center; }
    h1 { margin: 0; color: var(--deep-magenta); font-size: clamp(25px,2.6vw,34px); letter-spacing: -.045em; line-height: 1.1; text-align: center; }
    .login-intro { margin: 6px 0 18px; color: var(--muted-plum); font-size: 12px; text-align: center; }
    .field { margin-bottom: 12px; }
    .field label { display: block; margin-bottom: 5px; color: #604956; font-size: 9px; font-weight: 800; }
    .field input { width: 100%; min-height: 37px; padding: 0 10px; border: 1px solid var(--line); border-radius: 10px; color: var(--dark-plum); background: white; font-size: 10px; }
    .field input:focus { border-color: var(--brand-pink); outline: none; box-shadow: 0 0 0 3px rgba(226,60,134,.12); }
    .message { display: none; margin: 0 0 12px; padding: 10px 12px; border-radius: 10px; font-size: 10px; }
    .message.show { display: block; }
    .message.good { color: var(--green); background: var(--green-bg); }
    .message.bad { color: var(--red); background: var(--red-bg); }
    .btn { width: 100%; min-height: 39px; margin-top: 4px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid transparent; border-radius: 99px; color: white; background: var(--deep-magenta); box-shadow: 0 7px 16px rgba(176,29,84,.16); font-size: 11px; font-weight: 800; transition: .18s ease; }
    .btn:hover { background: #8F1644; transform: translateY(-1px); }
    .btn:disabled { opacity: .7; cursor: wait; transform: none; }
    .back-link { display: block; margin-top: 14px; color: var(--deep-magenta); font-size: 10px; font-weight: 800; text-align: center; text-decoration: none; }
    .back-link:hover { color: var(--brand-pink); text-decoration: underline; }
    .auth-switch { display: block; margin: 14px auto 0; padding: 0; border: 0; color: var(--deep-magenta); background: none; font-size: 10px; font-weight: 800; text-align: center; text-decoration: underline; }
    @media (prefers-reduced-motion: reduce) { *,*::before,*::after { transition-duration: .01ms !important; } }
  </style>
</head>
<body>
  <form class="login-card" id="loginForm" data-auth-form="login">
    <img src="assets/el-glam-logo.png" alt="EL Glamorous Face and Body Clinic logo">
    <p class="eyebrow"><?= $customerMode ? 'EL Glamorous Face and Body Clinic' : 'EL Glamorous Face and Body Clinic team' ?></p>
    <h1><?= $customerMode ? 'Customer sign in' : 'Sign in' ?></h1>
    <p class="login-intro"><?= $customerMode ? 'Sign in to view the clinic website and request an appointment.' : 'Use your EL Glamorous Face and Body Clinic account. You’ll go straight to your workspace.' ?></p>
    <div class="field"><label for="loginEmail">Email</label><input id="loginEmail" name="email" type="email" autocomplete="username" required autofocus></div>
    <div class="field"><label for="loginPassword">Password</label><input id="loginPassword" name="password" type="password" autocomplete="current-password" required></div>
    <div class="message <?= $notice === $notices['signed_out'] ? 'good' : 'bad' ?><?= $notice ? ' show' : '' ?>" id="loginMessage" role="alert"><?= htmlspecialchars($notice) ?></div>
    <button class="btn" type="submit">Sign in</button>
    <?php if ($customerMode): ?>
      <button class="auth-switch" type="button" data-auth-view="register">New customer? Create an account</button>
    <?php else: ?>
      <a class="back-link" href="customer-view/index.php">← Customer sign in</a>
    <?php endif; ?>
  </form>
  <?php if ($customerMode): ?>
    <form class="login-card" id="registerForm" data-auth-form="register" hidden>
      <img src="assets/el-glam-logo.png" alt="EL Glamorous Face and Body Clinic logo">
      <p class="eyebrow">EL Glamorous Face and Body Clinic</p>
      <h1>Create account</h1>
      <p class="login-intro">Create a customer account to visit the clinic website.</p>
      <div class="field"><label for="registerName">Full name</label><input id="registerName" name="name" type="text" maxlength="120" autocomplete="name" required></div>
      <div class="field"><label for="registerEmail">Email</label><input id="registerEmail" name="email" type="email" maxlength="190" autocomplete="email" required></div>
      <div class="field"><label for="registerMobile">Mobile number (optional)</label><input id="registerMobile" name="mobile" type="tel" maxlength="20" autocomplete="tel"></div>
      <div class="field"><label for="registerPassword">Password (8–72 characters)</label><input id="registerPassword" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
      <div class="message" data-auth-message role="alert"></div>
      <button class="btn" type="submit">Create customer account</button>
      <button class="auth-switch" type="button" data-auth-view="login">Already have an account? Sign in</button>
    </form>
  <?php endif; ?>
  <script>
    const customerMode = <?= $customerMode ? 'true' : 'false' ?>;
    document.querySelectorAll('[data-auth-view]').forEach((control) => control.addEventListener('click', () => {
      const register = control.dataset.authView === 'register';
      document.getElementById('loginForm').hidden = register;
      const registerForm = document.getElementById('registerForm');
      if (registerForm) registerForm.hidden = !register;
      (register ? registerForm : document.getElementById('loginForm')).querySelector('input')?.focus();
    }));
    document.querySelectorAll('[data-auth-form]').forEach((form) => form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      const message = form.querySelector('[data-auth-message]') || document.getElementById('loginMessage');
      const data = Object.fromEntries(new FormData(form).entries());
      button.disabled = true;
      message.className = 'message';
      try {
        const action = form.dataset.authForm === 'register' ? 'register' : 'login';
        const response = await fetch('../api/auth.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ...data, action, ...(customerMode ? { audience: 'customer' } : {}) }),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.error || 'Sign-in failed. Please try again.');
        window.location.replace(result.redirect);
      } catch (err) {
        message.textContent = err.message === 'Failed to fetch' ? 'Can’t reach the clinic server. Check that XAMPP is running.' : err.message;
        message.className = 'message bad show';
        button.disabled = false;
      }
    }));
  </script>
</body>
</html>

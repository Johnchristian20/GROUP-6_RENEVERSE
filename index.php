<?php
session_start();
if (!empty($_SESSION['user_id'])) {
  header('Location: dashboard.php');
  exit;
}
$error = $_SESSION['login_error'] ?? '';
$success = $_SESSION['signup_success'] ?? '';
unset($_SESSION['login_error'], $_SESSION['signup_success']);
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | The Reading Room</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="login-page auth-login-page">
  <div class="login-layout">
    <section class="login-art">
      <a class="brand brand-light" href="index.php"><span class="brand-mark"><svg viewBox="0 0 24 24">
            <path
              d="M3 5.5c3.5-1.5 6.5-.8 9 1.3v13c-2.5-2.1-5.5-2.8-9-1.3zM21 5.5c-3.5-1.5-6.5-.8-9 1.3v13c2.5-2.1 5.5-2.8 9-1.3z" />
          </svg></span><span><strong>The Reading Room</strong><small>Library Management</small></span></a>
      <div class="art-copy">
        <div class="eyebrow light-eyebrow">A WORLD BETWEEN COVERS</div>
        <h1>Make room<br>for <em>curiosity.</em></h1>
        <p>Organize your collection, care for your readers, and keep knowledge within reach.</p>
      </div>
      <div class="art-bottom"><span class="decor-line"></span><span>Discover. Learn. Return.</span></div>
      <div class="orb orb-one"></div>
      <div class="orb orb-two"></div>
    </section>
    <section class="login-panel">
      <div class="login-card">
        <div class="login-kicker">WELCOME BACK</div>
        <h2>Sign in to your library</h2>
        <p class="muted">Enter your account details to continue.</p>
        <?php if ($error): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form action="login_process.php" method="post">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" autocomplete="username" placeholder="Enter your username"
            required maxlength="50">
          <label for="password">Password</label>
          <div class="password-wrap"><input id="password" name="password" type="password"
              autocomplete="current-password" placeholder="Enter your password" required><button type="button"
              class="show-password" id="togglePassword" aria-label="Show password">Show</button></div>
          <button class="button button-primary button-full" type="submit">Sign in <span
              aria-hidden="true">→</span></button>
        </form>
        <p class="login-help">New to the library? <a class="text-link" href="signup.php">Create an
            account</a></p>
      </div>
    </section>
  </div>
  <script src="assets/js/app.js"></script>
</body>

</html>
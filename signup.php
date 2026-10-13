<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
require __DIR__ . '/database.php';
$error = '';
$values = ['first_name' => '', 'last_name' => '', 'email' => '', 'username' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value)
        $values[$key] = trim($_POST[$key] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (in_array('', $values, true) || $password === '' || $confirm === '')
        $error = 'Please complete all fields.';
    elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL))
        $error = 'Enter a valid email address.';
    elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $values['username']))
        $error = 'Username must be 3–50 characters and use letters, numbers, dots, underscores, or hyphens.';
    elseif (strlen($password) < 6)
        $error = 'Password must be at least 6 characters.';
    elseif ($password !== $confirm)
        $error = 'Passwords do not match.';
    else {
        $check = mysqli_prepare($conn, 'SELECT user_id FROM users WHERE username=? OR email=? LIMIT 1');
        mysqli_stmt_bind_param($check, 'ss', $values['username'], $values['email']);
        mysqli_stmt_execute($check);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);
        if ($exists)
            $error = 'That username or email is already registered.';
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (first_name,last_name,email,username,password,role) VALUES (?,?,?,?,?,'User')");
            mysqli_stmt_bind_param($stmt, 'sssss', $values['first_name'], $values['last_name'], $values['email'], $values['username'], $hash);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $_SESSION['signup_success'] = 'Account created. You can now sign in.';
                header('Location: index.php');
                exit;
            }
            $error = 'Unable to create the account. Please try again.';
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account | The Reading Room</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="login-page auth-signup-page">
    <div class="login-layout">
        <section class="login-art"><a class="brand brand-light" href="index.php"><span class="brand-mark"><svg
                        viewBox="0 0 24 24">
                        <path
                            d="M3 5.5c3.5-1.5 6.5-.8 9 1.3v13c-2.5-2.1-5.5-2.8-9-1.3zM21 5.5c-3.5-1.5-6.5-.8-9 1.3v13c2.5-2.1 5.5-2.8 9-1.3z" />
                    </svg></span><span><strong>The Reading Room</strong><small>Library Management</small></span></a>
            <div class="art-copy">
                <div class="eyebrow light-eyebrow">YOUR NEXT CHAPTER STARTS HERE</div>
                <h1>Every reader<br>belongs <em>here.</em></h1>
                <p>Create your library account to explore the collection and keep track of your reading journey.</p>
            </div>
            <div class="art-bottom"><span class="decor-line"></span><span>Discover. Learn. Return.</span></div>
            <div class="orb orb-one"></div>
            <div class="orb orb-two"></div>
        </section>
        <section class="login-panel">
            <div class="login-card">
                <div class="login-kicker">JOIN THE LIBRARY</div>
                <h2>Create an account</h2>
                <p class="muted">Your account will be saved directly to the library database.</p><?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <form method="post">
                    <div class="form-grid signup-grid">
                        <div class="form-field"><label for="first_name">First name</label><input id="first_name"
                                name="first_name" maxlength="50" required
                                value="<?= htmlspecialchars($values['first_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="form-field"><label for="last_name">Last name</label><input id="last_name"
                                name="last_name" maxlength="50" required
                                value="<?= htmlspecialchars($values['last_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="form-field signup-full"><label for="email">Email address</label><input id="email"
                                name="email" type="email" maxlength="100" required
                                value="<?= htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="form-field signup-full"><label for="username">Username</label><input id="username"
                                name="username" maxlength="50" autocomplete="username" required
                                value="<?= htmlspecialchars($values['username'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="form-field signup-full"><label for="password">Password (at least 6
                                characters)</label><input id="password" name="password" type="password" minlength="6"
                                autocomplete="new-password" required></div>
                        <div class="form-field signup-full"><label for="confirm_password">Confirm password</label><input
                                id="confirm_password" name="confirm_password" type="password" minlength="6"
                                autocomplete="new-password" required></div>
                    </div><button class="button button-primary button-full" type="submit">Create account
                        <span>→</span></button>
                </form>
                <p class="login-help">Already have an account? <a class="text-link" href="index.php">Sign in</a></p>
                <div class="login-foot">New accounts are assigned the standard User role.</div>
            </div>
        </section>
    </div>
</body>

</html>
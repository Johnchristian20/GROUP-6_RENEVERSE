<?php
session_start();
require __DIR__ . '/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Enter both your username and password.';
    header('Location: index.php');
    exit;
}
$stmt = mysqli_prepare($conn, 'SELECT user_id, first_name, last_name, username, password, role FROM users WHERE username = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    header('Location: dashboard.php');
    exit;
}
$_SESSION['login_error'] = 'Incorrect username or password.';
header('Location: index.php');
exit;

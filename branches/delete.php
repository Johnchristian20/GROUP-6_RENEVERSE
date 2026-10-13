<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid form token. Reload the page and try again.');
}
$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    $_SESSION['flash'] = 'Choose a valid branch.';
    header('Location: index.php');
    exit;
}
$stmt = mysqli_prepare($conn, 'DELETE FROM branches WHERE branch_id=?');
mysqli_stmt_bind_param($stmt, 'i', $id);
if (mysqli_stmt_execute($stmt)) {
    $_SESSION['flash'] = mysqli_stmt_affected_rows($stmt) ? 'Branch deleted.' : 'Branch was not found.';
} else {
    error_log('Branch delete failed: ' . mysqli_stmt_error($stmt));
    $_SESSION['flash'] = mysqli_stmt_errno($stmt) === 1451 ? 'This branch is linked to books, members, or loans and cannot be deleted.' : 'Unable to delete the branch.';
}
mysqli_stmt_close($stmt);
header('Location: index.php');
exit;
<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    $_SESSION['flash'] = 'Invalid member ID.';
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'DELETE FROM members WHERE member_id = ?'
);

mysqli_stmt_bind_param($stmt, 'i', $id);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        $_SESSION['flash'] = 'Member deleted successfully.';
    } else {
        $_SESSION['flash'] = 'Member record was not found.';
    }
} else {
    $_SESSION['flash'] =
        'Unable to delete this member because related records may exist.';
}

mysqli_stmt_close($stmt);

header('Location: index.php');
exit;

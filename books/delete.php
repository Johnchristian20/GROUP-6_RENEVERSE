<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php');
  exit;
}
$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
  $stmt = mysqli_prepare($conn, 'DELETE FROM books WHERE book_id=?');
  mysqli_stmt_bind_param($stmt, 'i', $id);
  if (mysqli_stmt_execute($stmt)) {
    $_SESSION['flash'] = mysqli_stmt_affected_rows($stmt) > 0 ? 'Book record deleted.' : 'Book record was not found.';
  } else {
    $_SESSION['flash'] = 'This book cannot be deleted because it may be linked to a loan record.';
  }
  mysqli_stmt_close($stmt);
}
header('Location: index.php');
exit;

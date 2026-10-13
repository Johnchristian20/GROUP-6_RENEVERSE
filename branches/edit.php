<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';
require __DIR__ . '/branch_helpers.php';
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id < 1) {
  header('Location: index.php');
  exit;
}
$pageTitle = 'Edit branch';
$basePath = '../';
$error = '';
$isEdit = true;
if (empty($_SESSION['csrf_token']))
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$stmt = mysqli_prepare($conn, 'SELECT * FROM branches WHERE branch_id=?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$branch = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$branch) {
  http_response_code(404);
  exit('Branch not found.');
}
$values = branch_input_values($branch);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid form token. Reload the page and try again.');
  }
  $values = branch_input_values($_POST);
  $error = branch_validate($values);
  if ($error === '') {
    $sql = 'UPDATE branches SET branch_code=?,branch_name=?,street_address=?,city=?,state_province=?,postal_code=?,contact_phone=?,contact_email=?,opening_time=?,closing_time=?,capacity=?,established_date=? WHERE branch_id=?';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt)
      $error = 'Unable to prepare the branch update.';
    else {
      $capacity = (int) $values['capacity'];
      mysqli_stmt_bind_param($stmt, 'ssssssssssisi', $values['branch_code'], $values['branch_name'], $values['street_address'], $values['city'], $values['state_province'], $values['postal_code'], $values['contact_phone'], $values['contact_email'], $values['opening_time'], $values['closing_time'], $capacity, $values['established_date'], $id);
      if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        $_SESSION['flash'] = 'Branch updated successfully.';
        header('Location: index.php');
        exit;
      }
      $error = mysqli_stmt_errno($stmt) === 1062 ? 'That branch code already exists.' : 'Unable to update the branch.';
      error_log('Branch update failed: ' . mysqli_stmt_error($stmt));
      mysqli_stmt_close($stmt);
    }
  }
}
require __DIR__ . '/../includes/header.php';
branch_render_form($values, $error, true);
require __DIR__ . '/../includes/footer.php';
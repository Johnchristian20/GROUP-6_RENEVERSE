<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';
require __DIR__ . '/branch_helpers.php';
$pageTitle = 'Add branch';
$basePath = '../';
$isEdit = false;
$error = '';
if (empty($_SESSION['csrf_token']))
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$values = branch_input_values([]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid form token. Reload the page and try again.');
    }
    $values = branch_input_values($_POST);
    $error = branch_validate($values);
    if ($error === '') {
        $sql = 'INSERT INTO branches (branch_code,branch_name,street_address,city,state_province,postal_code,contact_phone,contact_email,opening_time,closing_time,capacity,established_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt)
            $error = 'Unable to prepare the branch record. Check the database.';
        else {
            $capacity = (int) $values['capacity'];
            mysqli_stmt_bind_param($stmt, 'ssssssssssis', $values['branch_code'], $values['branch_name'], $values['street_address'], $values['city'], $values['state_province'], $values['postal_code'], $values['contact_phone'], $values['contact_email'], $values['opening_time'], $values['closing_time'], $capacity, $values['established_date']);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $_SESSION['flash'] = 'Branch added successfully.';
                header('Location: index.php');
                exit;
            }
            $error = mysqli_stmt_errno($stmt) === 1062 ? 'That branch code already exists.' : 'Unable to add the branch. Check that the database fields are available.';
            error_log('Branch insert failed: ' . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }
    }
}
require __DIR__ . '/../includes/header.php';
branch_render_form($values, $error, false);
require __DIR__ . '/../includes/footer.php';
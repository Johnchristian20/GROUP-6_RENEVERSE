<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';

$pageTitle = 'Edit member';
$basePath = '../';
$isEdit = true;
$error = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

if (!$id || $id < 1) {
    $_SESSION['flash'] = 'Invalid member ID.';
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM members WHERE member_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$member) {
    $_SESSION['flash'] = 'Member record was not found.';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'membership_number',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'home_branch_id',
        'membership_type',
        'account_status',
        'max_borrow_limit',
        'registration_date',
        'expiration_date'
    ];

    foreach ($fields as $field) {
        $member[$field] = trim($_POST[$field] ?? '');
    }

    $types = ['Student', 'Faculty', 'Regular', 'Senior', 'Corporate'];
    $statuses = ['Active', 'Suspended', 'Expired', 'Pending'];

    if (in_array('', array_intersect_key($member, array_flip($fields)), true)) {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (
        !ctype_digit($member['home_branch_id']) ||
        (int) $member['home_branch_id'] < 1
    ) {
        $error = 'Choose a valid home branch.';
    } elseif (!in_array($member['membership_type'], $types, true)) {
        $error = 'Choose a valid membership type.';
    } elseif (!in_array($member['account_status'], $statuses, true)) {
        $error = 'Choose a valid account status.';
    } elseif (
        !ctype_digit($member['max_borrow_limit']) ||
        (int) $member['max_borrow_limit'] < 1
    ) {
        $error = 'The borrowing limit must be at least 1.';
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $member['registration_date']) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $member['expiration_date']) ||
        !checkdate(
            (int) substr($member['registration_date'], 5, 2),
            (int) substr($member['registration_date'], 8, 2),
            (int) substr($member['registration_date'], 0, 4)
        ) ||
        !checkdate(
            (int) substr($member['expiration_date'], 5, 2),
            (int) substr($member['expiration_date'], 8, 2),
            (int) substr($member['expiration_date'], 0, 4)
        )
    ) {
        $error = 'Enter valid registration and expiration dates.';
    } elseif ($member['expiration_date'] < $member['registration_date']) {
        $error = 'Expiration date cannot be earlier than registration date.';
    } else {
        $branchId = (int) $member['home_branch_id'];

        $branchStmt = mysqli_prepare(
            $conn,
            'SELECT branch_id FROM branches WHERE branch_id = ?'
        );
        mysqli_stmt_bind_param($branchStmt, 'i', $branchId);
        mysqli_stmt_execute($branchStmt);
        $branchResult = mysqli_stmt_get_result($branchStmt);
        $branchExists = mysqli_num_rows($branchResult) > 0;
        mysqli_stmt_close($branchStmt);

        if (!$branchExists) {
            $error = 'The selected branch does not exist.';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE members SET
                    membership_number = ?,
                    first_name = ?,
                    last_name = ?,
                    email = ?,
                    phone_number = ?,
                    home_branch_id = ?,
                    membership_type = ?,
                    account_status = ?,
                    max_borrow_limit = ?,
                    registration_date = ?,
                    expiration_date = ?
                 WHERE member_id = ?'
            );

            mysqli_stmt_bind_param(
                $stmt,
                'sssssississi',
                $member['membership_number'],
                $member['first_name'],
                $member['last_name'],
                $member['email'],
                $member['phone_number'],
                $branchId,
                $member['membership_type'],
                $member['account_status'],
                $member['max_borrow_limit'],
                $member['registration_date'],
                $member['expiration_date'],
                $id
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $_SESSION['flash'] = 'Member details updated successfully.';
                header('Location: index.php');
                exit;
            }

            $error = mysqli_errno($conn) === 1062
                ? 'Membership number or email already belongs to another member.'
                : 'Could not update the member. Please check the information.';

            mysqli_stmt_close($stmt);
        }
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/form.php';
require __DIR__ . '/../includes/footer.php';

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
    $_SESSION['flash'] = 'Invalid loan ID.';
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT book_id, loan_status FROM book_loans WHERE loan_id=?'
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$loan) {
    $_SESSION['flash'] = 'Loan record was not found.';
    header('Location: index.php');
    exit;
}

mysqli_begin_transaction($conn);

$stmt = mysqli_prepare($conn, 'DELETE FROM book_loans WHERE loan_id=?');
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($ok && in_array($loan['loan_status'], ['Ongoing', 'Overdue'], true)) {
    $bookId = (int) $loan['book_id'];

    $active = mysqli_prepare($conn, "
        SELECT COUNT(*) AS n
        FROM book_loans
        WHERE book_id=?
        AND loan_status IN ('Ongoing','Overdue')
    ");
    mysqli_stmt_bind_param($active, 'i', $bookId);
    mysqli_stmt_execute($active);
    $count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($active))['n'];
    mysqli_stmt_close($active);

    if ($count === 0) {
        $available = 'Available';
        $up = mysqli_prepare(
            $conn,
            "UPDATE books SET status=? WHERE book_id=? AND status='Checked Out'"
        );
        mysqli_stmt_bind_param($up, 'si', $available, $bookId);
        $ok = mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
    }
}

if ($ok) {
    mysqli_commit($conn);
    $_SESSION['flash'] = 'Loan record deleted successfully.';
} else {
    mysqli_rollback($conn);
    $_SESSION['flash'] = 'Could not delete the loan record.';
}

header('Location: index.php');
exit;
?>

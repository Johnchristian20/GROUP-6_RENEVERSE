<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

if (!$id || $id < 1) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Edit loan';
$basePath = '../';
$isEdit = true;
$error = '';

function updateBookStatus(mysqli $conn, int $bookId): void
{
    // Lost takes priority over active loans.
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            SUM(CASE WHEN loan_status IN ('Ongoing', 'Overdue')
                THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN loan_status = 'Lost'
                THEN 1 ELSE 0 END) AS lost_count
         FROM book_loans
         WHERE book_id = ?"
    );

    if (!$stmt) {
        throw new RuntimeException('Could not prepare book status update.');
    }

    mysqli_stmt_bind_param($stmt, 'i', $bookId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ((int) ($result['lost_count'] ?? 0) > 0) {
        $status = 'Lost';
    } elseif ((int) ($result['active_count'] ?? 0) > 0) {
        $status = 'Checked Out';
    } else {
        $status = 'Available';
    }

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE books SET status = ? WHERE book_id = ?'
    );

    if (!$stmt) {
        throw new RuntimeException('Could not prepare book status update.');
    }

    mysqli_stmt_bind_param($stmt, 'si', $status, $bookId);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Could not update the book status.');
    }

    mysqli_stmt_close($stmt);
}

// Load existing loan.
$stmt = mysqli_prepare(
    $conn,
    'SELECT * FROM book_loans WHERE loan_id = ?'
);

if (!$stmt) {
    exit('Could not load the loan record.');
}

mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);

$loan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$loan) {
    http_response_code(404);
    exit('Loan record not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'loan_reference',
        'book_id',
        'member_id',
        'checkout_branch_id',
        'checkout_date',
        'due_date',
        'return_date',
        'loan_status',
        'late_fine_amount',
        'renewal_count',
        'recorded_by_staff'
    ];

    foreach ($fields as $field) {
        $loan[$field] = trim($_POST[$field] ?? '');
    }

    $bookId = filter_var($loan['book_id'], FILTER_VALIDATE_INT);
    $memberId = filter_var($loan['member_id'], FILTER_VALIDATE_INT);
    $branchId = filter_var(
        $loan['checkout_branch_id'],
        FILTER_VALIDATE_INT
    );

    $checkout = str_replace('T', ' ', $loan['checkout_date']);
    $due = str_replace('T', ' ', $loan['due_date']);

    $return = $loan['return_date'] !== ''
        ? str_replace('T', ' ', $loan['return_date'])
        : null;

    $allowedStatuses = [
        'Ongoing',
        'Returned',
        'Overdue',
        'Lost'
    ];

    $oldBookId = (int) $loan['book_id'];

    if (
        !$bookId || !$memberId || !$branchId ||
        $loan['loan_reference'] === '' ||
        $loan['checkout_date'] === '' ||
        $loan['due_date'] === '' ||
        $loan['recorded_by_staff'] === ''
    ) {
        $error = 'Please complete all required fields.';
    } elseif (
        !in_array($loan['loan_status'], $allowedStatuses, true)
    ) {
        $error = 'Choose a valid loan status.';
    } elseif (
        !is_numeric($loan['late_fine_amount']) ||
        (float) $loan['late_fine_amount'] < 0 ||
        !ctype_digit($loan['renewal_count']) ||
        (int) $loan['renewal_count'] < 0
    ) {
        $error = 'Fine and renewal count must be zero or greater.';
    } elseif (
        strtotime($checkout) === false ||
        strtotime($due) === false ||
        ($return !== null && strtotime($return) === false)
    ) {
        $error = 'Enter valid checkout, due, and return dates.';
    } elseif (
        strtotime($due) < strtotime($checkout) ||
        ($return !== null && strtotime($return) < strtotime($checkout))
    ) {
        $error = 'Due and return dates cannot be earlier than checkout.';
    } elseif (
        ($loan['loan_status'] === 'Returned') !== ($return !== null)
    ) {
        $error = 'Enter a return date for Returned status only.';
    } else {
        // Check the selected book.
        $stmt = mysqli_prepare(
            $conn,
            'SELECT status FROM books WHERE book_id = ?'
        );

        mysqli_stmt_bind_param($stmt, 'i', $bookId);
        mysqli_stmt_execute($stmt);
        $bookRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        // Only Active members can borrow.
        $stmt = mysqli_prepare(
            $conn,
            'SELECT account_status
             FROM members
             WHERE member_id = ?'
        );

        mysqli_stmt_bind_param($stmt, 'i', $memberId);
        mysqli_stmt_execute($stmt);
        $memberRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        // Check the selected branch.
        $stmt = mysqli_prepare(
            $conn,
            'SELECT branch_id FROM branches WHERE branch_id = ?'
        );

        mysqli_stmt_bind_param($stmt, 'i', $branchId);
        mysqli_stmt_execute($stmt);
        $branchRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$bookRow || !$memberRow || !$branchRow) {
            $error = 'Choose an existing book, member, and branch.';
        } elseif (
            $bookId !== $oldBookId &&
            $bookRow['status'] !== 'Available'
        ) {
            $error = 'The new book must be available for checkout.';
        } elseif ($memberRow['account_status'] !== 'Active') {
            $error = 'Only members with Active accounts can borrow books.';
        } else {
            $fine = (float) $loan['late_fine_amount'];
            $renewals = (int) $loan['renewal_count'];

            mysqli_begin_transaction($conn);

            try {
                // Update the loan record in the database.
                $sql = "UPDATE book_loans
                        SET loan_reference = ?,
                            book_id = ?,
                            member_id = ?,
                            checkout_branch_id = ?,
                            checkout_date = ?,
                            due_date = ?,
                            return_date = ?,
                            loan_status = ?,
                            late_fine_amount = ?,
                            renewal_count = ?,
                            recorded_by_staff = ?
                        WHERE loan_id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                if (!$stmt) {
                    throw new RuntimeException(
                        'Could not prepare the loan update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    'siiissssdisi',
                    $loan['loan_reference'],
                    $bookId,
                    $memberId,
                    $branchId,
                    $checkout,
                    $due,
                    $return,
                    $loan['loan_status'],
                    $fine,
                    $renewals,
                    $loan['recorded_by_staff'],
                    $id
                );

                if (!mysqli_stmt_execute($stmt)) {
                    $dbError = mysqli_stmt_error($stmt);
                    $dbErrno = mysqli_stmt_errno($stmt);
                    mysqli_stmt_close($stmt);

                    if ($dbErrno === 1062) {
                        throw new RuntimeException(
                            'That loan reference already exists.'
                        );
                    }

                    error_log('Loan update failed: ' . $dbError);

                    throw new RuntimeException(
                        'Could not save the loan changes.'
                    );
                }

                mysqli_stmt_close($stmt);

                // If the book changed, refresh the old book's status.
                if ($oldBookId !== $bookId) {
                    updateBookStatus($conn, $oldBookId);
                }

                // Refresh the selected book based on saved loan records.
                updateBookStatus($conn, $bookId);

                // Save both table updates together.
                mysqli_commit($conn);

                $_SESSION['flash'] =
                    'Loan record and book status updated successfully.';

                header('Location: index.php');
                exit;
            } catch (Throwable $e) {
                mysqli_rollback($conn);

                error_log('Loan edit error: ' . $e->getMessage());

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'An error occurred while saving the changes.';
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/form.php';
require __DIR__ . '/../includes/footer.php';
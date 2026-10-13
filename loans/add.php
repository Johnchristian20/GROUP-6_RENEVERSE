<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';

$pageTitle = 'Record a loan';
$basePath = '../';
$isEdit = false;
$error = '';

$loan = [
    'loan_reference' => 'LN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
    'book_id' => '',
    'member_id' => '',
    'checkout_branch_id' => '',
    'checkout_date' => date('Y-m-d\TH:i'),
    'due_date' => date('Y-m-d\TH:i', strtotime('+14 days')),
    'return_date' => '',
    'loan_status' => 'Ongoing',
    'late_fine_amount' => '0.00',
    'renewal_count' => '0',
    'recorded_by_staff' => trim(
        ($_SESSION['first_name'] ?? '') . ' ' .
        ($_SESSION['last_name'] ?? '')
    )
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($loan as $key => $value) {
        $loan[$key] = trim($_POST[$key] ?? $value);
    }

    $loan['return_date'] = trim($_POST['return_date'] ?? '');

    $bookId = filter_var($loan['book_id'], FILTER_VALIDATE_INT);
    $memberId = filter_var($loan['member_id'], FILTER_VALIDATE_INT);
    $branchId = filter_var($loan['checkout_branch_id'], FILTER_VALIDATE_INT);

    $checkout = str_replace('T', ' ', $loan['checkout_date']);
    $due = str_replace('T', ' ', $loan['due_date']);
    $return = $loan['return_date'] !== ''
        ? str_replace('T', ' ', $loan['return_date'])
        : null;

    $allowedStatuses = ['Ongoing', 'Returned', 'Overdue', 'Lost'];

    if (
        !$bookId || !$memberId || !$branchId ||
        $loan['loan_reference'] === '' ||
        $loan['checkout_date'] === '' ||
        $loan['due_date'] === '' ||
        $loan['recorded_by_staff'] === ''
    ) {
        $error = 'Please complete all required fields.';
    } elseif (!in_array($loan['loan_status'], $allowedStatuses, true)) {
        $error = 'Choose a valid loan status.';
    } elseif (
        !is_numeric($loan['late_fine_amount']) ||
        (float) $loan['late_fine_amount'] < 0 ||
        !ctype_digit($loan['renewal_count']) ||
        (int) $loan['renewal_count'] < 0
    ) {
        $error = 'Fine and renewal count must be zero or greater.';
    } elseif (
        strtotime($due) < strtotime($checkout) ||
        ($return !== null && strtotime($return) < strtotime($checkout))
    ) {
        $error = 'Due and return dates cannot be earlier than the checkout date.';
    } elseif (
        ($loan['loan_status'] === 'Returned') !== ($return !== null)
    ) {
        $error = 'Enter a return date for a returned loan, and leave it blank for other statuses.';
    } else {
        // Check whether the selected book exists and is available.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT status FROM books WHERE book_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $bookId);
        mysqli_stmt_execute($stmt);
        $bookResult = mysqli_stmt_get_result($stmt);
        $bookRow = mysqli_fetch_assoc($bookResult);
        mysqli_stmt_close($stmt);

        // Accept members whose SQL account status is Active.
        // Expiration date is not used as an eligibility restriction here.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT member_id, account_status
             FROM members
             WHERE member_id = ?
               AND account_status = 'Active'
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 'i', $memberId);
        mysqli_stmt_execute($stmt);
        $memberResult = mysqli_stmt_get_result($stmt);
        $memberRow = mysqli_fetch_assoc($memberResult);
        mysqli_stmt_close($stmt);

        // Check whether the selected branch exists.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT branch_id FROM branches WHERE branch_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $branchId);
        mysqli_stmt_execute($stmt);
        $branchResult = mysqli_stmt_get_result($stmt);
        $branchExists = (bool) mysqli_fetch_assoc($branchResult);
        mysqli_stmt_close($stmt);

        if (!$bookRow || !$memberRow || !$branchExists) {
            $error = 'Choose an existing book, active member, and branch.';
        } elseif ($bookRow['status'] !== 'Available') {
            $error = 'This book is not available for checkout.';
        } else {
            mysqli_begin_transaction($conn);

            try {
                // Save the loan record.
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO book_loans
                    (
                        loan_reference,
                        book_id,
                        member_id,
                        checkout_branch_id,
                        checkout_date,
                        due_date,
                        return_date,
                        loan_status,
                        late_fine_amount,
                        renewal_count,
                        recorded_by_staff
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $fine = (float) $loan['late_fine_amount'];
                $renewals = (int) $loan['renewal_count'];

                mysqli_stmt_bind_param(
                    $stmt,
                    'siiissssdis',
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
                    $loan['recorded_by_staff']
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Unable to save the loan record.');
                }

                mysqli_stmt_close($stmt);

                // Update the book's availability.
                if ($loan['loan_status'] === 'Lost') {
                    $bookStatus = 'Lost';
                } elseif ($loan['loan_status'] === 'Returned') {
                    $bookStatus = 'Available';
                } else {
                    $bookStatus = 'Checked Out';
                }

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE books SET status = ? WHERE book_id = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    'si',
                    $bookStatus,
                    $bookId
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Unable to update book status.');
                }

                mysqli_stmt_close($stmt);
                mysqli_commit($conn);

                $_SESSION['flash'] = 'Loan record created successfully.';
                header('Location: index.php');
                exit;

            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $error = 'Could not save the loan. Check the loan reference and selected records.';
            }
        }
    }
}

// Load dropdown data directly from SQL.
$booksResult = mysqli_query(
    $conn,
    "SELECT book_id, title, status
     FROM books
     WHERE status = 'Available'
     ORDER BY title ASC"
);

$membersResult = mysqli_query(
    $conn,
    "SELECT member_id, membership_number, first_name, last_name,
            account_status
     FROM members
     WHERE account_status = 'Active'
     ORDER BY first_name ASC, last_name ASC"
);

$branchesResult = mysqli_query(
    $conn,
    "SELECT branch_id, branch_name
     FROM branches
     ORDER BY branch_name ASC"
);

if (!$booksResult || !$membersResult || !$branchesResult) {
    die('Unable to load loan form options from the database.');
}

$books = [];
while ($row = mysqli_fetch_assoc($booksResult)) {
    $books[] = $row;
}

$members = [];
while ($row = mysqli_fetch_assoc($membersResult)) {
    $members[] = $row;
}

$branches = [];
while ($row = mysqli_fetch_assoc($branchesResult)) {
    $branches[] = $row;
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/form.php';
require __DIR__ . '/../includes/footer.php';
?>

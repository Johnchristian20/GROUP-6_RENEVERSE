<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';

$pageTitle = 'Book loans';
$basePath = '../';

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$allowedStatuses = ['Ongoing', 'Returned', 'Overdue', 'Lost'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, [10, 20, 30, 50], true)) {
    $perPage = 10;
}

$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = '(bl.loan_reference LIKE ?
        OR b.title LIKE ?
        OR m.membership_number LIKE ?
        OR m.first_name LIKE ?
        OR m.last_name LIKE ?)';

    $like = "%$q%";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

if ($status !== '') {
    $where[] = 'bl.loan_status = ?';
    $params[] = $status;
    $types .= 's';
}

if (
    $dateFrom !== '' &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) &&
    checkdate((int) substr($dateFrom, 5, 2), (int) substr($dateFrom, 8, 2), (int) substr($dateFrom, 0, 4))
) {
    $where[] = 'DATE(bl.checkout_date) >= ?';
    $params[] = $dateFrom;
    $types .= 's';
} else {
    $dateFrom = '';
}

if (
    $dateTo !== '' &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) &&
    checkdate((int) substr($dateTo, 5, 2), (int) substr($dateTo, 8, 2), (int) substr($dateTo, 0, 4))
) {
    $where[] = 'DATE(bl.checkout_date) <= ?';
    $params[] = $dateTo;
    $types .= 's';
} else {
    $dateTo = '';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countSql = "
    SELECT COUNT(*) AS total
    FROM book_loans bl
    INNER JOIN books b ON bl.book_id = b.book_id
    INNER JOIN members m ON bl.member_id = m.member_id
    INNER JOIN branches br ON bl.checkout_branch_id = br.branch_id
    $whereSql
";

$countStmt = mysqli_prepare($conn, $countSql);
if (!$countStmt) {
    exit('Unable to prepare the loan count query.');
}

if ($types !== '') {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}

mysqli_stmt_execute($countStmt);
$totalRows = (int) mysqli_fetch_assoc(
    mysqli_stmt_get_result($countStmt)
)['total'];
mysqli_stmt_close($countStmt);

$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT
        bl.loan_id,
        bl.loan_reference,
        bl.checkout_date,
        bl.due_date,
        bl.return_date,
        bl.loan_status,
        bl.late_fine_amount,
        b.title,
        m.membership_number,
        m.first_name,
        m.last_name,
        br.branch_name
    FROM book_loans bl
    INNER JOIN books b ON bl.book_id = b.book_id
    INNER JOIN members m ON bl.member_id = m.member_id
    INNER JOIN branches br ON bl.checkout_branch_id = br.branch_id
    $whereSql
    ORDER BY bl.loan_id DESC
    LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    exit('Unable to prepare the loan records query.');
}

$bindTypes = $types . 'ii';
$bindParams = $params;
$bindParams[] = $perPage;
$bindParams[] = $offset;

mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindParams);
mysqli_stmt_execute($stmt);
$loans = mysqli_stmt_get_result($stmt);

$makePageUrl = function ($n) use ($q, $status, $dateFrom, $dateTo, $perPage) {
    return '?' . http_build_query([
        'q' => $q,
        'status' => $status,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'page' => $n,
        'per_page' => $perPage
    ]);
};

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <div class="eyebrow">CIRCULATION MANAGEMENT</div>
            <h2>Book loan records</h2>
            <p>Search, filter, and manage library borrowing records.</p>
        </div>
        <a class="button button-primary" href="add.php">＋ Record a loan</a>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success">
            <?= e($_SESSION['flash']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <form class="filter-bar" method="get" action="index.php">
        <div class="search-field">
            <span aria-hidden="true">⌕</span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search reference, book, or member">
        </div>

        <select name="status" aria-label="Filter by loan status">
            <option value="">All statuses</option>
            <?php foreach ($allowedStatuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                    <?= e($s) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="date" name="date_from" value="<?= e($dateFrom) ?>" aria-label="Checkout date from">
        <input type="date" name="date_to" value="<?= e($dateTo) ?>" aria-label="Checkout date to">

        <input type="hidden" name="page" value="1">
        <input type="hidden" name="per_page" value="<?= $perPage ?>">

        <button class="button button-outline search-submit" type="submit">Search</button>

        <?php if ($q !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
            <a class="text-link" href="index.php">Clear</a>
        <?php endif; ?>
    </form>

    <div class="results-line">
        Showing
        <strong><?= $totalRows ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $totalRows) ?></strong>
        of <strong><?= number_format($totalRows) ?></strong> records
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>LOAN REFERENCE</th>
                    <th>BOOK</th>
                    <th>MEMBER</th>
                    <th>CHECKOUT</th>
                    <th>DUE DATE</th>
                    <th>RETURN DATE</th>
                    <th>FINE</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($loans) > 0): ?>
                    <?php while ($loan = mysqli_fetch_assoc($loans)): ?>
                        <tr>
                            <td><?= e($loan['loan_reference']) ?></td>
                            <td><?= e($loan['title']) ?></td>
                            <td>
                                <strong><?= e($loan['first_name'] . ' ' . $loan['last_name']) ?></strong>
                                <small class="cell-sub"><?= e($loan['membership_number']) ?></small>
                            </td>
                            <td><?= e($loan['checkout_date']) ?></td>
                            <td><?= e($loan['due_date']) ?></td>
                            <td><?= e($loan['return_date'] ?: '—') ?></td>
                            <td><?= number_format((float) $loan['late_fine_amount'], 2) ?></td>
                            <td>
                                <span class="status status-<?= strtolower(e($loan['loan_status'])) ?>">
                                    <?= e($loan['loan_status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="action-link" href="edit.php?id=<?= (int) $loan['loan_id'] ?>">
                                        Edit
                                    </a>
                                    <form method="post" action="delete.php" data-confirm-message="Delete this loan record?"
                                        data-confirm-title="Delete loan record" data-confirm-action="Delete loan"
                                        data-confirm-danger="true">
                                        <input type="hidden" name="id" value="<?= (int) $loan['loan_id'] ?>">
                                        <button class="action-link danger-link" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="empty-state">No loan records match your search.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-bar">
        <div class="pagination" aria-label="Loan pagination">
            <a class="page-arrow <?= $page <= 1 ? 'disabled' : '' ?>"
                href="<?= $page > 1 ? e($makePageUrl($page - 1)) : '#' ?>">‹</a>

            <?php
            $visiblePages = [];

            if ($totalPages <= 7) {
                $visiblePages = range(1, $totalPages);
            } else {
                $visiblePages = [1];
                $startPage = max(2, $page - 1);
                $endPage = min($totalPages - 1, $page + 1);

                if ($startPage > 2) {
                    $visiblePages[] = '…';
                }

                for ($n = $startPage; $n <= $endPage; $n++) {
                    $visiblePages[] = $n;
                }

                if ($endPage < $totalPages - 1) {
                    $visiblePages[] = '…';
                }

                $visiblePages[] = $totalPages;
            }

            foreach ($visiblePages as $n):
                if ($n === '…'):
                    ?>
                    <span class="page-ellipsis">…</span>
                <?php else: ?>
                    <a class="<?= $n === $page ? 'current' : '' ?>" href="<?= e($makePageUrl($n)) ?>"><?= $n ?></a>
                <?php endif;
            endforeach;
            ?>

            <a class="page-arrow <?= $page >= $totalPages ? 'disabled' : '' ?>"
                href="<?= $page < $totalPages ? e($makePageUrl($page + 1)) : '#' ?>">›</a>
        </div>

        <div class="pagination-controls">
            <form class="page-jump-form" method="get">
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <input type="hidden" name="date_from" value="<?= e($dateFrom) ?>">
                <input type="hidden" name="date_to" value="<?= e($dateTo) ?>">
                <input type="hidden" name="per_page" value="<?= $perPage ?>">

                <label for="pageJump">Page</label>
                <input type="number" id="pageJump" name="page" min="1" max="<?= $totalPages ?>" value="<?= $page ?>"
                    required>
                <span>of <?= $totalPages ?></span>
                <button class="button button-outline" type="submit">Go</button>
            </form>
        </div>
    </div>
</section>

<?php
mysqli_stmt_close($stmt);
require __DIR__ . '/../includes/footer.php';
?>

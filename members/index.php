<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';

$pageTitle = 'Members';
$basePath = '../';

$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$type = trim($_GET['type'] ?? '');

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = in_array((int) ($_GET['per_page'] ?? 10), [10, 20, 30, 50], true)
    ? (int) ($_GET['per_page'] ?? 10)
    : 10;

$allowedStatuses = ['Active', 'Suspended', 'Expired', 'Pending'];
$allowedTypes = ['Student', 'Faculty', 'Regular', 'Senior', 'Corporate'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

if (!in_array($type, $allowedTypes, true)) {
    $type = '';
}

/* Search and filters */
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(
        m.membership_number LIKE ?
        OR m.first_name LIKE ?
        OR m.last_name LIKE ?
        OR m.email LIKE ?
        OR b.branch_name LIKE ?
    )";

    $like = '%' . $search . '%';

    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

if ($status !== '') {
    $where[] = 'm.account_status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($type !== '') {
    $where[] = 'm.membership_type = ?';
    $params[] = $type;
    $types .= 's';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

/* Count filtered records */
$countSql = "
    SELECT COUNT(*) AS total
    FROM members m
    INNER JOIN branches b
        ON m.home_branch_id = b.branch_id
    $whereSql
";

$countStmt = mysqli_prepare($conn, $countSql);

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

/* Advanced JOIN: members, branches, and borrowing history */
$sql = "
    SELECT
        m.member_id,
        m.membership_number,
        m.first_name,
        m.last_name,
        m.email,
        m.phone_number,
        m.membership_type,
        m.account_status,
        m.registration_date,
        m.expiration_date,
        b.branch_name,
        COUNT(bl.loan_id) AS total_loans
    FROM members m
    INNER JOIN branches b
        ON m.home_branch_id = b.branch_id
    LEFT JOIN book_loans bl
        ON m.member_id = bl.member_id
    $whereSql
    GROUP BY
        m.member_id,
        m.membership_number,
        m.first_name,
        m.last_name,
        m.email,
        m.phone_number,
        m.membership_type,
        m.account_status,
        m.registration_date,
        m.expiration_date,
        b.branch_name
    ORDER BY m.member_id DESC
    LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);

$bindTypes = $types . 'ii';
$bindParams = $params;
$bindParams[] = $perPage;
$bindParams[] = $offset;

mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindParams);
mysqli_stmt_execute($stmt);
$members = mysqli_stmt_get_result($stmt);

require __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <div class="eyebrow">MEMBER DIRECTORY</div>
            <h2>Member records</h2>
            <p>Search, filter, and manage library members.</p>
        </div>

        <a class="button button-primary" href="add.php">+ Add Member</a>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success">
            <?= e($_SESSION['flash']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <form class="filter-bar" method="get">
        <div class="search-field">
            <span aria-hidden="true">⌕</span>
            <input type="search" name="q" value="<?= e($search) ?>"
                placeholder="Search name, membership no., email, branch">
        </div>

        <select name="status" aria-label="Filter by account status">
            <option value="">All status</option>
            <?php foreach ($allowedStatuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                    <?= e($s) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="type" aria-label="Filter by membership type">
            <option value="">All member types</option>
            <?php foreach ($allowedTypes as $t): ?>
                <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>>
                    <?= e($t) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="button button-outline search-submit" type="submit">
            Search
        </button>

        <?php if ($search !== '' || $status !== '' || $type !== ''): ?>
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
                    <th>MEMBER</th>
                    <th>MEMBERSHIP NO.</th>
                    <th>TYPE</th>
                    <th>HOME BRANCH</th>
                    <th>LOANS</th>
                    <th>STATUS</th>
                    <th>EXPIRATION</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>

            <tbody>
                <?php if (mysqli_num_rows($members) > 0): ?>
                    <?php while ($m = mysqli_fetch_assoc($members)): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= e($m['first_name'] . ' ' . $m['last_name']) ?>
                                </strong>
                                <small class="cell-sub">
                                    <?= e($m['email']) ?>
                                </small>
                            </td>

                            <td><?= e($m['membership_number']) ?></td>
                            <td><?= e($m['membership_type']) ?></td>
                            <td><?= e($m['branch_name']) ?></td>
                            <td><?= (int) $m['total_loans'] ?></td>

                            <td>
                                <span class="status status-<?= strtolower(e($m['account_status'])) ?>">
                                    <?= e($m['account_status']) ?>
                                </span>
                            </td>

                            <td><?= e($m['expiration_date']) ?></td>




                            <td>
                                <div class="table-actions">
                                    <a class="button button-outline" href="edit.php?id=<?= (int) $m['member_id'] ?>">
                                        Edit
                                    </a>


                                    <form action="delete.php" method="post"
                                        data-confirm-message="Are you sure you want to delete this member? This action cannot be undone."
                                        data-confirm-title="Delete member record" data-confirm-action="Yes, delete member"
                                        data-confirm-danger="true">

                                        <input type="hidden" name="id" value="<?= (int) $m['member_id'] ?>">

                                        <button class="action-link danger-link" type="submit">
                                            Delete
                                        </button>
                                    </form>

                                </div>
                            </td>




                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            No members match your search.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-bar">
        <?php
        $makePageUrl = function ($n) use ($search, $status, $type, $perPage) {
            return '?' . http_build_query([
                'q' => $search,
                'status' => $status,
                'type' => $type,
                'page' => $n,
                'per_page' => $perPage
            ]);
        };
        ?>

        <div class="pagination" aria-label="Member pagination">
            <a class="page-arrow <?= $page <= 1 ? 'disabled' : '' ?>"
                href="<?= $page > 1 ? e($makePageUrl($page - 1)) : '#' ?>" aria-label="Previous page">‹</a>

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
                href="<?= $page < $totalPages ? e($makePageUrl($page + 1)) : '#' ?>" aria-label="Next page">›</a>
        </div>

        <form class="page-jump-form" method="get" aria-label="Go to member page">
            <input type="hidden" name="q" value="<?= e($search) ?>">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="hidden" name="per_page" value="<?= $perPage ?>">

            <label for="go-to-page" class="visually-hidden">Go to page</label>
            <input id="go-to-page" class="page-number-input" type="number" name="page" min="1" max="<?= $totalPages ?>"
                value="<?= $page ?>" inputmode="numeric" aria-label="Type page number" required>

            <span class="page-total">of <?= $totalPages ?></span>
            <button class="button button-outline" type="submit">Go</button>
        </form>
    </div>
</section>

<?php
mysqli_stmt_close($stmt);
require __DIR__ . '/../includes/footer.php';
?>

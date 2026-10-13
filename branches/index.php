<?php
require __DIR__ . '/../includes/auth.php';
require_login();
require __DIR__ . '/../database.php';
$pageTitle = 'Library branches';
$basePath = '../';
$isAdmin = (($_SESSION['role'] ?? '') === 'Admin');
if (empty($_SESSION['csrf_token']))
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$q = trim($_GET['q'] ?? '');
$city = trim($_GET['city'] ?? '');
$state = trim($_GET['state'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$like = '%' . $q . '%';
$countSql = 'SELECT COUNT(*) AS total FROM branches br WHERE (?="" OR br.branch_name LIKE ? OR br.branch_code LIKE ? OR br.city LIKE ?) AND (?="" OR br.city=?) AND (?="" OR br.state_province=?)';
$count = mysqli_prepare($conn, $countSql);
if (!$count) {
    error_log('Branch count query failed: ' . mysqli_error($conn));
    http_response_code(500);
    exit('Unable to load branches.');
}
mysqli_stmt_bind_param($count, 'ssssssss', $q, $like, $like, $like, $city, $city, $state, $state);
mysqli_stmt_execute($count);
$totalRows = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($count))['total'];
mysqli_stmt_close($count);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$sql = 'SELECT br.branch_id,br.branch_code,br.branch_name,br.street_address,br.city,br.state_province,br.postal_code,br.contact_phone,br.contact_email,br.opening_time,br.closing_time,br.capacity,COUNT(DISTINCT b.book_id) AS book_count,COUNT(DISTINCT m.member_id) AS member_count FROM branches br LEFT JOIN books b ON b.allocated_branch_id=br.branch_id LEFT JOIN members m ON m.home_branch_id=br.branch_id WHERE (?="" OR br.branch_name LIKE ? OR br.branch_code LIKE ? OR br.city LIKE ?) AND (?="" OR br.city=?) AND (?="" OR br.state_province=?) GROUP BY br.branch_id ORDER BY br.branch_name LIMIT ? OFFSET ?';
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    error_log('Branch list query failed: ' . mysqli_error($conn));
    http_response_code(500);
    exit('Unable to load branch details.');
}
mysqli_stmt_bind_param($stmt, 'ssssssssii', $q, $like, $like, $like, $city, $city, $state, $state, $perPage, $offset);
mysqli_stmt_execute($stmt);
$branches = mysqli_stmt_get_result($stmt);
$cities = mysqli_query($conn, 'SELECT DISTINCT city FROM branches ORDER BY city');
$states = mysqli_query($conn, 'SELECT DISTINCT state_province FROM branches ORDER BY state_province');
$makePageUrl = function ($n) use ($q, $city, $state) {
    return '?' . http_build_query(['q' => $q, 'city' => $city, 'state' => $state, 'page' => $n]);
};
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <div class="panel-heading">
        <div>
            <div class="eyebrow">OUR LOCATIONS</div>
            <h2>Library branches</h2>
            <p>Branch locations, contacts, opening hours, and collection counts.</p>
        </div><?php if ($isAdmin): ?><a class="button button-primary" href="add.php">＋ Add branch</a><?php endif; ?>
    </div>
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success"><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
    <form class="filter-bar" method="get">
        <div class="search-field"><span aria-hidden="true">⌕</span><input type="search" name="q" value="<?= e($q) ?>"
                placeholder="Search branch name or code"></div><select name="city" aria-label="Filter by city">
            <option value="">All cities</option><?php while ($c = mysqli_fetch_assoc($cities)): ?>
                <option value="<?= e($c['city']) ?>" <?= $city === $c['city'] ? 'selected' : '' ?>><?= e($c['city']) ?>
                </option>
            <?php endwhile; ?>
        </select><select name="state" aria-label="Filter by province">
            <option value="">All provinces</option><?php while ($s = mysqli_fetch_assoc($states)): ?>
                <option value="<?= e($s['state_province']) ?>" <?= $state === $s['state_province'] ? 'selected' : '' ?>>
                    <?= e($s['state_province']) ?>
                </option><?php endwhile; ?>
        </select><input type="hidden" name="page" value="1"><button class="button button-outline"
            type="submit">Filter</button><?php if ($q !== '' || $city !== '' || $state !== ''): ?><a class="text-link"
                href="index.php">Clear</a><?php endif; ?>
    </form>
    <div class="results-line">Showing
        <strong><?= $totalRows ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalRows) ?></strong> of
        <strong><?= number_format($totalRows) ?></strong> branches
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>BRANCH</th>
                    <th>LOCATION</th>
                    <th>CONTACT</th>
                    <th>HOURS</th>
                    <th>CAPACITY</th>
                    <th>TITLES</th>
                    <th>MEMBERS</th><?php if ($isAdmin): ?>
                        <th>ACTIONS</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($branches)):
                    while ($b = mysqli_fetch_assoc($branches)): ?>
                        <tr>
                            <td><strong><span class="branch-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24">
                                            <path
                                                d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5M9 9h.01M15 9h.01M9 12h.01M15 12h.01" />
                                        </svg></span><?= e($b['branch_name']) ?></strong><small
                                    class="cell-sub"><?= e($b['branch_code']) ?></small></td>
                            <td><?= e($b['street_address']) ?><small class="cell-sub"><?= e($b['city']) ?>,
                                    <?= e($b['state_province']) ?>         <?= e($b['postal_code']) ?></small></td>
                            <td><?php if ($b['contact_phone']): ?><a
                                        href="tel:<?= e($b['contact_phone']) ?>"><?= e($b['contact_phone']) ?></a><?php endif; ?><small
                                    class="cell-sub"><?php if ($b['contact_email']): ?><a
                                            href="mailto:<?= e($b['contact_email']) ?>"><?= e($b['contact_email']) ?></a><?php endif; ?></small>
                            </td>
                            <td><?= e(substr((string) $b['opening_time'], 0, 5)) ?> –
                                <?= e(substr((string) $b['closing_time'], 0, 5)) ?>
                            </td>
                            <td><?= number_format((int) $b['capacity']) ?></td>
                            <td><?= number_format((int) $b['book_count']) ?></td>
                            <td><?= number_format((int) $b['member_count']) ?></td><?php if ($isAdmin): ?>
                                <td>
                                    <div class="actions"><a class="action-link"
                                            href="edit.php?id=<?= (int) $b['branch_id'] ?>">Edit</a>
                                        <form method="post" action="delete.php"
                                            data-confirm-message="Delete this branch? Branches connected to books, members, or loans cannot be deleted."
                                            data-confirm-title="Delete branch" data-confirm-action="Delete branch"
                                            data-confirm-danger="true"><input type="hidden" name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="id"
                                                value="<?= (int) $b['branch_id'] ?>"><button type="submit"
                                                class="action-link danger-link">Delete</button></form>
                                    </div>
                                </td><?php endif; ?>
                        </tr><?php endwhile; else: ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? 8 : 7 ?>" class="empty-state">No branches match your filters.</td>
                    </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar">
        <div class="pagination" aria-label="Branch pagination"><a class="page-arrow <?= $page <= 1 ? 'disabled' : '' ?>"
                href="<?= $page > 1 ? e($makePageUrl($page - 1)) : '#' ?>" aria-label="Previous page">‹</a><?php $start = max(1, $page - 2);
                          $end = min($totalPages, $page + 2);
                          for ($n = $start; $n <= $end; $n++): ?><a class="<?= $n === $page ? 'current' : '' ?>"
                    href="<?= e($makePageUrl($n)) ?>"><?= $n ?></a><?php endfor; ?><a
                class="page-arrow <?= $page >= $totalPages ? 'disabled' : '' ?>"
                href="<?= $page < $totalPages ? e($makePageUrl($page + 1)) : '#' ?>" aria-label="Next page">›</a></div>
        <form class="page-jump-form" method="get"><input type="hidden" name="q" value="<?= e($q) ?>"><input
                type="hidden" name="city" value="<?= e($city) ?>"><input type="hidden" name="state"
                value="<?= e($state) ?>"><label for="branchPage">Page</label><input id="branchPage" type="number"
                name="page" min="1" max="<?= $totalPages ?>" value="<?= $page ?>" required><span>of
                <?= $totalPages ?></span><button class="button button-outline" type="submit">Go</button></form>
    </div>
</section>
<?php mysqli_stmt_close($stmt);
require __DIR__ . '/../includes/footer.php'; ?>
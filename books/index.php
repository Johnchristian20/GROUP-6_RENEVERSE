<?php

require __DIR__ . '/../includes/auth.php';

require_login();

require __DIR__ . '/../database.php';

$pageTitle = 'Book catalogue';

$basePath = '../';

$search = trim($_GET['q'] ?? '');

$status = trim($_GET['status'] ?? '');

$page = max(1, (int) ($_GET['page'] ?? 1));

$perPage = in_array((int) ($_GET['per_page'] ?? 10), [10, 20, 30, 50], true) ? (int) ($_GET['per_page'] ?? 10) : 10;
$_SESSION['books_per_page'] = $perPage;

$allowedStatuses = ['Available', 'Checked Out', 'Reserved', 'Under Maintenance', 'Lost'];

$where = [];

$params = [];

$types = '';

if ($search !== '') {

  $where[] = '(b.title LIKE ? OR b.author_name LIKE ? OR b.isbn LIKE ? OR b.call_number LIKE ?)';

  $like = '%' . $search . '%';

  array_push($params, $like, $like, $like, $like);

  $types .= 'ssss';

}

if (in_array($status, $allowedStatuses, true)) {

  $where[] = 'b.status = ?';

  $params[] = $status;

  $types .= 's';

}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM books b" . $whereSql);

if ($types !== '')

  mysqli_stmt_bind_param($countStmt, $types, ...$params);

mysqli_stmt_execute($countStmt);

$totalRows = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'];

mysqli_stmt_close($countStmt);

$totalPages = max(1, (int) ceil($totalRows / $perPage));

$page = min($page, $totalPages);

$offset = ($page - 1) * $perPage;

$sql = "SELECT b.book_id,b.isbn,b.title,b.author_name,b.genre,b.publication_year,b.status,br.branch_name

        FROM books b INNER JOIN branches br ON b.allocated_branch_id=br.branch_id" . $whereSql . "

        ORDER BY b.book_id DESC LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);

$bindTypes = $types . 'ii';

$bindParams = $params;

$bindParams[] = $perPage;

$bindParams[] = $offset;

mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindParams);

mysqli_stmt_execute($stmt);

$books = mysqli_stmt_get_result($stmt);

require __DIR__ . '/../includes/header.php';

?>



<section class="panel">

  <div class="panel-heading">

    <div>

      <div class="eyebrow">LIBRARY COLLECTION</div>

      <h2>Book records</h2>

      <p>Search, filter, and manage the collection.</p>

    </div>

    <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?><a class="button button-primary" href="add.php">＋ Add a

        book</a><?php endif; ?>

  </div>

  <?php if (!empty($_SESSION['flash'])): ?>

    <div class="alert alert-success"><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>

  <form class="filter-bar" method="get" action="index.php" id="bookSearchForm">

    <div class="search-field"><span aria-hidden="true">⌕</span><input type="search" name="q" id="bookSearchInput"
        value="<?= e($search) ?>" placeholder="Search title, author, ISBN, call number" autocomplete="off"></div>

    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= $perPage ?>">

    <select name="status" aria-label="Filter by status" onchange="this.form.submit()">

      <option value="">All status</option><?php foreach ($allowedStatuses as $s): ?>

        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>

    </select>

    <button class="button button-outline search-submit" type="submit">Search</button>

    <?php if ($search !== '' || $status !== ''): ?><a class="text-link" href="index.php">Clear</a><?php endif; ?>

  </form>

  <div class="results-line">Showing

    <strong><?= $totalRows ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $totalRows) ?></strong> of

    <strong><?= number_format($totalRows) ?></strong> records

  </div>

  <div class="table-wrap">

    <table>

      <thead>

        <tr>

          <th>TITLE / ISBN</th>

          <th>AUTHOR</th>

          <th>GENRE</th>

          <th>YEAR</th>

          <th>BRANCH</th>

          <th>STATUS</th>
          <th><?= (($_SESSION['role'] ?? '') === 'Admin') ? 'ACTIONS' : 'REQUEST' ?></th>

        </tr>

      </thead>

      <tbody>

        <?php if (mysqli_num_rows($books)):

          while ($b = mysqli_fetch_assoc($books)): ?>

            <tr>

              <td><strong><?= e($b['title']) ?></strong><small class="cell-sub"><?= e($b['isbn']) ?></small></td>

              <td><?= e($b['author_name']) ?></td>

              <td><?= e($b['genre']) ?></td>

              <td><?= e($b['publication_year']) ?></td>

              <td><?= e($b['branch_name']) ?></td>

              <td><span
                  class="status status-<?= strtolower(str_replace(' ', '-', $b['status'])) ?>"><?= e($b['status']) ?></span>

              </td>

              <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <td>
                  <div class="actions"><a class="action-link" href="edit.php?id=<?= (int) $b['book_id'] ?>">Edit</a>
                    <form action="delete.php" method="post"
                      data-confirm-message="Delete this book record? This action cannot be undone."
                      data-confirm-title="Delete book record" data-confirm-action="Delete book" data-confirm-danger="true">
                      <input type="hidden" name="id" value="<?= (int) $b['book_id'] ?>"><button
                        class="action-link danger-link" type="submit">Delete</button>
                    </form>
                  </div>
                </td>
              <?php else: ?>
                <td><?php if ($b['status'] === 'Available'): ?><a class="action-link"
                      href="../requests/index.php?book_id=<?= (int) $b['book_id'] ?>">Request</a><?php else: ?><span
                      class="muted">Unavailable</span><?php endif; ?></td><?php endif; ?>

            </tr>

          <?php endwhile; else: ?>

          <tr>

            <td colspan="<?= (($_SESSION['role'] ?? '') === 'Admin') ? 7 : 7 ?>" class="empty-state">No books match your

              search.</td>

          </tr><?php endif; ?>

      </tbody>

    </table>

  </div>

  <div class="pagination-bar">

    <div class="pagination" aria-label="Pagination">

      <?php $makePageUrl = function ($n) use ($search, $status, $perPage) {

        return '?' . http_build_query(['q' => $search, 'status' => $status, 'page' => $n, 'per_page' => $perPage]);

      }; ?>

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

        if ($startPage > 2)

          $visiblePages[] = '…';

        for ($n = $startPage; $n <= $endPage; $n++)

          $visiblePages[] = $n;

        if ($endPage < $totalPages - 1)

          $visiblePages[] = '…';

        $visiblePages[] = $totalPages;

      }

      foreach ($visiblePages as $n):

        if ($n === '…'): ?><span class="page-ellipsis">…</span><?php else: ?><a
            class="<?= $n === $page ? 'current' : '' ?>" href="<?= e($makePageUrl($n)) ?>"><?= $n ?></a><?php endif;

      endforeach;

      ?>

      <a class="page-arrow <?= $page >= $totalPages ? 'disabled' : '' ?>"
        href="<?= $page < $totalPages ? e($makePageUrl($page + 1)) : '#' ?>" aria-label="Next page">›</a>

    </div>


    <div class="pagination-controls">

      <form class="page-jump-form" method="get" action="index.php">
        <input type="hidden" name="q" value="<?= e($search) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="per_page" value="<?= $perPage ?>">

        <label for="pageJump">Page</label>
        <input type="number" id="pageJump" name="page" min="1" max="<?= $totalPages ?>" value="<?= $page ?>" required
          aria-label="Enter page number" onkeydown="if(event.key === 'Enter') this.form.requestSubmit()">
        <span>of <?= $totalPages ?></span>
        <button class="button button-outline" type="submit">Go</button>
      </form>

    </div>


  </div>

</section>

<?php mysqli_stmt_close($stmt);

require __DIR__ . '/../includes/footer.php'; ?>
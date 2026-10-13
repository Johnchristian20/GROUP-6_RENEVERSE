<?php
require __DIR__ . '/includes/auth.php';
require_login();
require __DIR__ . '/database.php';
$pageTitle = 'Overview';
$basePath = '';
$counts = ['books' => 0, 'members' => 0, 'loans' => 0, 'available' => 0];
foreach ([
  'books' => "SELECT COUNT(*) AS total FROM books",
  'members' => "SELECT COUNT(*) AS total FROM members",
  'loans' => "SELECT COUNT(*) AS total FROM book_loans",
  'available' => "SELECT COUNT(*) AS total FROM books WHERE status='Available'"
] as $key => $sql) {
  $res = mysqli_query($conn, $sql);
  if ($res) {
    $row = mysqli_fetch_assoc($res);
    $counts[$key] = (int) $row['total'];
  }
}
$recentSql = "SELECT b.book_id, b.title, b.author_name, b.genre, b.status, br.branch_name
              FROM books b INNER JOIN branches br ON b.allocated_branch_id = br.branch_id
              ORDER BY b.book_id DESC LIMIT 6";
$recent = mysqli_query($conn, $recentSql);
require __DIR__ . '/includes/header.php';
?>
<section class="welcome-banner">
  <div>
    <div class="eyebrow">YOUR LIBRARY, AT A GLANCE</div>
    <h2>Good to see you, <?= e($_SESSION['first_name']) ?>.</h2>
    <p>Here is the latest snapshot of your library collection.</p>
  </div>
  <div class="banner-mark" aria-hidden="true"><svg viewBox="0 0 100 100">
      <path d="M12 24c15-7 28-4 38 5v51C40 71 27 68 12 75zM88 24c-15-7-28-4-38 5v51c10-9 23-12 38-5z" />
      <path d="M22 35l18 5M22 46l18 5M78 35L60 40M78 46L60 51" />
    </svg></div>
</section>
<div class="stats-grid">
  <article class="stat-card">
    <div class="stat-top"><span>Total titles</span><span class="stat-icon">▤</span></div>
    <strong><?= number_format($counts['books']) ?></strong><small>Records in the catalogue</small>
  </article>
  <article class="stat-card">
    <div class="stat-top"><span>Available books</span><span class="stat-icon">✓</span></div>
    <strong><?= number_format($counts['available']) ?></strong><small>Ready to be borrowed</small>
  </article>
  <article class="stat-card">
    <div class="stat-top"><span>Registered members</span><span class="stat-icon">♙</span></div>
    <strong><?= number_format($counts['members']) ?></strong><small>Library member records</small>
  </article>
  <article class="stat-card">
    <div class="stat-top"><span>Loan records</span><span class="stat-icon">↗</span></div>
    <strong><?= number_format($counts['loans']) ?></strong><small>Borrowing history entries</small>
  </article>
</div>
<section class="panel">
  <div class="panel-heading">
    <div>
      <div class="eyebrow">COLLECTION</div>
      <h2>Recently added books</h2>
      <p>Latest records from the book catalogue.</p>
    </div><a class="button button-outline" href="books/index.php">View catalogue <span>→</span></a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>BOOK TITLE</th>
          <th>AUTHOR</th>
          <th>GENRE</th>
          <th>BRANCH</th>
          <th>STATUS</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recent && mysqli_num_rows($recent) > 0):
          while ($book = mysqli_fetch_assoc($recent)): ?>
            <tr>
              <td><strong><?= e($book['title']) ?></strong><small class="cell-sub"><?= e($book['book_id']) ?> · Catalogue
                  ID</small></td>
              <td><?= e($book['author_name']) ?></td>
              <td><?= e($book['genre']) ?></td>
              <td><?= e($book['branch_name']) ?></td>
              <td><span
                  class="status status-<?= strtolower(str_replace(' ', '-', $book['status'])) ?>"><?= e($book['status']) ?></span>
              </td>
            </tr>
          <?php endwhile; else: ?>
          <tr>
            <td colspan="5" class="empty-state">No book records found.</td>
          </tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
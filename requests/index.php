<?php
require __DIR__ . '/../includes/auth.php';
require_login();
require __DIR__ . '/../database.php';
$pageTitle = 'Book requests';
$basePath = '../';
$isAdmin = (($_SESSION['role'] ?? '') === 'Admin');
$flash = '';
$selectedBookId = (int) ($_GET['book_id'] ?? 0);
if (empty($_SESSION['csrf_token']))
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$requestSchema = "CREATE TABLE IF NOT EXISTS book_requests (
  request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  book_id INT NOT NULL,
  pickup_branch_id INT NOT NULL,
  request_note VARCHAR(500) NOT NULL DEFAULT '',
  status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  request_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by INT UNSIGNED DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  PRIMARY KEY (request_id),
  KEY idx_requests_user_status (user_id,status),
  KEY idx_requests_book_status (book_id,status),
  CONSTRAINT fk_requests_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_requests_book FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_requests_branch FOREIGN KEY (pickup_branch_id) REFERENCES branches(branch_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
if (!mysqli_query($conn, $requestSchema)) {
  error_log('Book request schema setup failed: ' . mysqli_error($conn));
  http_response_code(500);
  require __DIR__ . '/../includes/header.php';
  ?>
  <section class="panel">
    <div class="eyebrow">DATABASE SETUP</div>
    <h2>Book requests need a database update</h2>
    <p>The request table could not be prepared. Confirm that the <code>library_management</code> database contains the
      books, branches, and users tables, then run <code>database/requests-migration.sql</code> in HeidiSQL or phpMyAdmin.
    </p><a class="button button-outline" href="../dashboard.php">Return to overview</a>
  </section>
  <?php
  require __DIR__ . '/../includes/footer.php';
  exit;
}
function req_redirect(): void
{
  header('Location: index.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid form token. Reload the page and try again.');
  }
  $action = $_POST['action'] ?? '';
  if ($action === 'request') {
    $bookId = (int) ($_POST['book_id'] ?? 0);
    $branchId = (int) ($_POST['pickup_branch_id'] ?? 0);
    $note = trim($_POST['request_note'] ?? '');
    $check = mysqli_prepare($conn, "SELECT book_id FROM books WHERE book_id=? AND status='Available'");
    mysqli_stmt_bind_param($check, 'i', $bookId);
    mysqli_stmt_execute($check);
    $found = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
    mysqli_stmt_close($check);
    if (!$found)
      $_SESSION['flash'] = 'That book is no longer available.';
    else {
      $uid = (int) $_SESSION['user_id'];
      $dupe = mysqli_prepare($conn, "SELECT request_id FROM book_requests WHERE user_id=? AND book_id=? AND status IN ('Pending','Approved') LIMIT 1");
      mysqli_stmt_bind_param($dupe, 'ii', $uid, $bookId);
      mysqli_stmt_execute($dupe);
      $active = mysqli_fetch_assoc(mysqli_stmt_get_result($dupe));
      mysqli_stmt_close($dupe);
      if ($active)
        $_SESSION['flash'] = 'You already have an active request for this title.';
      else {
        $s = mysqli_prepare($conn, "INSERT INTO book_requests(user_id,book_id,pickup_branch_id,request_note) VALUES(?,?,?,?)");
        mysqli_stmt_bind_param($s, 'iiis', $uid, $bookId, $branchId, $note);
        if (mysqli_stmt_execute($s))
          $_SESSION['flash'] = 'Your request was sent to the library.';
        else
          $_SESSION['flash'] = 'The request could not be saved. Please try again.';
        mysqli_stmt_close($s);
      }
    }
    req_redirect();
  }
  if ($isAdmin && in_array($action, ['approve', 'reject'], true)) {
    $rid = (int) ($_POST['request_id'] ?? 0);
    mysqli_begin_transaction($conn);
    $s = mysqli_prepare($conn, 'SELECT book_id,status FROM book_requests WHERE request_id=? FOR UPDATE');
    mysqli_stmt_bind_param($s, 'i', $rid);
    mysqli_stmt_execute($s);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    mysqli_stmt_close($s);
    if ($r && $r['status'] === 'Pending') {
      if ($action === 'approve') {
        $b = mysqli_prepare($conn, "UPDATE books SET status='Reserved' WHERE book_id=? AND status='Available'");
        mysqli_stmt_bind_param($b, 'i', $r['book_id']);
        mysqli_stmt_execute($b);
        $ok = mysqli_stmt_affected_rows($b) === 1;
        mysqli_stmt_close($b);
        if ($ok) {
          $status = 'Approved';
        } else {
          $status = 'Rejected';
        }
      } else
        $status = 'Rejected';
      $reviewer = (int) $_SESSION['user_id'];
      $u = mysqli_prepare($conn, 'UPDATE book_requests SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE request_id=?');
      mysqli_stmt_bind_param($u, 'sii', $status, $reviewer, $rid);
      mysqli_stmt_execute($u);
      mysqli_stmt_close($u);
      mysqli_commit($conn);
      $_SESSION['flash'] = $status === 'Approved' ? 'Request approved and book reserved.' : 'Request declined.';
    } else {
      mysqli_rollback($conn);
      $_SESSION['flash'] = 'This request was already reviewed.';
    }
    req_redirect();
  }
  if ($action === 'cancel') {
    $rid = (int) ($_POST['request_id'] ?? 0);
    $uid = (int) $_SESSION['user_id'];
    $s = mysqli_prepare($conn, "UPDATE book_requests SET status='Cancelled' WHERE request_id=? AND user_id=? AND status='Pending'");
    mysqli_stmt_bind_param($s, 'ii', $rid, $uid);
    mysqli_stmt_execute($s);
    $ok = mysqli_stmt_affected_rows($s) > 0;
    mysqli_stmt_close($s);
    $_SESSION['flash'] = $ok ? 'Request cancelled.' : 'This request can no longer be cancelled.';
    req_redirect();
  }
}
$branches = mysqli_query($conn, 'SELECT branch_id,branch_name FROM branches ORDER BY branch_name');
$uid = (int) $_SESSION['user_id'];
$sql = "SELECT r.request_id,r.status,r.request_note,r.request_date,r.reviewed_at,b.title,b.author_name,b.status AS book_status,br.branch_name,u.first_name,u.last_name,u.username FROM book_requests r JOIN books b ON b.book_id=r.book_id JOIN branches br ON br.branch_id=r.pickup_branch_id JOIN users u ON u.user_id=r.user_id" . ($isAdmin ? '' : ' WHERE r.user_id=?') . " ORDER BY r.request_id DESC";
$s = mysqli_prepare($conn, $sql);
if (!$isAdmin)
  mysqli_stmt_bind_param($s, 'i', $uid);
mysqli_stmt_execute($s);
$requests = mysqli_stmt_get_result($s);
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
  <div class="panel-heading">
    <div>
      <div class="eyebrow">CIRCULATION</div>
      <h2><?= $isAdmin ? 'Manage book requests' : 'Your book requests' ?></h2>
      <p><?= $isAdmin ? 'Review requests and reserve available titles.' : 'Request an available title from your library.' ?>
      </p>
    </div><a class="button button-outline" href="../books/index.php">Browse catalogue</a>
  </div>
  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
  <?php if (!$isAdmin): ?>
    <form method="post" class="filter-bar"><input type="hidden" name="csrf_token"
        value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="request"><label>Available
        book<select name="book_id" required>
          <option value="">Choose a title</option>
          <?php $avail = mysqli_query($conn, "SELECT book_id,title,author_name FROM books WHERE status='Available' ORDER BY title");
          while ($b = mysqli_fetch_assoc($avail)): ?>
            <option value="<?= $b['book_id'] ?>" <?= $selectedBookId === (int) $b['book_id'] ? 'selected' : '' ?>>
              <?= e($b['title'] . ' — ' . $b['author_name']) ?></option><?php endwhile; ?>
        </select></label><label>Pickup branch<select name="pickup_branch_id"
          required><?php while ($br = mysqli_fetch_assoc($branches)): ?>
            <option value="<?= $br['branch_id'] ?>"><?= e($br['branch_name']) ?></option><?php endwhile; ?>
        </select></label><label>Note (optional)<input name="request_note" maxlength="500"
          placeholder="Anything the librarian should know?"></label><button class="button button-primary"
        type="submit">Request book</button></form><?php endif; ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><?php if ($isAdmin): ?>
            <th>REQUESTER</th><?php endif; ?>
          <th>BOOK</th>
          <th>PICKUP BRANCH</th>
          <th>REQUESTED</th>
          <th>STATUS</th><?php if ($isAdmin): ?>
            <th>ACTIONS</th><?php else: ?>
            <th>NOTE / ACTION</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($requests)):
          while ($r = mysqli_fetch_assoc($requests)): ?>
            <tr><?php if ($isAdmin): ?>
                <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?><small class="cell-sub">@<?= e($r['username']) ?></small></td>
              <?php endif; ?>
              <td><strong><?= e($r['title']) ?></strong><small class="cell-sub"><?= e($r['author_name']) ?></small></td>
              <td><?= e($r['branch_name']) ?></td>
              <td><?= e($r['request_date']) ?></td>
              <td><span class="status status-<?= strtolower(e($r['status'])) ?>"><?= e($r['status']) ?></span></td>
              <td><?php if ($isAdmin && $r['status'] === 'Pending'): ?>
                  <div class="actions">
                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input
                        type="hidden" name="action" value="approve"><input type="hidden" name="request_id"
                        value="<?= $r['request_id'] ?>"><button class="action-link" type="submit">Approve & reserve</button>
                    </form>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input
                        type="hidden" name="action" value="reject"><input type="hidden" name="request_id"
                        value="<?= $r['request_id'] ?>"><button class="action-link danger-link" type="submit">Decline</button>
                    </form>
                  </div>
                <?php else: ?>      <?= e($r['request_note'] ?: ($r['status'] === 'Approved' ? 'Reserved for pickup.' : '')) ?>      <?php if (!$isAdmin && $r['status'] === 'Pending'): ?>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input
                        type="hidden" name="action" value="cancel"><input type="hidden" name="request_id"
                        value="<?= $r['request_id'] ?>"><button class="action-link danger-link" type="submit">Cancel
                        request</button></form><?php endif; ?><?php endif; ?>
              </td>
            </tr><?php endwhile; else: ?>
          <tr>
            <td colspan="<?= $isAdmin ? 6 : 5 ?>" class="empty-state">No book requests yet.</td>
          </tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($isAdmin): ?>
    <p class="muted">After approval, the title is marked Reserved. Record the actual checkout in Book loans after the
      member collects it.</p><?php endif; ?>
</section>
<?php mysqli_stmt_close($s);
require __DIR__ . '/../includes/footer.php'; ?>
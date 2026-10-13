
<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';
$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
  header('Location: index.php');
  exit;
}
$pageTitle = 'Edit book';
$basePath = '../';
$isEdit = true;
$error = '';
$stmt = mysqli_prepare($conn, 'SELECT * FROM books WHERE book_id=?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$book = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$book) {
  http_response_code(404);
  exit('Book record not found.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fields = ['isbn', 'title', 'author_name', 'genre', 'publisher', 'publication_year', 'language_code', 'page_count', 'call_number', 'allocated_branch_id', 'shelf_location', 'status'];
  foreach ($fields as $f)
    $book[$f] = trim($_POST[$f] ?? '');
  $submitted = [];
  foreach ($fields as $f)
    $submitted[] = $book[$f];
  if (in_array('', $submitted, true))
    $error = 'Please complete all required fields.';
  elseif (!ctype_digit((string) $book['publication_year']) || (int) $book['publication_year'] < 1000 || (int) $book['publication_year'] > (int) date('Y') + 1)
    $error = 'Enter a valid publication year.';
  elseif (!ctype_digit((string) $book['page_count']) || (int) $book['page_count'] < 1)
    $error = 'Page count must be at least 1.';
  elseif (!ctype_digit((string) $book['allocated_branch_id']))
    $error = 'Choose a valid branch.';
  else {
    $sql = 'UPDATE books SET isbn=?,title=?,author_name=?,genre=?,publisher=?,publication_year=?,language_code=?,page_count=?,call_number=?,allocated_branch_id=?,shelf_location=?,status=? WHERE book_id=?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sssssisisissi', $book['isbn'], $book['title'], $book['author_name'], $book['genre'], $book['publisher'], $book['publication_year'], $book['language_code'], $book['page_count'], $book['call_number'], $book['allocated_branch_id'], $book['shelf_location'], $book['status'], $id);
    if (mysqli_stmt_execute($stmt)) {
      $_SESSION['flash'] = 'Book record updated successfully.';
      header('Location: index.php');
      exit;
    }
    $dbError = mysqli_stmt_error($stmt);
    error_log('Book update failed: ' . $dbError);
    $error = mysqli_stmt_errno($stmt) === 1062 ? 'That ISBN already exists. Use a unique ISBN.' : 'The book was not updated: ' . $dbError;
    mysqli_stmt_close($stmt);
  }
}
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/form.php';
require __DIR__ . '/../includes/footer.php';

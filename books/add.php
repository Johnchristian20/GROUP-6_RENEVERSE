<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';
$pageTitle = 'Add book';
$basePath = '../';
$isEdit = false;
$error = '';
$book = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['isbn', 'title', 'author_name', 'genre', 'publisher', 'publication_year', 'language_code', 'page_count', 'call_number', 'allocated_branch_id', 'shelf_location', 'status'];
    foreach ($fields as $f)
        $book[$f] = trim($_POST[$f] ?? '');
    if (in_array('', $book, true))
        $error = 'Please complete all required fields.';
    elseif (!ctype_digit($book['publication_year']) || (int) $book['publication_year'] < 1000 || (int) $book['publication_year'] > (int) date('Y') + 1)
        $error = 'Enter a valid publication year.';
    elseif (!ctype_digit($book['page_count']) || (int) $book['page_count'] < 1)
        $error = 'Page count must be at least 1.';
    elseif (!ctype_digit($book['allocated_branch_id']))
        $error = 'Choose a valid branch.';
    else {
        $sql = 'INSERT INTO books (isbn,title,author_name,genre,publisher,publication_year,language_code,page_count,call_number,allocated_branch_id,shelf_location,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            error_log('Book insert prepare failed: ' . mysqli_error($conn));
            $error = 'Could not prepare the book record. Check the books table structure.';
        } else {
            mysqli_stmt_bind_param($stmt, 'sssssisisiss', $book['isbn'], $book['title'], $book['author_name'], $book['genre'], $book['publisher'], $book['publication_year'], $book['language_code'], $book['page_count'], $book['call_number'], $book['allocated_branch_id'], $book['shelf_location'], $book['status']);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $_SESSION['flash'] = 'Book record added successfully.';
                header('Location: index.php?page=1&per_page=' . (int) ($_SESSION['books_per_page'] ?? 10));
                exit;
            }
            $dbError = mysqli_stmt_error($stmt);
            error_log('Book insert failed: ' . $dbError);
            $error = mysqli_stmt_errno($stmt) === 1062
                ? 'That ISBN already exists. Use a unique ISBN.'
                : 'The book was not saved: ' . $dbError;
            mysqli_stmt_close($stmt);
        }
    }
}
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/form.php';
require __DIR__ . '/../includes/footer.php';
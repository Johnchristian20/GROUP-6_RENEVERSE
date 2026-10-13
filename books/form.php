<?php
$statuses = ['Available', 'Checked Out', 'Reserved', 'Under Maintenance', 'Lost'];
$branches = mysqli_query($conn, 'SELECT branch_id, branch_name FROM branches ORDER BY branch_name');
function field_value($key, $book)
{
  return e($book[$key] ?? '');
}
?>
<section class="panel form-panel">
  <div class="panel-heading">
    <div>
      <div class="eyebrow">CATALOGUE MANAGEMENT</div>
      <h2><?= $isEdit ? 'Edit book details' : 'Add a new book' ?></h2>
      <p>Fields marked with an asterisk are required.</p>
    </div><a class="button button-outline" href="index.php">← Back to catalogue</a>
  </div>
  <?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="record-form"
    data-confirm-message="<?= $isEdit ? 'Save these changes to this book record?' : 'Add this book to the library catalogue?' ?>"
    data-confirm-title="<?= $isEdit ? 'Confirm changes' : 'Confirm new book' ?>"
    data-confirm-action="<?= $isEdit ? 'Save changes' : 'Add book' ?>">
    <div class="form-grid">
      <div class="form-field"><label for="isbn">ISBN *</label><input id="isbn" name="isbn" maxlength="20" required
          value="<?= field_value('isbn', $book) ?>"></div>
      <div class="form-field"><label for="title">Book title *</label><input id="title" name="title" maxlength="200"
          required value="<?= field_value('title', $book) ?>"></div>
      <div class="form-field"><label for="author_name">Author name *</label><input id="author_name" name="author_name"
          maxlength="100" required value="<?= field_value('author_name', $book) ?>"></div>
      <div class="form-field"><label for="genre">Genre *</label><input id="genre" name="genre" maxlength="50" required
          value="<?= field_value('genre', $book) ?>"></div>
      <div class="form-field"><label for="publisher">Publisher *</label><input id="publisher" name="publisher"
          maxlength="100" required value="<?= field_value('publisher', $book) ?>"></div>
      <div class="form-field"><label for="publication_year">Publication year *</label><input id="publication_year"
          name="publication_year" type="number" min="1000" max="<?= date('Y') + 1 ?>" required
          value="<?= field_value('publication_year', $book) ?>"></div>
      <div class="form-field"><label for="language_code">Language code *</label><input id="language_code"
          name="language_code" maxlength="10" required value="<?= field_value('language_code', $book) ?>"></div>
      <div class="form-field"><label for="page_count">Page count *</label><input id="page_count" name="page_count"
          type="number" min="1" required value="<?= field_value('page_count', $book) ?>"></div>
      <div class="form-field"><label for="call_number">Call number *</label><input id="call_number" name="call_number"
          maxlength="50" required value="<?= field_value('call_number', $book) ?>"></div>
      <div class="form-field"><label for="allocated_branch_id">Allocated branch *</label><select
          id="allocated_branch_id" name="allocated_branch_id" required>
          <option value="">Choose branch</option><?php if ($branches):
            while ($br = mysqli_fetch_assoc($branches)): ?>
              <option value="<?= (int) $br['branch_id'] ?>" <?= (string) ($book['allocated_branch_id'] ?? '') === (string) $br['branch_id'] ? 'selected' : '' ?>>
                <?= e($br['branch_name']) ?>
              </option><?php endwhile; endif; ?>
        </select></div>
      <div class="form-field"><label for="shelf_location">Shelf location *</label><input id="shelf_location"
          name="shelf_location" maxlength="30" required value="<?= field_value('shelf_location', $book) ?>"></div>
      <div class="form-field"><label for="status">Status *</label><select id="status" name="status"
          required><?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>" <?= ($book['status'] ?? 'Available') === $s ? 'selected' : '' ?>><?= e($s) ?>
            </option>
          <?php endforeach; ?>
        </select></div>
    </div>
    <div class="form-actions"><a class="button button-outline" href="index.php">Cancel</a><button
        class="button button-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Create book record' ?></button>
    </div>
  </form>
</section>
<?php
$statuses = ['Ongoing', 'Returned', 'Overdue', 'Lost'];

$booksResult = mysqli_query(
    $conn,
    "SELECT book_id, title, status FROM books ORDER BY title"
);

$membersResult = mysqli_query(
    $conn,
    "SELECT member_id, membership_number, first_name, last_name, account_status
     FROM members ORDER BY last_name, first_name"
);

$branchesResult = mysqli_query(
    $conn,
    "SELECT branch_id, branch_name FROM branches ORDER BY branch_name"
);

function loan_field($key, $loan)
{
    return e($loan[$key] ?? '');
}

function loan_datetime_input($value)
{
    if (!$value) {
        return '';
    }
    return e(str_replace(' ', 'T', substr((string) $value, 0, 16)));
}
?>

<section class="panel form-panel">
    <div class="panel-heading">
        <div>
            <div class="eyebrow">CIRCULATION MANAGEMENT</div>
            <h2><?= $isEdit ? 'Edit loan record' : 'Record a book loan' ?></h2>
            <p>Complete the loan details below.</p>
        </div>
        <a class="button button-outline" href="index.php">← Back to loans</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="record-form"
        data-confirm-message="<?= $isEdit ? 'Save these loan changes?' : 'Create this loan record?' ?>"
        data-confirm-title="<?= $isEdit ? 'Confirm changes' : 'Confirm loan' ?>"
        data-confirm-action="<?= $isEdit ? 'Save changes' : 'Create loan' ?>">

        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-field">
                <label for="loan_reference">Loan reference *</label>
                <input id="loan_reference" name="loan_reference" maxlength="30" required
                    value="<?= loan_field('loan_reference', $loan) ?>">
            </div>

            <div class="form-field">
                <label for="book_id">Book *</label>
                <select id="book_id" name="book_id" required>
                    <option value="">Choose book</option>
                    <?php if ($booksResult):
                        while ($b = mysqli_fetch_assoc($booksResult)): ?>
                            <option value="<?= (int) $b['book_id'] ?>" <?= (string) ($loan['book_id'] ?? '') === (string) $b['book_id'] ? 'selected' : '' ?>>
                                <?= e($b['title']) ?> — <?= e($b['status']) ?>
                            </option>
                        <?php endwhile; endif; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="member_id">Member *</label>
                <select id="member_id" name="member_id" required>
                    <option value="">Choose member</option>
                    <?php if ($membersResult):
                        while ($m = mysqli_fetch_assoc($membersResult)): ?>
                            <option value="<?= (int) $m['member_id'] ?>" <?= (string) ($loan['member_id'] ?? '') === (string) $m['member_id'] ? 'selected' : '' ?>>
                                <?= e($m['membership_number'] . ' - ' . $m['first_name'] . ' ' . $m['last_name']) ?>
                                (<?= e($m['account_status']) ?>)
                            </option>
                        <?php endwhile; endif; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="checkout_branch_id">Checkout branch *</label>
                <select id="checkout_branch_id" name="checkout_branch_id" required>
                    <option value="">Choose branch</option>
                    <?php if ($branchesResult):
                        while ($br = mysqli_fetch_assoc($branchesResult)): ?>
                            <option value="<?= (int) $br['branch_id'] ?>" <?= (string) ($loan['checkout_branch_id'] ?? '') === (string) $br['branch_id'] ? 'selected' : '' ?>>
                                <?= e($br['branch_name']) ?>
                            </option>
                        <?php endwhile; endif; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="checkout_date">Checkout date *</label>
                <input type="datetime-local" id="checkout_date" name="checkout_date" required
                    value="<?= loan_datetime_input($loan['checkout_date'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label for="due_date">Due date *</label>
                <input type="datetime-local" id="due_date" name="due_date" required
                    value="<?= loan_datetime_input($loan['due_date'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label for="return_date">Return date</label>
                <input type="datetime-local" id="return_date" name="return_date"
                    value="<?= loan_datetime_input($loan['return_date'] ?? '') ?>">
            </div>

            <div class="form-field">
                <label for="loan_status">Loan status *</label>
                <select id="loan_status" name="loan_status" required>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= ($loan['loan_status'] ?? 'Ongoing') === $s ? 'selected' : '' ?>>
                            <?= e($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="late_fine_amount">Late fine amount *</label>
                <input type="number" id="late_fine_amount" name="late_fine_amount" min="0" step="0.01" required
                    value="<?= loan_field('late_fine_amount', $loan) ?>">
            </div>

            <div class="form-field">
                <label for="renewal_count">Renewal count *</label>
                <input type="number" id="renewal_count" name="renewal_count" min="0" step="1" required
                    value="<?= loan_field('renewal_count', $loan) ?>">
            </div>

            <div class="form-field">
                <label for="recorded_by_staff">Recorded by staff *</label>
                <input id="recorded_by_staff" name="recorded_by_staff" maxlength="60" required
                    value="<?= loan_field('recorded_by_staff', $loan) ?>">
            </div>
        </div>

        <div class="form-actions">
            <a class="button button-outline" href="index.php">Cancel</a>
            <button class="button button-primary" type="submit">
                <?= $isEdit ? 'Save changes' : 'Create loan record' ?>
            </button>
        </div>
    </form>
</section>

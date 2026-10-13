<?php
$membershipTypes = ['Student', 'Faculty', 'Regular', 'Senior', 'Corporate'];
$statuses = ['Active', 'Suspended', 'Expired', 'Pending'];

$branches = mysqli_query(
    $conn,
    'SELECT branch_id, branch_name FROM branches ORDER BY branch_name'
);

function member_field_value($key, $member)
{
    return e($member[$key] ?? '');
}
?>

<section class="panel form-panel">
    <div class="panel-heading">
        <div>
            <div class="eyebrow">MEMBER MANAGEMENT</div>
            <h2><?= $isEdit ? 'Edit member details' : 'Add a new member' ?></h2>
            <p>Fields marked with an asterisk are required.</p>
        </div>
        <a class="button button-outline" href="index.php">
            ← Back to members
        </a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>




    <form method="post" class="record-form" data-confirm-message="<?= $isEdit
        ? 'Save these changes to this member record?'
        : 'Add this member to the library records?' ?>" data-confirm-title="<?= $isEdit
          ? 'Confirm member changes'
          : 'Confirm new member' ?>" data-confirm-action="<?= $isEdit
            ? 'Save changes'
            : 'Add member' ?>">

        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
        <?php endif; ?>




        <div class="form-grid">
            <div class="form-field">
                <label for="membership_number">Membership number *</label>
                <input id="membership_number" name="membership_number" maxlength="30" required
                    value="<?= member_field_value('membership_number', $member) ?>">
            </div>

            <div class="form-field">
                <label for="first_name">First name *</label>
                <input id="first_name" name="first_name" maxlength="50" required
                    value="<?= member_field_value('first_name', $member) ?>">
            </div>

            <div class="form-field">
                <label for="last_name">Last name *</label>
                <input id="last_name" name="last_name" maxlength="50" required
                    value="<?= member_field_value('last_name', $member) ?>">
            </div>

            <div class="form-field">
                <label for="email">Email address *</label>
                <input id="email" name="email" type="email" maxlength="100" required
                    value="<?= member_field_value('email', $member) ?>">
            </div>

            <div class="form-field">
                <label for="phone_number">Phone number *</label>
                <input id="phone_number" name="phone_number" maxlength="25" required
                    value="<?= member_field_value('phone_number', $member) ?>">
            </div>

            <div class="form-field">
                <label for="home_branch_id">Home branch *</label>
                <select id="home_branch_id" name="home_branch_id" required>
                    <option value="">Choose branch</option>
                    <?php if ($branches): ?>
                        <?php while ($branch = mysqli_fetch_assoc($branches)): ?>
                            <option value="<?= (int) $branch['branch_id'] ?>" <?= (string) ($member['home_branch_id'] ?? '') ===
                                   (string) $branch['branch_id'] ? 'selected' : '' ?>>
                                <?= e($branch['branch_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="membership_type">Membership type *</label>
                <select id="membership_type" name="membership_type" required>
                    <?php foreach ($membershipTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($member['membership_type'] ?? '') === $type
                              ? 'selected' : '' ?>>
                            <?= e($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="account_status">Account status *</label>
                <select id="account_status" name="account_status" required>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($member['account_status'] ?? '') === $status
                              ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="max_borrow_limit">Maximum borrowing limit *</label>
                <input id="max_borrow_limit" name="max_borrow_limit" type="number" min="1" required
                    value="<?= member_field_value('max_borrow_limit', $member) ?>">
            </div>

            <div class="form-field">
                <label for="registration_date">Registration date *</label>
                <input id="registration_date" name="registration_date" type="date" required
                    value="<?= member_field_value('registration_date', $member) ?>">
            </div>

            <div class="form-field">
                <label for="expiration_date">Expiration date *</label>
                <input id="expiration_date" name="expiration_date" type="date" required
                    value="<?= member_field_value('expiration_date', $member) ?>">
            </div>
        </div>

        <div class="form-actions">
            <a class="button button-outline" href="index.php">Cancel</a>
            <button class="button button-primary" type="submit">
                <?= $isEdit ? 'Save changes' : 'Create member record' ?>
            </button>
        </div>
    </form>
</section>

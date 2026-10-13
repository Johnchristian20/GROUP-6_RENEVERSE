<?php
function branch_input_values(array $input): array
{
  $keys = ['branch_code', 'branch_name', 'street_address', 'city', 'state_province', 'postal_code', 'contact_phone', 'contact_email', 'opening_time', 'closing_time', 'capacity', 'established_date'];
  $values = [];
  foreach ($keys as $key)
    $values[$key] = trim((string) ($input[$key] ?? ''));
  return $values;
}
function branch_validate(array $v): string
{
  foreach ($v as $value)
    if ($value === '')
      return 'Please complete every required branch field.';
  if (!preg_match('/^[A-Za-z0-9_-]{2,20}$/', $v['branch_code']))
    return 'Branch code must be 2–20 letters, numbers, dashes, or underscores.';
  if (!filter_var($v['contact_email'], FILTER_VALIDATE_EMAIL))
    return 'Enter a valid branch email address.';
  if (!ctype_digit($v['capacity']) || (int) $v['capacity'] < 1)
    return 'Capacity must be a positive whole number.';
  if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $v['opening_time']) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $v['closing_time']))
    return 'Enter valid opening and closing times.';
  if (strtotime($v['opening_time']) >= strtotime($v['closing_time']))
    return 'Closing time must be later than opening time.';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['established_date']) || !checkdate((int) substr($v['established_date'], 5, 2), (int) substr($v['established_date'], 8, 2), (int) substr($v['established_date'], 0, 4)))
    return 'Enter a valid establishment date.';
  $limits = ['branch_name' => 100, 'street_address' => 150, 'city' => 80, 'state_province' => 80, 'postal_code' => 20, 'contact_phone' => 25];
  foreach ($limits as $key => $limit)
    if (strlen($v[$key]) > $limit)
      return 'One or more fields exceed their allowed length.';
  return '';
}
function branch_render_form(array $values, string $error, bool $isEdit): void
{
  $e = 'e';
  ?>
  <section class="panel">
    <div class="panel-heading">
      <div>
        <div class="eyebrow">BRANCH DIRECTORY</div>
        <h2><?= $isEdit ? 'Edit branch' : 'Add a branch' ?></h2>
        <p>Keep branch locations and contact details up to date.</p>
      </div><a class="button button-outline" href="index.php">Back to branches</a>
    </div>
    <?php if ($error !== ''): ?>
      <div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="branch-form"><input type="hidden" name="csrf_token"
        value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="form-grid">
        <?php foreach ([['branch_code', 'Branch code'], ['branch_name', 'Branch name'], ['street_address', 'Street address'], ['city', 'City'], ['state_province', 'Province / state'], ['postal_code', 'Postal code'], ['contact_phone', 'Phone'], ['contact_email', 'Email'], ['opening_time', 'Opening time'], ['closing_time', 'Closing time'], ['capacity', 'Capacity'], ['established_date', 'Established date']] as [$name, $label]): ?>
          <div class="form-field"><label for="<?= $name ?>"><?= e($label) ?></label><input id="<?= $name ?>" name="<?= $name ?>"
              value="<?= e($values[$name] ?? '') ?>"
              <?= $name === 'contact_email' ? 'type="email"' : ($name === 'opening_time' || $name === 'closing_time' ? 'type="time"' : ($name === 'capacity' ? 'type="number" min="1"' : ($name === 'established_date' ? 'type="date"' : ''))) ?> required
              maxlength="<?= in_array($name, ['branch_code', 'branch_name', 'street_address', 'city', 'state_province', 'postal_code', 'contact_phone', 'contact_email'], true) ? (['branch_code' => 20, 'branch_name' => 100, 'street_address' => 150, 'city' => 80, 'state_province' => 80, 'postal_code' => 20, 'contact_phone' => 25, 'contact_email' => 100][$name]) : '' ?>">
          </div>
        <?php endforeach; ?>
      </div>
      <div class="form-actions"><a class="button button-outline" href="index.php">Cancel</a><button
          class="button button-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Add branch' ?></button></div>
    </form>
  </section>
  <?php
}
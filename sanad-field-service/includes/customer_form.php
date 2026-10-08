<?php
declare(strict_types=1);

/**
 * Shared customer form (customer_new.php / customer_edit.php).
 */
function customer_form(array $c): void
{
    $v = static fn (string $k, string $d = ''): string => (string) ($c[$k] ?? $d);
    ?>
    <div class="form-grid">
      <div class="form-col">
        <label class="field-label" for="name"><?= e(t('common.name')) ?> *</label>
        <input class="input" id="name" name="name" required value="<?= e($v('name')) ?>" placeholder="<?= e(t('customer.name_ph')) ?>">

        <label class="field-label" for="type"><?= e(t('common.category')) ?></label>
        <select class="input input-select" id="type" name="type">
          <?php foreach (customer_types() as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $v('type', 'company') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="field-label" for="phone"><?= e(t('common.phone')) ?></label>
        <input class="input" id="phone" name="phone" value="<?= e($v('phone')) ?>">

        <label class="field-label" for="email"><?= e(t('common.email')) ?></label>
        <input class="input" type="email" id="email" name="email" value="<?= e($v('email')) ?>">

        <label class="field-label" for="tax_no"><?= e(t('customer.tax_no')) ?></label>
        <input class="input" id="tax_no" name="tax_no" value="<?= e($v('tax_no')) ?>">
      </div>

      <div class="form-col">
        <label class="field-label" for="address"><?= e(t('common.address')) ?></label>
        <input class="input" id="address" name="address" value="<?= e($v('address')) ?>">

        <label class="field-label" for="city"><?= e(t('common.city')) ?></label>
        <input class="input" id="city" name="city" value="<?= e($v('city')) ?>">

        <label class="field-label" for="notes"><?= e(t('common.notes')) ?></label>
        <textarea class="input" id="notes" name="notes" rows="6"><?= e($v('notes')) ?></textarea>
      </div>
    </div>
    <?php
}

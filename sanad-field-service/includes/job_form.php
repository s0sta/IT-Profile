<?php
declare(strict_types=1);

/**
 * Shared job form (used by job_new.php and job_edit.php).
 * Expects $job (existing row or []), $customers, $sites, $services, $technicians, $prefix.
 */
function job_form(array $job, array $customers, array $sites, array $services, array $technicians): void
{
    $v = static function (string $key, string $default = '') use ($job): string {
        return (string) ($job[$key] ?? $default);
    };
    ?>
    <div class="form-grid">
      <div class="form-col">
        <label class="field-label" for="customer_id"><?= e(t('job.customer')) ?> *</label>
        <select class="input input-select" id="customer_id" name="customer_id" required data-customer-select>
          <option value=""><?= e(t('common.select')) ?></option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $v('customer_id') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="field-label" for="site_id"><?= e(t('job.site')) ?></label>
        <select class="input input-select" id="site_id" name="site_id" data-site-select>
          <option value=""><?= e(t('job.no_site')) ?></option>
          <?php foreach ($sites as $s): ?>
            <option value="<?= (int) $s['id'] ?>" data-customer="<?= (int) $s['customer_id'] ?>" <?= (int) $v('site_id') === (int) $s['id'] ? 'selected' : '' ?>>
              <?= e($s['customer_name'] . ' — ' . $s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label class="field-label" for="service_id"><?= e(t('job.service')) ?></label>
        <select class="input input-select" id="service_id" name="service_id">
          <option value=""><?= e(t('job.no_service')) ?></option>
          <?php foreach ($services as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= (int) $v('service_id') === (int) $s['id'] ? 'selected' : '' ?>>
              <?= e($s['name']) ?> — <?= e(money((float) $s['price'])) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label class="field-label" for="priority"><?= e(t('common.priority')) ?></label>
        <select class="input input-select" id="priority" name="priority">
          <?php foreach (job_priorities() as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $v('priority', 'normal') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="field-label" for="assigned_to"><?= e(t('job.technician')) ?></label>
        <select class="input input-select" id="assigned_to" name="assigned_to">
          <option value=""><?= e(t('job.unassigned')) ?></option>
          <?php foreach ($technicians as $tech): ?>
            <option value="<?= (int) $tech['id'] ?>" <?= (int) $v('assigned_to') === (int) $tech['id'] ? 'selected' : '' ?>><?= e($tech['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-col">
        <label class="field-label" for="scheduled_date"><?= e(t('job.date')) ?></label>
        <input class="input" id="scheduled_date" name="scheduled_date" type="date" value="<?= e($v('scheduled_date')) ?>">

        <div class="form-row">
          <div>
            <label class="field-label" for="window_start"><?= e(t('job.window_start')) ?></label>
            <input class="input" id="window_start" name="window_start" type="time" value="<?= e(fmt_time($v('window_start'))) ?>">
          </div>
          <div>
            <label class="field-label" for="window_end"><?= e(t('job.window_end')) ?></label>
            <input class="input" id="window_end" name="window_end" type="time" value="<?= e(fmt_time($v('window_end'))) ?>">
          </div>
        </div>

        <label class="field-label" for="title"><?= e(t('job.title')) ?> *</label>
        <input class="input" id="title" name="title" required value="<?= e($v('title')) ?>" placeholder="<?= e(t('job.title_ph')) ?>">

        <label class="field-label" for="description"><?= e(t('job.description')) ?></label>
        <textarea class="input" id="description" name="description" rows="5" placeholder="<?= e(t('job.description_ph')) ?>"><?= e($v('description')) ?></textarea>

        <label class="field-label" for="internal_notes"><?= e(t('job.internal_notes')) ?></label>
        <textarea class="input" id="internal_notes" name="internal_notes" rows="3" placeholder="<?= e(t('job.internal_notes_ph')) ?>"><?= e($v('internal_notes')) ?></textarea>
      </div>
    </div>
    <script>
    (function () {
      var cust = document.querySelector('[data-customer-select]');
      var site = document.querySelector('[data-site-select]');
      if (!cust || !site) { return; }
      var all = Array.prototype.slice.call(site.options);
      function sync() {
        var id = cust.value;
        site.innerHTML = '';
        all.forEach(function (opt) {
          if (!id || !opt.dataset.customer || opt.dataset.customer === id || opt.value === '') {
            site.appendChild(opt.cloneNode(true));
          }
        });
      }
      cust.addEventListener('change', sync);
      if (cust.value) { sync(); }
    })();
    </script>
    <?php
}

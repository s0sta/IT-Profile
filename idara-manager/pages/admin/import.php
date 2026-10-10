<?php
declare(strict_types=1);

/**
 * Admin · Import tasks (CSV) — bulk-create tasks from an uploaded CSV file.
 * Columns (first row, exact lowercase): title, description, priority,
 * assignee_username, department_code, due_date, start_date.
 * Route guard: admin only.
 */

$me = Auth::current();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $file = $_FILES['csv'] ?? null;
    $err  = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
    $tmp  = is_array($file) ? (string) ($file['tmp_name'] ?? '') : '';

    if (!$file || $err !== UPLOAD_ERR_OK || $tmp === '') {
        flash('error', t('imp.err_file'));
        redirect('admin/import');
    }

    if (!is_file($tmp) || !is_readable($tmp)) {
        flash('error', t('imp.err_file'));
        redirect('admin/import');
    }

    $fh = fopen($tmp, 'rb');
    if ($fh === false) {
        flash('error', t('imp.err_file'));
        redirect('admin/import');
    }

    $headerRow = fgetcsv($fh, null, ',', '"', '\\');
    if (!is_array($headerRow)) {
        fclose($fh);
        flash('error', t('imp.err_cols'));
        redirect('admin/import');
    }

    $headerRow = array_map(static fn($cell): string => trim((string) ($cell ?? '')), $headerRow);
    if (isset($headerRow[0])) {
        // Strip a UTF-8 BOM from the first header cell.
        $headerRow[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $headerRow[0]);
    }

    $required = ['title', 'description', 'priority', 'assignee_username', 'department_code', 'due_date', 'start_date'];
    foreach ($required as $col) {
        if (!in_array($col, $headerRow, true)) {
            fclose($fh);
            flash('error', t('imp.err_cols'));
            redirect('admin/import');
        }
    }
    $colIdx = array_flip($headerRow);

    $priorityKeys = array_keys(priorities());

    $deptMap = [];
    foreach (Departments::all() as $dept) {
        $code = strtolower(trim((string) ($dept['code'] ?? '')));
        if ($code !== '') {
            $deptMap[$code] = (int) $dept['id'];
        }
    }

    $ok      = 0;
    $skipped = 0;

    while (($row = fgetcsv($fh, null, ',', '"', '\\')) !== false) {
        if (!is_array($row)) {
            continue;
        }
        $cells = array_map(static fn($cell): string => trim((string) ($cell ?? '')), $row);
        if ($cells === [] || implode('', $cells) === '') {
            $skipped++; // fully empty row
            continue;
        }

        $title = $cells[$colIdx['title']] ?? '';
        if ($title === '' || mb_strlen($title) > 250) {
            $skipped++;
            continue;
        }
        $description = $cells[$colIdx['description']] ?? '';

        $priority = strtolower(trim($cells[$colIdx['priority']] ?? ''));
        if (!in_array($priority, $priorityKeys, true)) {
            $priority = 'medium';
        }

        $login      = $cells[$colIdx['assignee_username']] ?? '';
        $assignee   = $login !== '' ? Users::byLogin($login) : null; // byLogin matches active users only
        $assigneeId = $assignee !== null ? (int) $assignee['id'] : null;

        $deptCode     = strtolower(trim($cells[$colIdx['department_code']] ?? ''));
        $departmentId = ($deptCode !== '' && isset($deptMap[$deptCode])) ? $deptMap[$deptCode] : null;

        $startRaw  = $cells[$colIdx['start_date']] ?? '';
        $startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startRaw) === 1 ? $startRaw : null;

        $dueRaw  = $cells[$colIdx['due_date']] ?? '';
        $dueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueRaw) === 1 ? $dueRaw : null;

        Tasks::create([
            'title'         => $title,
            'description'   => $description,
            'category_id'   => null,
            'priority'      => $priority,
            'assignee_id'   => $assigneeId,
            'department_id' => $departmentId,
            'start_date'    => $startDate,
            'due_date'      => $dueDate,
        ], $me);

        $ok++;
    }
    fclose($fh);

    flash('success', t('imp.ok', ['n' => $ok]));
    if ($skipped) {
        flash('warning', t('imp.skipped', ['n' => $skipped]));
    }
    redirect('admin/import');
}

$sampleCsv = "title,description,priority,assignee_username,department_code,due_date,start_date\n"
    . 'إعداد تقرير الربع الأول,تجهيز بيانات الربع الأول وعرضها على الإدارة,medium,ahmed,IT,2026-12-15,2026-12-01';

layout_header(t('imp.title'), 'admin/import');
?>

<section class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <h2 class="panel-title"><?= e(t('imp.title')) ?></h2>
      <a class="link" href="<?= u('tasks') ?>"><?= e(t('common.back')) ?></a>
    </div>
    <p class="muted-text"><?= e(t('imp.hint')) ?></p>
    <form method="post" action="<?= u('admin/import') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label class="field-label" for="csv"><?= e(t('common.import')) ?></label>
      <input class="input" type="file" id="csv" name="csv" accept=".csv" required>
      <button class="btn btn-primary" type="submit"><?= e(t('common.import')) ?></button>
    </form>
  </div>

  <aside class="panel panel-muted">
    <details>
      <summary><?= e(t('imp.download_sample')) ?></summary>
      <pre><?= e($sampleCsv) ?></pre>
    </details>
  </aside>
</section>

<?php layout_footer(); ?>

<?php
declare(strict_types=1);

/**
 * Idara — delegation of approval authority.
 * ?p=delegations
 *
 * A user may only delegate their OWN authority: non-admins always get
 * delegator_id = themselves (an admin may delegate on behalf of an approver).
 * Cancelling requires being the delegator (or an admin).
 */

$me = Auth::current();
if (!$me) {
    redirect('login');
}
$isAdmin = Auth::isAdmin();
$today   = date('Y-m-d');

/** True for a real, strictly formatted YYYY-MM-DD date. */
$isDate = static function (string $d): bool {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $delegatorId = $isAdmin ? (int) ($_POST['delegator_id'] ?? 0) : (int) $me['id'];
        $delegateId  = (int) ($_POST['delegate_id'] ?? 0);
        $starts      = trim((string) ($_POST['starts_at'] ?? ''));
        $ends        = trim((string) ($_POST['ends_at'] ?? ''));
        $reason      = trim((string) ($_POST['reason'] ?? ''));

        $validDelegate = false;
        foreach (Users::all(true) as $u) {
            if ((int) $u['id'] === $delegateId) {
                $validDelegate = true;
                break;
            }
        }

        if ($delegatorId <= 0 || $delegateId <= 0 || !$validDelegate) {
            // No dedicated key exists for an unknown delegate; treat it as forbidden input.
            flash('error', t('auth.denied'));
        } elseif ($delegateId === $delegatorId) {
            flash('error', t('deleg.err_same'));
        } elseif (!$isDate($starts) || !$isDate($ends) || $starts > $ends) {
            flash('error', t('deleg.err_dates'));
        } else {
            Delegations::create([
                'delegator_id' => $delegatorId,
                'delegate_id'  => $delegateId,
                'starts_at'    => $starts,
                'ends_at'      => $ends,
                'reason'       => $reason,
            ]);
            flash('success', t('deleg.created_ok'));
        }
        redirect('delegations');
    }

    if ($action === 'cancel') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $id > 0 ? Database::one('SELECT * FROM delegations WHERE id = ?', [$id]) : null;
        if ($row && ($isAdmin || (int) $row['delegator_id'] === (int) $me['id'])) {
            Delegations::cancel($id);
            flash('success', t('deleg.cancelled_ok'));
        } else {
            flash('error', t('auth.denied'));
        }
        redirect('delegations');
    }

    redirect('delegations');
}

$mine = Delegations::allFor((int) $me['id']);
$all  = $isAdmin ? Delegations::listAll() : [];

/** One row of the delegation table. */
$renderRow = static function (array $d, bool $canCancel, string $today): void {
    $live = !empty($d['active']) && (string) $d['starts_at'] <= $today && (string) $d['ends_at'] >= $today;
    // Four distinct states: scheduled (future) · in force today · expired · cancelled.
    if (empty($d['active'])) {
        $stateLabel = t('deleg.cancelled');
    } elseif ($live) {
        $stateLabel = t('deleg.active');
    } elseif ((string) $d['starts_at'] > $today) {
        $stateLabel = t('deleg.scheduled');
    } else {
        $stateLabel = t('deleg.expired');
    }
    $stateClass = $live ? 'badge-active' : 'badge-inactive';
    ?>
    <tr>
      <td><?= e($d['delegator_name'] ?? '—') ?></td>
      <td><?= e($d['delegate_name'] ?? '—') ?></td>
      <td><?= e(fmt_date($d['starts_at'])) ?></td>
      <td><?= e(fmt_date($d['ends_at'])) ?></td>
      <td><?= $d['reason'] ? e(excerpt((string) $d['reason'], 90)) : '<span class="cell-muted">—</span>' ?></td>
      <td>
        <span class="badge <?= e($stateClass) ?>"><?= e($stateLabel) ?></span>
      </td>
      <td>
        <?php if ($canCancel && !empty($d['active'])): ?>
          <form method="post" action="<?= u('delegations') ?>" class="inline-form" data-confirm="<?= e(t('deleg.confirm_cancel')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <button class="btn btn-sm btn-ghost" type="submit" data-confirm="<?= e(t('deleg.confirm_cancel')) ?>"><?= e(t('deleg.cancel')) ?></button>
          </form>
        <?php else: ?>
          <span class="cell-muted">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php
};

layout_header(t('deleg.title'), 'delegations');
?>

<section class="panel">
  <h2 class="panel-title"><?= e(t('deleg.new')) ?></h2>
  <form method="post" action="<?= u('delegations') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">

    <div class="row-2">
      <div>
        <label class="field-label" for="delegator_id"><?= e(t('deleg.delegator')) ?></label>
        <?php if ($isAdmin): ?>
          <select class="input" id="delegator_id" name="delegator_id" required>
            <?php foreach (Users::approvers() as $u): ?>
              <option value="<?= (int) $u['id'] ?>"<?= (int) $u['id'] === (int) $me['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input class="input" id="delegator_id" type="text" value="<?= e($me['name']) ?>" disabled>
          <input type="hidden" name="delegator_id" value="<?= (int) $me['id'] ?>">
        <?php endif; ?>
      </div>

      <div>
        <label class="field-label" for="delegate_id"><?= e(t('deleg.delegate')) ?></label>
        <select class="input" id="delegate_id" name="delegate_id" required>
          <option value=""><?= e(t('common.select')) ?></option>
          <?php foreach (Users::all(true) as $u): ?>
            <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?><?= $u['job_title'] ? ' — ' . e((string) $u['job_title']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row-2">
      <div>
        <label class="field-label" for="starts_at"><?= e(t('deleg.starts_at')) ?></label>
        <input class="input" id="starts_at" name="starts_at" type="date" required value="<?= e($today) ?>">
      </div>
      <div>
        <label class="field-label" for="ends_at"><?= e(t('deleg.ends_at')) ?></label>
        <input class="input" id="ends_at" name="ends_at" type="date" required value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>">
      </div>
    </div>

    <label class="field-label" for="reason"><?= e(t('deleg.reason')) ?> <span class="cell-muted">(<?= e(t('common.optional')) ?>)</span></label>
    <input class="input" id="reason" name="reason" type="text" maxlength="250">

    <p><button class="btn btn-primary" type="submit"><?= e(t('common.create')) ?></button></p>
  </form>
</section>

<section class="panel panel-muted">
  <p class="muted-text"><?= e(t('deleg.note')) ?></p>
</section>

<?php
// A single table per user: the administrator sees every delegation once
// (the "all" list); everyone else sees the rows they are part of.
$rows = $isAdmin ? $all : $mine;
$canCancel = static fn (array $d): bool => $isAdmin || (int) $d['delegator_id'] === (int) $me['id'];
?>
<section class="panel">
  <h2 class="panel-title"><?= e(t('deleg.title')) ?><?= $isAdmin ? ' — ' . e(t('common.all')) : '' ?></h2>
  <?php if (!$rows): ?>
    <div class="empty"><?= e(t('deleg.empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('deleg.delegator')) ?></th>
            <th><?= e(t('deleg.delegate')) ?></th>
            <th><?= e(t('deleg.starts_at')) ?></th>
            <th><?= e(t('deleg.ends_at')) ?></th>
            <th><?= e(t('common.reason')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $d) {
              $renderRow($d, $canCancel($d), $today);
          } ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>

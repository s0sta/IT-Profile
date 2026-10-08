<?php
declare(strict_types=1);

/**
 * Idara — notifications inbox. ?p=notifications
 * Mark-all-read is a POST that redirects (PRG); the list itself is read-only.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    Notifications::markAllRead(Auth::id());
    flash('success', t('notif.marked'));
    redirect('notifications');
}

$items = Notifications::forUser(Auth::id(), 50);

layout_header(t('notif.title'), 'notifications');
?>

<section class="panel">
  <div class="panel-head">
    <h2 class="panel-title"><?= e(t('notif.title')) ?></h2>
    <?php if ($items): ?>
      <form method="post" action="<?= u('notifications') ?>" class="inline-form">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" type="submit"><?= e(t('notif.mark_all')) ?></button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$items): ?>
    <div class="empty"><?= e(t('notif.empty')) ?></div>
  <?php else: ?>
    <ul class="notif-list">
      <?php foreach ($items as $n): ?>
        <li class="notif <?= $n['is_read'] ? '' : 'notif-unread' ?>">
          <span class="notif-dot"></span>
          <span class="notif-text">
            <?= $n['link'] ? '<a href="' . e((string) $n['link']) . '">' . e((string) $n['message']) . '</a>' : e((string) $n['message']) ?>
            <span class="cell-muted">· <?= e(time_ago($n['created_at'])) ?></span>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php layout_footer(); ?>

<?php
declare(strict_types=1);

/**
 * Idara — Manager Workspace · data layer.
 * One static class per entity. Every statement is prepared; dynamic ordering is whitelisted.
 *
 * Visibility model
 *  - admin / executive : everything
 *  - manager           : own tasks + tasks of direct reports + tasks of own department
 *  - member            : tasks assigned to them or created by them
 */

// ---------------------------------------------------------------- departments

final class Departments
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM departments';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        return Database::all($sql . ' ORDER BY sort_order ASC, name_ar ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM departments WHERE id = ?', [$id]);
    }

    public static function create(array $d): int
    {
        $sort = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM departments');
        $id = Database::insert(
            'INSERT INTO departments (name_ar, name_en, code, manager_id, active, sort_order) VALUES (?, ?, ?, ?, 1, ?)',
            [$d['name_ar'], $d['name_en'] ?? '', $d['code'] ?? '', $d['manager_id'] ?: null, $sort]
        );
        audit('department_created', 'department', $id, 'name=' . $d['name_ar']);
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE departments SET name_ar = ?, name_en = ?, code = ?, manager_id = ? WHERE id = ?',
            [$d['name_ar'], $d['name_en'] ?? '', $d['code'] ?? '', $d['manager_id'] ?: null, $id]
        );
        audit('department_updated', 'department', $id, 'name=' . $d['name_ar']);
    }

    public static function toggleActive(int $id): void
    {
        $d = self::find($id);
        if (!$d) {
            return;
        }
        Database::exec('UPDATE departments SET active = ? WHERE id = ?', [$d['active'] ? 0 : 1, $id]);
        audit('department_toggled', 'department', $id);
    }

    /** Department name in the current language. */
    public static function label(?array $dept): string
    {
        if (!$dept) {
            return '—';
        }
        return daem_current_lang() === 'ar'
            ? (string) $dept['name_ar']
            : (string) ($dept['name_en'] !== '' ? $dept['name_en'] : $dept['name_ar']);
    }
}

// ---------------------------------------------------------------- users

final class Users
{
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function byLogin(string $login): ?array
    {
        return Database::one(
            'SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1',
            [$login, $login]
        );
    }

    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT u.*, d.name_ar AS dept_name_ar, d.name_en AS dept_name_en
                FROM users u LEFT JOIN departments d ON d.id = u.department_id';
        if ($activeOnly) {
            $sql .= ' WHERE u.active = 1';
        }
        return Database::all($sql . ' ORDER BY u.name ASC');
    }

    public static function byRole(array $roles, bool $activeOnly = true): array
    {
        $ph = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT * FROM users WHERE role IN ($ph)";
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        return Database::all($sql . ' ORDER BY name ASC', array_values($roles));
    }

    /** Direct reports of a manager. */
    public static function team(int $managerId): array
    {
        return Database::all(
            'SELECT * FROM users WHERE manager_id = ? AND active = 1 ORDER BY name ASC',
            [$managerId]
        );
    }

    public static function approvers(): array
    {
        return self::byRole(['manager', 'executive', 'admin']);
    }

    /** ids a manager may look after: self + direct reports. */
    public static function scopeIds(array $user): array
    {
        if (in_array($user['role'], ['admin', 'executive'], true)) {
            return [];
        }
        $ids = [(int) $user['id']];
        foreach (self::team((int) $user['id']) as $m) {
            $ids[] = (int) $m['id'];
        }
        return $ids;
    }

    public static function create(array $d): int
    {
        $id = Database::insert(
            'INSERT INTO users (name, name_en, username, email, password_hash, role, department_id, job_title, phone, manager_id, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [
                $d['name'], $d['name_en'] ?? '', $d['username'], $d['email'],
                password_hash($d['password'], PASSWORD_DEFAULT), $d['role'],
                $d['department_id'] ?: null, $d['job_title'] ?? '', $d['phone'] ?? '',
                $d['manager_id'] ?: null, now(),
            ]
        );
        audit('user_created', 'user', $id, 'username=' . $d['username'] . ' role=' . $d['role']);
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE users SET name = ?, name_en = ?, username = ?, email = ?, role = ?, department_id = ?, job_title = ?, phone = ?, manager_id = ? WHERE id = ?',
            [
                $d['name'], $d['name_en'] ?? '', $d['username'], $d['email'], $d['role'],
                $d['department_id'] ?: null, $d['job_title'] ?? '', $d['phone'] ?? '',
                $d['manager_id'] ?: null, $id,
            ]
        );
        audit('user_updated', 'user', $id, 'username=' . $d['username'] . ' role=' . $d['role']);
    }

    public static function setPassword(int $id, string $plain): void
    {
        $extra = Database::hasColumn('users', 'must_change_password') ? ', must_change_password = 0' : '';
        Database::exec('UPDATE users SET password_hash = ?' . $extra . ' WHERE id = ?', [password_hash($plain, PASSWORD_DEFAULT), $id]);
        audit('user_password_reset', 'user', $id);
    }

    /**
     * Admin reset: generate a random one-time password, mark the account so the
     * user is forced to change it at the next sign-in, and return the password
     * (shown once, in the flash message).
     */
    public static function resetPassword(int $id): string
    {
        $plain = bin2hex(random_bytes(4)); // 8 hex characters
        $extra = Database::hasColumn('users', 'must_change_password') ? ', must_change_password = 1' : '';
        Database::exec('UPDATE users SET password_hash = ?' . $extra . ' WHERE id = ?', [password_hash($plain, PASSWORD_DEFAULT), $id]);
        audit('user_password_reset', 'user', $id);
        return $plain;
    }

    public static function toggleActive(int $id): void
    {
        $u = self::find($id);
        if (!$u || $u['role'] === 'admin') {
            return;
        }
        Database::exec('UPDATE users SET active = ? WHERE id = ?', [$u['active'] ? 0 : 1, $id]);
        audit($u['active'] ? 'user_deactivated' : 'user_activated', 'user', $id);
    }

    /** Display name in the current language (Arabic name is always set). */
    public static function label(?array $user): string
    {
        if (!$user) {
            return '—';
        }
        return (string) $user['name'];
    }
}

// ---------------------------------------------------------------- task categories

final class TaskCategories
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM task_categories';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        return Database::all($sql . ' ORDER BY sort_order ASC, name_ar ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM task_categories WHERE id = ?', [$id]);
    }

    public static function create(array $d): void
    {
        $sort = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM task_categories');
        $id = Database::insert(
            'INSERT INTO task_categories (name_ar, name_en, active, sort_order) VALUES (?, ?, 1, ?)',
            [$d['name_ar'], $d['name_en'] ?? '', $sort]
        );
        audit('task_category_created', 'task_category', $id, 'name=' . $d['name_ar']);
    }

    public static function update(int $id, array $d): void
    {
        Database::exec('UPDATE task_categories SET name_ar = ?, name_en = ? WHERE id = ?', [$d['name_ar'], $d['name_en'] ?? '', $id]);
        audit('task_category_updated', 'task_category', $id);
    }

    public static function toggleActive(int $id): void
    {
        $c = self::find($id);
        if (!$c) {
            return;
        }
        Database::exec('UPDATE task_categories SET active = ? WHERE id = ?', [$c['active'] ? 0 : 1, $id]);
    }

    public static function label(?array $cat): string
    {
        if (!$cat) {
            return '—';
        }
        return daem_current_lang() === 'ar'
            ? (string) $cat['name_ar']
            : (string) ($cat['name_en'] !== '' ? $cat['name_en'] : $cat['name_ar']);
    }
}

// ---------------------------------------------------------------- tasks

final class Tasks
{
    private static function selectSql(): string
    {
        return 'SELECT t.*,
                       a.name AS assignee_name, c.name AS creator_name,
                       cat.name_ar AS cat_name_ar, cat.name_en AS cat_name_en,
                       d.name_ar AS dept_name_ar, d.name_en AS dept_name_en
                FROM tasks t
                LEFT JOIN users a ON a.id = t.assignee_id
                LEFT JOIN users c ON c.id = t.creator_id
                LEFT JOIN task_categories cat ON cat.id = t.category_id
                LEFT JOIN departments d ON d.id = t.department_id';
    }

    public static function find(int $id): ?array
    {
        return Database::one(self::selectSql() . ' WHERE t.id = ?', [$id]);
    }

    public static function canView(array $task, array $me): bool
    {
        if (in_array($me['role'], ['admin', 'executive'], true)) {
            return true;
        }
        if ((int) $task['assignee_id'] === (int) $me['id'] || (int) $task['creator_id'] === (int) $me['id']) {
            return true;
        }
        if ($me['role'] === 'manager') {
            $scope = Users::scopeIds($me);
            if (in_array((int) $task['assignee_id'], $scope, true)) {
                return true;
            }
            if (!empty($me['department_id']) && (int) $task['department_id'] === (int) $me['department_id']) {
                return true;
            }
        }
        return false;
    }

    public static function canEdit(array $task, array $me): bool
    {
        if (in_array($me['role'], ['admin', 'executive'], true)) {
            return true;
        }
        if ((int) $task['assignee_id'] === (int) $me['id'] || (int) $task['creator_id'] === (int) $me['id']) {
            return true;
        }
        return $me['role'] === 'manager' && in_array((int) $task['assignee_id'], Users::scopeIds($me), true);
    }

    /** Only the creator (or admin/manager in scope) may reassign or delete. */
    public static function canManage(array $task, array $me): bool
    {
        if (in_array($me['role'], ['admin', 'executive'], true)) {
            return true;
        }
        if ((int) $task['creator_id'] === (int) $me['id']) {
            return true;
        }
        return $me['role'] === 'manager' && in_array((int) $task['assignee_id'], Users::scopeIds($me), true);
    }

    private static function scopeWhere(array $me, array &$params): string
    {
        if (in_array($me['role'], ['admin', 'executive'], true)) {
            return '';
        }
        if ($me['role'] === 'manager') {
            $scope = Users::scopeIds($me);
            $ph = implode(',', array_fill(0, count($scope), '?'));
            $params = array_merge($params, $scope);
            $w = "(t.assignee_id IN ($ph) OR t.creator_id = ?";
            $params[] = (int) $me['id'];
            if (!empty($me['department_id'])) {
                $w .= ' OR t.department_id = ?';
                $params[] = (int) $me['department_id'];
            }
            return $w . ')';
        }
        $params[] = (int) $me['id'];
        $params[] = (int) $me['id'];
        return '(t.assignee_id = ? OR t.creator_id = ?)';
    }

    private static function filterWhere(array $f, array &$params): array
    {
        $w = [];
        if (!empty($f['q'])) {
            $w[] = '(t.ref LIKE ? OR t.title LIKE ? OR t.description LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($f['status'])) {
            $w[] = 't.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['priority'])) {
            $w[] = 't.priority = ?';
            $params[] = $f['priority'];
        }
        if (!empty($f['category'])) {
            $w[] = 't.category_id = ?';
            $params[] = (int) $f['category'];
        }
        if (!empty($f['assignee'])) {
            $w[] = 't.assignee_id = ?';
            $params[] = (int) $f['assignee'];
        }
        if (!empty($f['department'])) {
            $w[] = 't.department_id = ?';
            $params[] = (int) $f['department'];
        }
        if (!empty($f['mine'])) {
            $w[] = 't.assignee_id = ?';
            $params[] = (int) Auth::id();
        }
        if (!empty($f['overdue'])) {
            $w[] = "t.due_date IS NOT NULL AND t.due_date < ? AND t.status NOT IN ('completed','cancelled')";
            $params[] = date('Y-m-d');
        }
        return $w;
    }

    public static function count(array $f, array $me): int
    {
        $params = [];
        $w = [];
        $scope = self::scopeWhere($me, $params);
        if ($scope !== '') {
            $w[] = $scope;
        }
        $w = array_merge($w, self::filterWhere($f, $params));
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return (int) Database::value('SELECT COUNT(*) FROM tasks t ' . $where, $params);
    }

    public static function list(array $f, array $me, int $limit = 20, int $offset = 0): array
    {
        $params = [];
        $w = [];
        $scope = self::scopeWhere($me, $params);
        if ($scope !== '') {
            $w[] = $scope;
        }
        $w = array_merge($w, self::filterWhere($f, $params));
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';

        $orderMap = [
            'updated'  => 't.updated_at DESC',
            'created'  => 't.created_at DESC',
            'due'      => 'CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date ASC',
            'priority' => "CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END ASC",
            'progress' => 't.progress DESC',
        ];
        $order = $orderMap[$f['sort'] ?? 'due'] ?? $orderMap['due'];

        return Database::all(
            self::selectSql() . ' ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    public static function create(array $d, array $me): int
    {
        $created = now();
        $id = Database::insert(
            'INSERT INTO tasks (title, description, category_id, priority, status, progress, creator_id, assignee_id, department_id, start_date, due_date, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['title'], $d['description'] ?? '', $d['category_id'] ?: null, $d['priority'], 'new',
                (int) $me['id'], $d['assignee_id'] ?: null, $d['department_id'] ?: null,
                $d['start_date'] ?: null, $d['due_date'] ?: null, $created, $created,
            ]
        );
        Database::exec('UPDATE tasks SET ref = ? WHERE id = ?', [self::makeRef($id), $id]);
        audit('task_created', 'task', $id, 'ref=' . self::makeRef($id) . ' priority=' . $d['priority']);

        if (!empty($d['assignee_id']) && (int) $d['assignee_id'] !== (int) $me['id']) {
            notify((int) $d['assignee_id'], t('notif.msg_task_assigned', ['title' => excerpt($d['title'], 60)]), u('task&id=' . $id));
        }
        return $id;
    }

    public static function makeRef(int $id): string
    {
        $prefix = setting('task_prefix', 'TSK');
        return strtoupper($prefix) . '-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE tasks SET title = ?, description = ?, category_id = ?, priority = ?, assignee_id = ?, department_id = ?, start_date = ?, due_date = ?, updated_at = ? WHERE id = ?',
            [
                $d['title'], $d['description'] ?? '', $d['category_id'] ?: null, $d['priority'],
                $d['assignee_id'] ?: null, $d['department_id'] ?: null,
                $d['start_date'] ?: null, $d['due_date'] ?: null, now(), $id,
            ]
        );
        audit('task_updated', 'task', $id);
    }

    public static function assign(int $id, ?int $assigneeId): void
    {
        $t = self::find($id);
        if (!$t) {
            return;
        }
        Database::exec('UPDATE tasks SET assignee_id = ?, updated_at = ? WHERE id = ?', [$assigneeId, now(), $id]);
        audit('task_assigned', 'task', $id, 'assignee=' . ($assigneeId ?: 'none'));
        if ($assigneeId && (int) $assigneeId !== Auth::id()) {
            notify($assigneeId, t('notif.msg_task_assigned', ['title' => excerpt((string) $t['title'], 60)]), u('task&id=' . $id));
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        $t = self::find($id);
        if (!$t) {
            return;
        }
        $progress = $t['progress'];
        if ($status === 'completed') {
            $progress = 100;
        } elseif ((string) $t['status'] === 'completed') {
            // Reopened: show what the checklist really says (or a partial bar
            // when there is no checklist), never a full 100 % bar.
            $done  = (int) Database::value('SELECT COUNT(*) FROM task_items WHERE task_id = ? AND is_done = 1', [$id]);
            $total = (int) Database::value('SELECT COUNT(*) FROM task_items WHERE task_id = ?', [$id]);
            $progress = $total > 0
                ? (int) round($done / $total * 100)
                : min(99, max(0, (int) $progress));
        }
        $sql = 'UPDATE tasks SET status = ?, progress = ?, updated_at = ?';
        $params = [$status, $progress, now()];
        if ($status === 'completed') {
            $sql .= ', completed_at = ?';
            $params[] = now();
        } else {
            $sql .= ', completed_at = NULL';
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        Database::exec($sql, $params);
        audit('task_status', 'task', $id, 'status=' . $status);

        if ((int) $t['creator_id'] !== Auth::id()) {
            notify((int) $t['creator_id'], t('notif.msg_task_status', ['title' => excerpt((string) $t['title'], 50), 'status' => task_statuses()[$status] ?? $status]), u('task&id=' . $id));
        }
    }

    public static function setProgress(int $id, int $progress): void
    {
        $progress = max(0, min(100, $progress));
        $t = self::find($id);
        if (!$t) {
            return;
        }
        $status = $t['status'];
        $extra = '';
        $params = [$progress];
        if ($progress === 100) {
            $status = 'completed';
            $extra = ', completed_at = ?';
        } elseif ($status === 'new' && $progress > 0) {
            $status = 'in_progress';
        }
        $params[] = $status;
        $params[] = now();
        if ($extra !== '') {
            $params[] = now();
        }
        $params[] = $id;
        Database::exec('UPDATE tasks SET progress = ?, status = ?, updated_at = ?' . $extra . ' WHERE id = ?', $params);
        audit('task_progress', 'task', $id, 'progress=' . $progress);
    }

    // ------------------------------------------------------------ checklist

    public static function items(int $taskId): array
    {
        return Database::all('SELECT * FROM task_items WHERE task_id = ? ORDER BY sort_order ASC, id ASC', [$taskId]);
    }

    public static function addItem(int $taskId, string $title): void
    {
        $sort = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM task_items WHERE task_id = ?', [$taskId]);
        $id = Database::insert(
            'INSERT INTO task_items (task_id, title, is_done, sort_order, created_at) VALUES (?, ?, 0, ?, ?)',
            [$taskId, $title, $sort, now()]
        );
        audit('task_item_added', 'task', $taskId, 'item=' . $id);
    }

    public static function toggleItem(int $itemId): void
    {
        $item = Database::one('SELECT * FROM task_items WHERE id = ?', [$itemId]);
        if (!$item) {
            return;
        }
        Database::exec('UPDATE task_items SET is_done = ? WHERE id = ?', [$item['is_done'] ? 0 : 1, $itemId]);
        self::syncProgressFromChecklist((int) $item['task_id']);
    }

    public static function deleteItem(int $itemId): void
    {
        $item = Database::one('SELECT * FROM task_items WHERE id = ?', [$itemId]);
        if (!$item) {
            return;
        }
        Database::exec('DELETE FROM task_items WHERE id = ?', [$itemId]);
        self::syncProgressFromChecklist((int) $item['task_id']);
    }

    /** When a checklist exists, progress follows the checklist. */
    public static function syncProgressFromChecklist(int $taskId): void
    {
        $total = (int) Database::value('SELECT COUNT(*) FROM task_items WHERE task_id = ?', [$taskId]);
        if ($total === 0) {
            return;
        }
        $done = (int) Database::value('SELECT COUNT(*) FROM task_items WHERE task_id = ? AND is_done = 1', [$taskId]);
        self::setProgress($taskId, (int) round($done / $total * 100));
    }

    // ------------------------------------------------------------ updates (comments)

    public static function updates(int $taskId): array
    {
        return Database::all(
            'SELECT tu.*, u.name AS author_name, u.role AS author_role
             FROM task_updates tu LEFT JOIN users u ON u.id = tu.author_id
             WHERE tu.task_id = ? ORDER BY tu.created_at ASC, tu.id ASC',
            [$taskId]
        );
    }

    public static function addUpdate(int $taskId, string $body, ?int $progress): int
    {
        $id = Database::insert(
            'INSERT INTO task_updates (task_id, author_id, body, progress, created_at) VALUES (?, ?, ?, ?, ?)',
            [$taskId, Auth::id(), $body, $progress, now()]
        );
        if ($progress !== null) {
            self::setProgress($taskId, $progress);
        } else {
            Database::exec('UPDATE tasks SET updated_at = ? WHERE id = ?', [now(), $taskId]);
        }
        audit('task_update_added', 'task', $taskId, 'update=' . $id);

        $task = self::find($taskId);
        if ($task) {
            foreach (array_unique([(int) $task['creator_id'], (int) $task['assignee_id']]) as $uid) {
                if ($uid && $uid !== Auth::id()) {
                    notify($uid, t('notif.msg_task_comment', ['title' => excerpt((string) $task['title'], 50)]), u('task&id=' . $taskId));
                }
            }
        }
        return $id;
    }

    // ------------------------------------------------------------ stats

    public static function statsFor(array $me): array
    {
        $params = [];
        $scope = self::scopeWhere($me, $params);
        $w = $scope !== '' ? 'WHERE ' . $scope : '';
        $row = Database::one(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) AS open_now,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN due_date IS NOT NULL AND due_date < ? AND status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) AS overdue
             FROM tasks t " . $w,
            array_merge([date('Y-m-d')], $params)
        );
        return [
            'total'       => (int) ($row['total'] ?? 0),
            'open'        => (int) ($row['open_now'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'waiting'     => (int) ($row['waiting'] ?? 0),
            'blocked'     => (int) ($row['blocked'] ?? 0),
            'completed'   => (int) ($row['completed'] ?? 0),
            'overdue'     => (int) ($row['overdue'] ?? 0),
        ];
    }

    public static function dueSoon(array $me, int $days = 7, int $limit = 8): array
    {
        $params = [];
        $scope = self::scopeWhere($me, $params);
        $w = ["t.due_date IS NOT NULL AND t.due_date >= ? AND t.due_date <= ? AND t.status NOT IN ('completed','cancelled')"];
        array_unshift($params, date('Y-m-d'), date('Y-m-d', strtotime('+' . $days . ' days')));
        if ($scope !== '') {
            $w[] = $scope;
        }
        return Database::all(
            self::selectSql() . ' WHERE ' . implode(' AND ', $w) . ' ORDER BY t.due_date ASC LIMIT ' . (int) $limit,
            $params
        );
    }

    public static function byPriority(array $me): array
    {
        $params = [];
        $scope = self::scopeWhere($me, $params);
        $w = $scope !== '' ? 'WHERE ' . $scope . " AND t.status NOT IN ('completed','cancelled')" : "WHERE t.status NOT IN ('completed','cancelled')";
        $rows = Database::all('SELECT priority, COUNT(*) AS c FROM tasks t ' . $w . ' GROUP BY priority', $params);
        $out = ['low' => 0, 'medium' => 0, 'high' => 0, 'urgent' => 0];
        foreach ($rows as $r) {
            $out[$r['priority']] = (int) $r['c'];
        }
        return $out;
    }

    /** Workload of the manager's team (open tasks per member). */
    public static function teamWorkload(array $me): array
    {
        if ($me['role'] === 'manager') {
            $scope = Users::scopeIds($me);
        } elseif (in_array($me['role'], ['admin', 'executive'], true)) {
            $scope = array_map(static fn ($u) => (int) $u['id'], Users::all(true));
        } else {
            $scope = [(int) $me['id']];
        }
        if (!$scope) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($scope), '?'));
        return Database::all(
            "SELECT u.id, u.name, u.role,
                    SUM(CASE WHEN t.status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) AS open_now,
                    SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < ? AND t.status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) AS overdue
             FROM users u LEFT JOIN tasks t ON t.assignee_id = u.id
             WHERE u.id IN ($ph)
             GROUP BY u.id, u.name, u.role
             ORDER BY open_now DESC, u.name ASC",
            array_merge([date('Y-m-d')], $scope)
        );
    }

    public static function reportRows(array $f, array $me): array
    {
        $params = [];
        $w = [];
        $scope = self::scopeWhere($me, $params);
        if ($scope !== '') {
            $w[] = $scope;
        }
        if (!empty($f['from'])) {
            $w[] = 't.created_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to'])) {
            $w[] = 't.created_at <= ?';
            $params[] = $f['to'] . ' 23:59:59';
        }
        if (!empty($f['status'])) {
            $w[] = 't.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['department'])) {
            $w[] = 't.department_id = ?';
            $params[] = (int) $f['department'];
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return Database::all(self::selectSql() . ' ' . $where . ' ORDER BY t.created_at ASC', $params);
    }
}

// ---------------------------------------------------------------- approvals

final class ApprovalTypes
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM approval_types';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        return Database::all($sql . ' ORDER BY sort_order ASC, name_ar ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM approval_types WHERE id = ?', [$id]);
    }

    public static function create(array $d): void
    {
        $sort = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM approval_types');
        $id = Database::insert(
            'INSERT INTO approval_types (name_ar, name_en, active, sort_order) VALUES (?, ?, 1, ?)',
            [$d['name_ar'], $d['name_en'] ?? '', $sort]
        );
        audit('approval_type_created', 'approval_type', $id, 'name=' . $d['name_ar']);
    }

    public static function update(int $id, array $d): void
    {
        Database::exec('UPDATE approval_types SET name_ar = ?, name_en = ? WHERE id = ?', [$d['name_ar'], $d['name_en'] ?? '', $id]);
        audit('approval_type_updated', 'approval_type', $id);
    }

    public static function toggleActive(int $id): void
    {
        $a = self::find($id);
        if (!$a) {
            return;
        }
        Database::exec('UPDATE approval_types SET active = ? WHERE id = ?', [$a['active'] ? 0 : 1, $id]);
    }

    public static function label(?array $type): string
    {
        if (!$type) {
            return '—';
        }
        return daem_current_lang() === 'ar'
            ? (string) $type['name_ar']
            : (string) ($type['name_en'] !== '' ? $type['name_en'] : $type['name_ar']);
    }
}

final class Approvals
{
    private static function selectSql(): string
    {
        return 'SELECT ap.*,
                       r.name AS requester_name,
                       ty.name_ar AS type_name_ar, ty.name_en AS type_name_en,
                       d.name_ar AS dept_name_ar, d.name_en AS dept_name_en,
                       (SELECT COUNT(*) FROM approval_steps s WHERE s.approval_id = ap.id) AS steps_total,
                       (SELECT s2.approver_id FROM approval_steps s2 WHERE s2.approval_id = ap.id AND s2.step_order = ap.current_step LIMIT 1) AS current_approver_id
                FROM approvals ap
                LEFT JOIN users r ON r.id = ap.requester_id
                LEFT JOIN approval_types ty ON ty.id = ap.type_id
                LEFT JOIN departments d ON d.id = ap.department_id';
    }

    public static function find(int $id): ?array
    {
        return Database::one(self::selectSql() . ' WHERE ap.id = ?', [$id]);
    }

    public static function steps(int $approvalId): array
    {
        return Database::all(
            'SELECT s.*, u.name AS approver_name, u.role AS approver_role
             FROM approval_steps s LEFT JOIN users u ON u.id = s.approver_id
             WHERE s.approval_id = ? ORDER BY s.step_order ASC',
            [$approvalId]
        );
    }

    public static function currentStep(int $approvalId): ?array
    {
        return Database::one(
            'SELECT s.*, u.name AS approver_name
             FROM approval_steps s LEFT JOIN users u ON u.id = s.approver_id
             WHERE s.approval_id = ? AND s.status = ? ORDER BY s.step_order ASC LIMIT 1',
            [$approvalId, 'pending']
        );
    }

    public static function makeRef(int $id): string
    {
        $prefix = setting('approval_prefix', 'APR');
        return strtoupper($prefix) . '-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    /** Create an approval with an ordered chain of approvers. */
    public static function create(array $d, array $approverIds, array $me): int
    {
        $created = now();
        $id = Database::insert(
            'INSERT INTO approvals (title, description, type_id, requester_id, department_id, priority, status, current_step, due_date, related_task_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)',
            [
                $d['title'], $d['description'] ?? '', $d['type_id'] ?: null, (int) $me['id'],
                $d['department_id'] ?: null, $d['priority'], 'pending',
                $d['due_date'] ?: null, $d['related_task_id'] ?: null, $created, $created,
            ]
        );
        Database::exec('UPDATE approvals SET ref = ? WHERE id = ?', [self::makeRef($id), $id]);

        $order = 1;
        foreach ($approverIds as $approverId) {
            $approverId = (int) $approverId;
            if ($approverId <= 0) {
                continue;
            }
            Database::exec(
                'INSERT INTO approval_steps (approval_id, step_order, approver_id, status, note, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $order, $approverId, $order === 1 ? 'pending' : 'waiting', '', $created]
            );
            $order++;
        }
        audit('approval_created', 'approval', $id, 'ref=' . self::makeRef($id));

        $first = self::currentStep($id);
        if ($first) {
            notify((int) $first['approver_id'], t('notif.msg_approval_pending', ['title' => excerpt($d['title'], 50)]), u('approval&id=' . $id));
        }
        return $id;
    }

    /** Who may decide the current step: the assigned approver, or their active delegate. */
    public static function canDecide(array $approval, int $userId): bool
    {
        if ($approval['status'] !== 'pending') {
            return false;
        }
        $step = self::currentStep((int) $approval['id']);
        if (!$step) {
            return false;
        }
        if ((int) $step['approver_id'] === $userId) {
            return true;
        }
        return Delegations::isDelegateFor($userId, (int) $step['approver_id']);
    }

    /** Approve / reject / return the current step, then advance or close. */
    public static function decide(int $approvalId, string $decision, string $note): void
    {
        $approval = self::find($approvalId);
        $step = self::currentStep($approvalId);
        if (!$approval || !$step) {
            return;
        }
        Database::exec(
            'UPDATE approval_steps SET status = ?, note = ?, decided_at = ? WHERE id = ?',
            [$decision, $note, now(), (int) $step['id']]
        );

        if ($decision === 'approved') {
            $next = Database::one(
                'SELECT * FROM approval_steps WHERE approval_id = ? AND status = ? ORDER BY step_order ASC LIMIT 1',
                [$approvalId, 'waiting']
            );
            if ($next) {
                Database::exec('UPDATE approval_steps SET status = ? WHERE id = ?', ['pending', (int) $next['id']]);
                Database::exec(
                    'UPDATE approvals SET current_step = ?, updated_at = ? WHERE id = ?',
                    [(int) $next['step_order'], now(), $approvalId]
                );
                notify((int) $next['approver_id'], t('notif.msg_approval_pending', ['title' => excerpt((string) $approval['title'], 50)]), u('approval&id=' . $approvalId));
            } else {
                Database::exec(
                    "UPDATE approvals SET status = 'approved', updated_at = ?, closed_at = ? WHERE id = ?",
                    [now(), now(), $approvalId]
                );
            }
        } else {
            // rejected or returned — the chain closes
            Database::exec(
                'UPDATE approval_steps SET status = ? WHERE approval_id = ? AND status IN (?, ?)',
                [$decision === 'rejected' ? 'skipped' : 'skipped', $approvalId, 'pending', 'waiting']
            );
            Database::exec(
                'UPDATE approvals SET status = ?, updated_at = ?, closed_at = ? WHERE id = ?',
                [$decision, now(), now(), $approvalId]
            );
        }

        audit('approval_' . $decision, 'approval', $approvalId, 'step=' . $step['step_order']);

        $msgKey = $decision === 'approved' ? 'notif.msg_approval_approved' : ($decision === 'rejected' ? 'notif.msg_approval_rejected' : 'notif.msg_approval_returned');
        if ((int) $approval['requester_id'] !== Auth::id()) {
            notify((int) $approval['requester_id'], t($msgKey, ['title' => excerpt((string) $approval['title'], 50)]), u('approval&id=' . $approvalId));
        }
    }

    /**
     * The requester (or an admin/executive) withdraws a pending request:
     * the chain closes, remaining steps are skipped and the current approver
     * is notified. Returns false when the request is not withdrawable.
     */
    public static function withdraw(int $approvalId, array $me): bool
    {
        $approval = self::find($approvalId);
        if (!$approval || $approval['status'] !== 'pending') {
            return false;
        }
        $privileged = in_array($me['role'], ['admin', 'executive'], true);
        if (!$privileged && (int) $approval['requester_id'] !== (int) $me['id']) {
            return false;
        }
        $current = self::currentStep($approvalId);
        Database::exec(
            "UPDATE approval_steps SET status = 'skipped' WHERE approval_id = ? AND status IN ('pending', 'waiting')",
            [$approvalId]
        );
        Database::exec(
            "UPDATE approvals SET status = 'cancelled', updated_at = ?, closed_at = ? WHERE id = ?",
            [now(), now(), $approvalId]
        );
        audit('approval_withdrawn', 'approval', $approvalId);
        if ($current) {
            notify((int) $current['approver_id'], t('notif.msg_approval_withdrawn', ['title' => excerpt((string) $approval['title'], 50)]), u('approval&id=' . $approvalId));
        }
        return true;
    }

    /** Ordered approver ids of the existing chain (skipped steps included). */
    private static function existingChainIds(int $approvalId): array
    {
        return array_map('intval', array_column(
            Database::all('SELECT approver_id FROM approval_steps WHERE approval_id = ? ORDER BY step_order ASC', [$approvalId]),
            'approver_id'
        ));
    }

    /**
     * The requester (or an admin/executive) edits a pending request. When the
     * approver chain changes, the steps are rebuilt from step 1 and the new
     * first approver is notified; otherwise the chain and its history stay.
     */
    public static function editRequest(int $approvalId, array $d, array $approverIds, array $me): bool
    {
        $approval = self::find($approvalId);
        if (!$approval || $approval['status'] !== 'pending') {
            return false;
        }
        $privileged = in_array($me['role'], ['admin', 'executive'], true);
        if (!$privileged && (int) $approval['requester_id'] !== (int) $me['id']) {
            return false;
        }
        Database::exec(
            'UPDATE approvals SET title = ?, description = ?, type_id = ?, priority = ?, due_date = ?, related_task_id = ?, updated_at = ? WHERE id = ?',
            [
                $d['title'], $d['description'] ?? '', $d['type_id'] ?: null, $d['priority'],
                $d['due_date'] ?: null, $d['related_task_id'] ?: null, now(), $approvalId,
            ]
        );

        $approverIds = array_values(array_unique(array_filter(array_map('intval', $approverIds), static fn (int $v): bool => $v > 0)));
        $chainChanged = $approverIds !== self::existingChainIds($approvalId);
        if ($chainChanged && $approverIds) {
            Database::exec('DELETE FROM approval_steps WHERE approval_id = ?', [$approvalId]);
            $order = 1;
            foreach ($approverIds as $approverId) {
                Database::exec(
                    'INSERT INTO approval_steps (approval_id, step_order, approver_id, status, note, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$approvalId, $order, $approverId, $order === 1 ? 'pending' : 'waiting', '', now()]
                );
                $order++;
            }
            Database::exec('UPDATE approvals SET current_step = 1 WHERE id = ?', [$approvalId]);
            $first = self::currentStep($approvalId);
            if ($first) {
                notify((int) $first['approver_id'], t('notif.msg_approval_pending', ['title' => excerpt((string) $d['title'], 50)]), u('approval&id=' . $approvalId));
            }
            audit('approval_chain_edited', 'approval', $approvalId, 'steps=' . count($approverIds));
        } else {
            audit('approval_edited', 'approval', $approvalId);
        }
        return true;
    }

    public static function canView(array $approval, array $me): bool
    {
        if (in_array($me['role'], ['admin', 'executive'], true)) {
            return true;
        }
        if ((int) $approval['requester_id'] === (int) $me['id']) {
            return true;
        }
        if ($me['role'] === 'manager') {
            $step = Database::one('SELECT id FROM approval_steps WHERE approval_id = ? AND approver_id = ? LIMIT 1', [(int) $approval['id'], (int) $me['id']]);
            if ($step) {
                return true;
            }
            if (!empty($me['department_id']) && (int) $approval['department_id'] === (int) $me['department_id']) {
                return true;
            }
        }
        return Delegations::isDelegateForAny((int) $me['id'], (int) $approval['id']);
    }

    private static function scopeWhere(array $me, array &$params): string
    {
        if (in_array($me['role'], ['admin', 'executive'], true)) {
            return '';
        }
        $uid   = (int) $me['id'];
        $today = date('Y-m-d');

        // Everyone involved sees the request: the requester, an approver in the
        // chain, or an active delegate acting for a chain approver.
        $clauses = [
            'ap.requester_id = ?',
            'EXISTS (SELECT 1 FROM approval_steps s WHERE s.approval_id = ap.id AND s.approver_id = ?)',
            'EXISTS (SELECT 1 FROM approval_steps s2 JOIN delegations dl ON dl.delegator_id = s2.approver_id
                     WHERE s2.approval_id = ap.id AND dl.delegate_id = ? AND dl.active = 1
                       AND dl.starts_at <= ? AND dl.ends_at >= ?)',
        ];
        $params = array_merge($params, [$uid, $uid, $uid, $today, $today]);

        if ($me['role'] === 'manager' && !empty($me['department_id'])) {
            $clauses[] = 'ap.department_id = ?';
            $params[]  = (int) $me['department_id'];
        }
        return '(' . implode(' OR ', $clauses) . ')';
    }

    public static function count(array $f, array $me): int
    {
        $params = [];
        $w = [];
        $scope = self::scopeWhere($me, $params);
        if ($scope !== '') {
            $w[] = $scope;
        }
        $w = array_merge($w, self::filterWhere($f, $params));
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return (int) Database::value('SELECT COUNT(*) FROM approvals ap ' . $where, $params);
    }

    private static function filterWhere(array $f, array &$params): array
    {
        $w = [];
        if (!empty($f['q'])) {
            $w[] = '(ap.ref LIKE ? OR ap.title LIKE ? OR ap.description LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($f['status'])) {
            $w[] = 'ap.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['type'])) {
            $w[] = 'ap.type_id = ?';
            $params[] = (int) $f['type'];
        }
        if (!empty($f['priority'])) {
            $w[] = 'ap.priority = ?';
            $params[] = $f['priority'];
        }
        if (!empty($f['inbox'])) {
            // pending steps assigned to me (or to someone who delegated to me)
            $w[] = "ap.status = 'pending' AND EXISTS (
                        SELECT 1 FROM approval_steps s
                        WHERE s.approval_id = ap.id AND s.status = 'pending' AND s.step_order = ap.current_step
                          AND (s.approver_id = ? OR s.approver_id IN (
                                SELECT dl.delegator_id FROM delegations dl
                                WHERE dl.delegate_id = ? AND dl.active = 1 AND dl.starts_at <= ? AND dl.ends_at >= ?
                          ))
                    )";
            $today = date('Y-m-d');
            array_push($params, (int) Auth::id(), (int) Auth::id(), $today, $today);
        }
        if (!empty($f['mine'])) {
            $w[] = 'ap.requester_id = ?';
            $params[] = (int) Auth::id();
        }
        return $w;
    }

    public static function list(array $f, array $me, int $limit = 20, int $offset = 0): array
    {
        $params = [];
        $w = [];
        $scope = self::scopeWhere($me, $params);
        if ($scope !== '') {
            $w[] = $scope;
        }
        $w = array_merge($w, self::filterWhere($f, $params));
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        $orderMap = [
            'updated'  => 'ap.updated_at DESC',
            'created'  => 'ap.created_at DESC',
            'due'      => 'CASE WHEN ap.due_date IS NULL THEN 1 ELSE 0 END, ap.due_date ASC',
            'priority' => "CASE ap.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END ASC",
        ];
        $order = $orderMap[$f['sort'] ?? 'updated'] ?? $orderMap['updated'];
        return Database::all(
            self::selectSql() . ' ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /** The manager's confirmation inbox: pending steps for me (or my delegations). */
    public static function inboxFor(array $me, int $limit = 8): array
    {
        return self::list(['inbox' => 1, 'sort' => 'due'], $me, $limit, 0);
    }

    public static function inboxCount(array $me): int
    {
        return self::count(['inbox' => 1], $me);
    }

    public static function overdueInboxCount(array $me): int
    {
        $params = [];
        $w = [];
        $scope = self::scopeWhere($me, $params);
        if ($scope !== '') {
            $w[] = $scope; // scope first — its params were appended first
        }
        $w[] = "ap.status = 'pending'";
        $w[] = 'ap.due_date IS NOT NULL';
        $w[] = 'ap.due_date < ?';
        $params[] = date('Y-m-d');
        $w[] = "EXISTS (SELECT 1 FROM approval_steps s WHERE s.approval_id = ap.id AND s.status = 'pending' AND s.step_order = ap.current_step AND (s.approver_id = ? OR s.approver_id IN (SELECT dl.delegator_id FROM delegations dl WHERE dl.delegate_id = ? AND dl.active = 1 AND dl.starts_at <= ? AND dl.ends_at >= ?)))";
        array_push($params, (int) $me['id'], (int) $me['id'], date('Y-m-d'), date('Y-m-d'));
        return (int) Database::value('SELECT COUNT(*) FROM approvals ap WHERE ' . implode(' AND ', $w), $params);
    }

    public static function byStatus(array $me): array
    {
        $params = [];
        $scope = self::scopeWhere($me, $params);
        $where = $scope !== '' ? 'WHERE ' . $scope : '';
        $rows = Database::all('SELECT status, COUNT(*) AS c FROM approvals ap ' . $where . ' GROUP BY status', $params);
        $out = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'returned' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    public static function byType(array $me): array
    {
        $params = [];
        $scope = self::scopeWhere($me, $params);
        $where = $scope !== '' ? 'WHERE ' . $scope : '';
        return Database::all(
            'SELECT ty.name_ar, ty.name_en, COUNT(*) AS c
             FROM approvals ap LEFT JOIN approval_types ty ON ty.id = ap.type_id
             ' . $where . ' GROUP BY ty.name_ar, ty.name_en ORDER BY c DESC',
            $params
        );
    }

    /** Average days from creation to decision for decided approvals. */
    public static function avgDecisionDays(array $me): ?float
    {
        $rows = Database::all(
            "SELECT created_at, closed_at FROM approvals ap WHERE closed_at IS NOT NULL"
        );
        if (!$rows) {
            return null;
        }
        $total = 0.0;
        $n = 0;
        foreach ($rows as $r) {
            $total += (strtotime((string) $r['closed_at']) - strtotime((string) $r['created_at'])) / 86400;
            $n++;
        }
        return $n ? round($total / $n, 1) : null;
    }
}

// ---------------------------------------------------------------- delegations

final class Delegations
{
    public static function allFor(int $userId): array
    {
        return Database::all(
            'SELECT dl.*, d1.name AS delegator_name, d2.name AS delegate_name
             FROM delegations dl
             LEFT JOIN users d1 ON d1.id = dl.delegator_id
             LEFT JOIN users d2 ON d2.id = dl.delegate_id
             WHERE dl.delegator_id = ? OR dl.delegate_id = ?
             ORDER BY dl.starts_at DESC',
            [$userId, $userId]
        );
    }

    public static function listAll(): array
    {
        return Database::all(
            'SELECT dl.*, d1.name AS delegator_name, d2.name AS delegate_name
             FROM delegations dl
             LEFT JOIN users d1 ON d1.id = dl.delegator_id
             LEFT JOIN users d2 ON d2.id = dl.delegate_id
             ORDER BY dl.starts_at DESC'
        );
    }

    public static function create(array $d): int
    {
        $id = Database::insert(
            'INSERT INTO delegations (delegator_id, delegate_id, starts_at, ends_at, reason, active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
            [(int) $d['delegator_id'], (int) $d['delegate_id'], $d['starts_at'], $d['ends_at'], $d['reason'] ?? '', now()]
        );
        audit('delegation_created', 'delegation', $id, 'from=' . $d['delegator_id'] . ' to=' . $d['delegate_id']);
        notify((int) $d['delegate_id'], t('notif.msg_delegation', ['from' => $d['starts_at'], 'to' => $d['ends_at']]), u('delegations'));
        return $id;
    }

    public static function cancel(int $id): void
    {
        Database::exec('UPDATE delegations SET active = 0 WHERE id = ?', [$id]);
        audit('delegation_cancelled', 'delegation', $id);
    }

    /** Is $userId an active delegate of $delegatorId today? */
    public static function isDelegateFor(int $userId, int $delegatorId): bool
    {
        $today = date('Y-m-d');
        return (bool) Database::value(
            'SELECT COUNT(*) FROM delegations WHERE delegate_id = ? AND delegator_id = ? AND active = 1 AND starts_at <= ? AND ends_at >= ?',
            [$userId, $delegatorId, $today, $today]
        );
    }

    public static function isDelegateForAny(int $userId, int $approvalId): bool
    {
        $today = date('Y-m-d');
        return (bool) Database::value(
            "SELECT COUNT(*) FROM approval_steps s
             JOIN delegations dl ON dl.delegator_id = s.approver_id
             WHERE s.approval_id = ? AND dl.delegate_id = ? AND dl.active = 1 AND dl.starts_at <= ? AND dl.ends_at >= ?",
            [$approvalId, $userId, $today, $today]
        );
    }

    /** Active delegations where I am the delegate (for the dashboard). */
    public static function activeForDelegate(int $userId): array
    {
        $today = date('Y-m-d');
        return Database::all(
            'SELECT dl.*, u.name AS delegator_name
             FROM delegations dl LEFT JOIN users u ON u.id = dl.delegator_id
             WHERE dl.delegate_id = ? AND dl.active = 1 AND dl.starts_at <= ? AND dl.ends_at >= ?',
            [$userId, $today, $today]
        );
    }
}

// ---------------------------------------------------------------- correspondence

final class Correspondence
{
    private static function selectSql(): string
    {
        return 'SELECT c.*, u.name AS assignee_name,
                       d.name_ar AS dept_name_ar, d.name_en AS dept_name_en
                FROM correspondence c
                LEFT JOIN users u ON u.id = c.assignee_id
                LEFT JOIN departments d ON d.id = c.department_id';
    }

    public static function find(int $id): ?array
    {
        return Database::one(self::selectSql() . ' WHERE c.id = ?', [$id]);
    }

    public static function makeRef(int $id, string $direction): string
    {
        $prefix = $direction === 'incoming' ? setting('corr_in_prefix', 'IN') : setting('corr_out_prefix', 'OUT');
        return strtoupper($prefix) . '-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    public static function create(array $d, array $me): int
    {
        $created = now();
        $id = Database::insert(
            'INSERT INTO correspondence (direction, subject, summary, party, reference_no, priority, status, assignee_id, department_id, received_at, due_date, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['direction'], $d['subject'], $d['summary'] ?? '', $d['party'] ?? '', $d['reference_no'] ?? '',
                $d['priority'], 'new', $d['assignee_id'] ?: null, $d['department_id'] ?: null,
                $d['received_at'] ?: date('Y-m-d'), $d['due_date'] ?: null, $created, $created,
            ]
        );
        Database::exec('UPDATE correspondence SET ref = ? WHERE id = ?', [self::makeRef($id, $d['direction']), $id]);
        audit('correspondence_created', 'correspondence', $id, 'direction=' . $d['direction']);
        if (!empty($d['assignee_id']) && (int) $d['assignee_id'] !== (int) $me['id']) {
            notify((int) $d['assignee_id'], t('notif.msg_corr_assigned', ['ref' => self::makeRef($id, $d['direction'])]), u('letter&id=' . $id));
        }
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE correspondence SET subject = ?, summary = ?, party = ?, reference_no = ?, priority = ?, assignee_id = ?, department_id = ?, due_date = ?, updated_at = ? WHERE id = ?',
            [
                $d['subject'], $d['summary'] ?? '', $d['party'] ?? '', $d['reference_no'] ?? '', $d['priority'],
                $d['assignee_id'] ?: null, $d['department_id'] ?: null, $d['due_date'] ?: null, now(), $id,
            ]
        );
        audit('correspondence_updated', 'correspondence', $id);
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::exec('UPDATE correspondence SET status = ?, updated_at = ? WHERE id = ?', [$status, now(), $id]);
        audit('correspondence_status', 'correspondence', $id, 'status=' . $status);
    }

    private static function scopeWhere(array $me, array &$params): string
    {
        if (in_array($me['role'], ['admin', 'executive'], true)) {
            return '';
        }
        if ($me['role'] === 'manager') {
            $scope = Users::scopeIds($me);
            $ph = implode(',', array_fill(0, count($scope), '?'));
            $params = array_merge($params, $scope);
            $w = "(c.assignee_id IN ($ph)";
            if (!empty($me['department_id'])) {
                $w .= ' OR c.department_id = ?';
                $params[] = (int) $me['department_id'];
            }
            return $w . ')';
        }
        $params[] = (int) $me['id'];
        return 'c.assignee_id = ?';
    }

    public static function count(array $f, array $me): int
    {
        $params = [];
        $w = [];
        $scope = self::scopeWhere($me, $params);
        if ($scope !== '') {
            $w[] = $scope;
        }
        $w = array_merge($w, self::filters($f, $params));
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return (int) Database::value('SELECT COUNT(*) FROM correspondence c ' . $where, $params);
    }

    private static function filters(array $f, array &$params): array
    {
        $w = [];
        if (!empty($f['q'])) {
            $w[] = '(c.ref LIKE ? OR c.subject LIKE ? OR c.party LIKE ? OR c.reference_no LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($f['direction'])) {
            $w[] = 'c.direction = ?';
            $params[] = $f['direction'];
        }
        if (!empty($f['status'])) {
            $w[] = 'c.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['priority'])) {
            $w[] = 'c.priority = ?';
            $params[] = $f['priority'];
        }
        return $w;
    }

    public static function list(array $f, array $me, int $limit = 20, int $offset = 0): array
    {
        $params = [];
        $w = [];
        $scope = self::scopeWhere($me, $params);
        if ($scope !== '') {
            $w[] = $scope;
        }
        $w = array_merge($w, self::filters($f, $params));
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        $order = ($f['sort'] ?? '') === 'due'
            ? 'CASE WHEN c.due_date IS NULL THEN 1 ELSE 0 END, c.due_date ASC'
            : 'c.created_at DESC';
        return Database::all(self::selectSql() . ' ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset, $params);
    }

    public static function openCount(array $me): int
    {
        $params = [];
        $scope = self::scopeWhere($me, $params);
        $w = ["c.status IN ('new','under_review')"];
        if ($scope !== '') {
            $w[] = $scope;
        }
        return (int) Database::value('SELECT COUNT(*) FROM correspondence c WHERE ' . implode(' AND ', $w), $params);
    }

    /** Incoming letters past their due date and still open. */
    public static function overdueCount(array $me): int
    {
        $params = [date('Y-m-d')];
        $scope = self::scopeWhere($me, $params);
        $w = ["c.status IN ('new','under_review')", 'c.due_date IS NOT NULL', 'c.due_date < ?'];
        if ($scope !== '') {
            $w[] = $scope;
        }
        // rebuild params in the right order: due-date first, then scope values
        $ordered = array_merge([date('Y-m-d')], array_slice($params, 1));
        return (int) Database::value('SELECT COUNT(*) FROM correspondence c WHERE ' . implode(' AND ', $w), $ordered);
    }
}

// ---------------------------------------------------------------- meetings

final class Meetings
{
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT m.*, u.name AS organizer_name FROM meetings m LEFT JOIN users u ON u.id = m.organizer_id WHERE m.id = ?',
            [$id]
        );
    }

    public static function attendees(int $meetingId): array
    {
        return Database::all(
            'SELECT ma.*, u.name, u.role, u.job_title FROM meeting_attendees ma JOIN users u ON u.id = ma.user_id
             WHERE ma.meeting_id = ? ORDER BY u.name ASC',
            [$meetingId]
        );
    }

    public static function create(array $d, array $attendeeIds): int
    {
        $id = Database::insert(
            'INSERT INTO meetings (title, agenda, location, starts_at, ends_at, organizer_id, minutes, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['title'], $d['agenda'] ?? '', $d['location'] ?? '', $d['starts_at'], $d['ends_at'] ?: null,
                Auth::id(), '', 'scheduled', now(), now(),
            ]
        );
        foreach (array_unique($attendeeIds) as $uid) {
            $uid = (int) $uid;
            if ($uid > 0) {
                Database::exec('INSERT INTO meeting_attendees (meeting_id, user_id, attended) VALUES (?, ?, 0)', [$id, $uid]);
                if ($uid !== Auth::id()) {
                    notify($uid, t('notif.msg_meeting', ['title' => excerpt($d['title'], 50), 'when' => $d['starts_at']]), u('meeting&id=' . $id));
                }
            }
        }
        audit('meeting_created', 'meeting', $id, 'title=' . $d['title']);
        return $id;
    }

    public static function update(int $id, array $d, string $detail = ''): void
    {
        Database::exec(
            'UPDATE meetings SET title = ?, agenda = ?, location = ?, starts_at = ?, ends_at = ?, status = ?, minutes = ?, updated_at = ? WHERE id = ?',
            [$d['title'], $d['agenda'] ?? '', $d['location'] ?? '', $d['starts_at'], $d['ends_at'] ?: null, $d['status'], $d['minutes'] ?? '', now(), $id]
        );
        audit('meeting_updated', 'meeting', $id, $detail !== '' ? $detail : 'status=' . $d['status']);
    }

    public static function setAttended(int $meetingId, int $userId, bool $attended): void
    {
        Database::exec('UPDATE meeting_attendees SET attended = ? WHERE meeting_id = ? AND user_id = ?', [$attended ? 1 : 0, $meetingId, $userId]);
        audit('meeting_attendance', 'meeting', $meetingId, 'user=' . $userId . ' attended=' . ($attended ? 1 : 0));
    }

    /** Meetings I organise or attend. */
    public static function listForUser(array $me, int $limit = 20, bool $upcomingOnly = false): array
    {
        $params = [(int) $me['id'], (int) $me['id']];
        $w = '(m.organizer_id = ? OR EXISTS (SELECT 1 FROM meeting_attendees ma WHERE ma.meeting_id = m.id AND ma.user_id = ?))';
        if ($upcomingOnly) {
            $w .= ' AND m.starts_at >= ?';
            $params[] = date('Y-m-d 00:00:00');
        }
        return Database::all(
            'SELECT m.*, u.name AS organizer_name,
                    (SELECT COUNT(*) FROM meeting_attendees ma2 WHERE ma2.meeting_id = m.id) AS attendees_count
             FROM meetings m LEFT JOIN users u ON u.id = m.organizer_id
             WHERE ' . $w . ' ORDER BY m.starts_at ' . ($upcomingOnly ? 'ASC' : 'DESC') . ' LIMIT ' . (int) $limit,
            $params
        );
    }

    public static function upcomingCount(array $me): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM meetings m
             WHERE m.status = 'scheduled' AND m.starts_at >= ?
               AND (m.organizer_id = ? OR EXISTS (SELECT 1 FROM meeting_attendees ma WHERE ma.meeting_id = m.id AND ma.user_id = ?))",
            [date('Y-m-d 00:00:00'), (int) $me['id'], (int) $me['id']]
        );
    }

    public static function nextForUser(array $me, int $limit = 4): array
    {
        return self::listForUser($me, $limit, true);
    }
}

// ---------------------------------------------------------------- notifications

final class Notifications
{
    public static function forUser(int $userId, int $limit = 50): array
    {
        return Database::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit, [$userId]);
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Database::exec('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
    }
}

// ---------------------------------------------------------------- audit

final class AuditLog
{
    public static function list(string $q = '', int $limit = 50, int $offset = 0): array
    {
        $w = '';
        $p = [];
        if ($q !== '') {
            $w = 'WHERE (action LIKE ? OR username LIKE ? OR details LIKE ? OR entity LIKE ?)';
            $like = '%' . $q . '%';
            array_push($p, $like, $like, $like, $like);
        }
        return Database::all('SELECT * FROM audit_log ' . $w . ' ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset, $p);
    }

    public static function count(string $q = ''): int
    {
        if ($q === '') {
            return (int) Database::value('SELECT COUNT(*) FROM audit_log');
        }
        $like = '%' . $q . '%';
        return (int) Database::value(
            'SELECT COUNT(*) FROM audit_log WHERE action LIKE ? OR username LIKE ? OR details LIKE ? OR entity LIKE ?',
            [$like, $like, $like, $like]
        );
    }

    public static function recent(int $limit = 8): array
    {
        return Database::all('SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . (int) $limit);
    }
}

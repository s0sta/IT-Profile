<?php
declare(strict_types=1);

/**
 * Data access layer. One small static class per domain entity.
 * Every statement is prepared; all dynamic ordering is whitelisted.
 */

// ---------------------------------------------------------------- users

final class Users
{
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM users';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        return Database::all($sql . ' ORDER BY name ASC');
    }

    /** Agents = agent + admin roles. */
    public static function agents(): array
    {
        return Database::all(
            "SELECT id, name, role FROM users WHERE role IN ('agent','admin') AND active = 1 ORDER BY name ASC"
        );
    }

    public static function create(array $d): int
    {
        $id = Database::insert(
            'INSERT INTO users (name, username, email, password_hash, role, department, phone, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [
                $d['name'], $d['username'], $d['email'],
                password_hash($d['password'], PASSWORD_DEFAULT),
                $d['role'], $d['department'] ?? '', $d['phone'] ?? '', now(),
            ]
        );
        audit('user_created', 'user', $id, 'username=' . $d['username'] . ' role=' . $d['role']);
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE users SET name = ?, username = ?, email = ?, role = ?, department = ?, phone = ? WHERE id = ?',
            [$d['name'], $d['username'], $d['email'], $d['role'], $d['department'] ?? '', $d['phone'] ?? '', $id]
        );
        audit('user_updated', 'user', $id, 'username=' . $d['username'] . ' role=' . $d['role']);
    }

    public static function setPassword(int $id, string $plain, bool $mustChange = false): void
    {
        Database::exec(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($plain, PASSWORD_DEFAULT), $id]
        );
        if (column_exists('users', 'must_change_password')) {
            Database::exec('UPDATE users SET must_change_password = ? WHERE id = ?', [$mustChange ? 1 : 0, $id]);
        }
        audit($mustChange ? 'user_password_reset' : 'user_password_changed', 'user', $id);
    }

    public static function toggleActive(int $id): void
    {
        $u = self::find($id);
        if (!$u || $u['role'] === 'admin') {
            return; // never deactivate the last/admin accounts via this path
        }
        Database::exec('UPDATE users SET active = ? WHERE id = ?', [$u['active'] ? 0 : 1, $id]);
        audit($u['active'] ? 'user_deactivated' : 'user_activated', 'user', $id);
    }
}

// ---------------------------------------------------------------- ticket categories

final class Categories
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM categories';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        return Database::all($sql . ' ORDER BY sort_order ASC, name ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    /** @return bool false when a category with this name already exists. */
    public static function create(string $name, string $description): bool
    {
        if (Database::value('SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?)', [$name])) {
            return false;
        }
        $sort = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories');
        $id = Database::insert(
            'INSERT INTO categories (name, description, active, sort_order) VALUES (?, ?, 1, ?)',
            [$name, $description, $sort]
        );
        audit('category_created', 'category', $id, 'name=' . $name);
        return true;
    }

    /** @return bool false when another category already uses this name. */
    public static function update(int $id, string $name, string $description): bool
    {
        if (Database::value('SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?) AND id <> ?', [$name, $id])) {
            return false;
        }
        Database::exec('UPDATE categories SET name = ?, description = ? WHERE id = ?', [$name, $description, $id]);
        audit('category_updated', 'category', $id, 'name=' . $name);
        return true;
    }

    public static function toggleActive(int $id): void
    {
        $c = self::find($id);
        if (!$c) {
            return;
        }
        Database::exec('UPDATE categories SET active = ? WHERE id = ?', [$c['active'] ? 0 : 1, $id]);
    }
}

// ---------------------------------------------------------------- tickets

final class Tickets
{
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT t.*, r.name AS requester_name, a.name AS assignee_name, c.name AS category_name
             FROM tickets t
             LEFT JOIN users r ON r.id = t.requester_id
             LEFT JOIN users a ON a.id = t.assignee_id
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.id = ?',
            [$id]
        );
    }

    public static function canView(array $ticket): bool
    {
        if (Auth::isStaff()) {
            return true;
        }
        return (int) $ticket['requester_id'] === Auth::id();
    }

    public static function create(int $requesterId, string $subject, string $description, ?int $categoryId, string $priority): int
    {
        $created = now();
        $id = Database::insert(
            'INSERT INTO tickets (subject, description, category_id, priority, status, requester_id, sla_due, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                trim($subject), trim($description), $categoryId, $priority, 'new',
                $requesterId, sla_due($created, $priority), $created, $created,
            ]
        );
        $ref = self::makeRef($id);
        Database::exec('UPDATE tickets SET ref = ? WHERE id = ?', [$ref, $id]);

        audit('ticket_created', 'ticket', $id, 'ref=' . $ref . ' priority=' . $priority);

        foreach (Users::agents() as $agent) {
            if ((int) $agent['id'] !== Auth::id()) {
                notify((int) $agent['id'], t('notif.msg_new_ticket', ['ref' => $ref, 'subject' => excerpt($subject, 60)]), u('ticket&id=' . $id));
            }
        }
        return $id;
    }

    public static function makeRef(int $id): string
    {
        $prefix = setting('ticket_prefix', 'TCK');
        return strtoupper($prefix) . '-' . date('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    private static function buildWhere(array $f, array &$params): string
    {
        $w = [];
        if (!empty($f['q'])) {
            $w[] = '(t.ref LIKE ? OR t.subject LIKE ? OR t.description LIKE ?)';
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
            if ($f['assignee'] === 'none') {
                $w[] = 't.assignee_id IS NULL';
            } else {
                $w[] = 't.assignee_id = ?';
                $params[] = (int) $f['assignee'];
            }
        }
        if (!empty($f['requester'])) {
            $w[] = 't.requester_id = ?';
            $params[] = (int) $f['requester'];
        }
        if (!empty($f['from'])) {
            $w[] = 't.created_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to'])) {
            $w[] = 't.created_at <= ?';
            $params[] = $f['to'] . ' 23:59:59';
        }
        // Requesters only see their own tickets.
        if (!Auth::isStaff()) {
            $w[] = 't.requester_id = ?';
            $params[] = Auth::id();
        }
        return $w ? ('WHERE ' . implode(' AND ', $w)) : '';
    }

    public static function count(array $f): int
    {
        $params = [];
        $where = self::buildWhere($f, $params);
        return (int) Database::value('SELECT COUNT(*) FROM tickets t ' . $where, $params);
    }

    public static function list(array $f, int $limit = 20, int $offset = 0): array
    {
        $params = [];
        $where = self::buildWhere($f, $params);
        $orderMap = [
            'updated'  => 't.updated_at DESC',
            'created'  => 't.created_at DESC',
            'due'      => 't.sla_due ASC',
            'priority' => "CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END ASC",
        ];
        $order = $orderMap[$f['sort'] ?? 'updated'] ?? $orderMap['updated'];
        $sql = 'SELECT t.*, r.name AS requester_name, a.name AS assignee_name, c.name AS category_name
                FROM tickets t
                LEFT JOIN users r ON r.id = t.requester_id
                LEFT JOIN users a ON a.id = t.assignee_id
                LEFT JOIN categories c ON c.id = t.category_id
                ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        return Database::all($sql, $params);
    }

    public static function setStatus(int $id, string $status): void
    {
        $t = self::find($id);
        if (!$t) {
            return;
        }
        $now = now();
        $sql = 'UPDATE tickets SET status = ?, updated_at = ?';
        $params = [$status, $now];
        if ($status === 'resolved') {
            $sql .= ', resolved_at = ?';
            $params[] = $now;
        } elseif ($status === 'closed') {
            $sql .= ', closed_at = ?';
            $params[] = $now;
        } elseif ($status !== 'closed' && $status !== 'resolved') {
            $sql .= ', resolved_at = NULL, closed_at = NULL';
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        Database::exec($sql, $params);
        audit('ticket_status', 'ticket', $id, 'ref=' . $t['ref'] . ' → ' . $status);
        self::notifyInvolved($t, t('notif.msg_status', ['ref' => $t['ref'], 'status' => statuses()[$status]]), $status);
    }

    public static function assign(int $id, ?int $assigneeId): void
    {
        $t = self::find($id);
        if (!$t) {
            return;
        }
        Database::exec('UPDATE tickets SET assignee_id = ?, updated_at = ? WHERE id = ?', [$assigneeId, now(), $id]);
        audit('ticket_assigned', 'ticket', $id, 'ref=' . $t['ref'] . ' assignee=' . ($assigneeId ?: 'unassigned'));
        if ($assigneeId && (int) $assigneeId !== Auth::id()) {
            notify($assigneeId, t('notif.msg_assigned', ['ref' => $t['ref']]), u('ticket&id=' . $id));
        }
    }

    public static function setPriority(int $id, string $priority): void
    {
        $t = self::find($id);
        if (!$t) {
            return;
        }
        // Re-arm the response SLA from the moment the priority changes (open tickets only).
        $extra = is_open_status($t['status']) && empty($t['first_response_at'])
            ? ', sla_due = ?'
            : '';
        $params = [$priority, now()];
        if ($extra) {
            $params[] = sla_due(now(), $priority);
        }
        $params[] = $id;
        Database::exec('UPDATE tickets SET priority = ?, updated_at = ?' . $extra . ' WHERE id = ?', $params);
        audit('ticket_priority', 'ticket', $id, 'ref=' . $t['ref'] . ' → ' . $priority);
    }

    public static function setCategory(int $id, ?int $categoryId): void
    {
        $t = self::find($id);
        if (!$t) {
            return;
        }
        Database::exec('UPDATE tickets SET category_id = ?, updated_at = ? WHERE id = ?', [$categoryId, now(), $id]);
        audit('ticket_category', 'ticket', $id, 'ref=' . $t['ref']);
    }

    /** Record the first agent response and move a brand-new ticket to Open. */
    public static function markFirstResponse(int $id): void
    {
        $t = self::find($id);
        if (!$t || $t['first_response_at']) {
            return;
        }
        $extra = ($t['status'] === 'new') ? ", status = 'open'" : '';
        Database::exec(
            'UPDATE tickets SET first_response_at = ?, updated_at = ?' . $extra . ' WHERE id = ?',
            [now(), now(), $id]
        );
        audit('first_response', 'ticket', $id, 'ref=' . $t['ref']);
    }

    /** Reopen a resolved/closed ticket when the requester replies. */
    public static function reopen(int $id): void
    {
        $t = self::find($id);
        if (!$t || !in_array($t['status'], ['resolved', 'closed'], true)) {
            return;
        }
        Database::exec(
            "UPDATE tickets SET status = 'open', resolved_at = NULL, closed_at = NULL, updated_at = ? WHERE id = ?",
            [now(), $id]
        );
        audit('ticket_reopened', 'ticket', $id, 'ref=' . $t['ref']);
        if (!empty($t['assignee_id'])) {
            notify((int) $t['assignee_id'], t('notif.msg_reopened', ['ref' => $t['ref']]), u('ticket&id=' . $id));
        } else {
            foreach (Users::agents() as $agent) {
                notify((int) $agent['id'], t('notif.msg_reopened', ['ref' => $t['ref']]), u('ticket&id=' . $id));
            }
        }
    }

    /** Notify requester and assignee (except the actor) about a change. */
    public static function notifyInvolved(array $ticket, string $message, ?string $linkSuffix = null): void
    {
        $link = u('ticket&id=' . $ticket['id']);
        if ((int) $ticket['requester_id'] !== Auth::id()) {
            notify((int) $ticket['requester_id'], $message, $link);
        }
        if (!empty($ticket['assignee_id']) && (int) $ticket['assignee_id'] !== Auth::id()) {
            notify((int) $ticket['assignee_id'], $message, $link);
        }
    }

    // ---------------------------------------------------------------- stats

    public static function openByStatus(bool $ownOnly = false): array
    {
        $extra = $ownOnly ? ' AND requester_id = ' . Auth::id() : '';
        return Database::all(
            "SELECT status, COUNT(*) AS c FROM tickets
             WHERE status NOT IN ('resolved','closed')" . $extra . ' GROUP BY status'
        );
    }

    public static function overdueCount(bool $ownOnly = false): int
    {
        $extra = $ownOnly ? ' AND requester_id = ' . Auth::id() : '';
        return (int) Database::value(
            "SELECT COUNT(*) FROM tickets
             WHERE status NOT IN ('resolved','closed') AND first_response_at IS NULL AND sla_due IS NOT NULL AND sla_due < ?" . $extra,
            [now()]
        );
    }

    public static function unassignedCount(): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM tickets WHERE status NOT IN ('resolved','closed') AND assignee_id IS NULL"
        );
    }

    public static function createdSince(string $since): int
    {
        $extra = Auth::isStaff() ? '' : ' AND requester_id = ' . Auth::id();
        return (int) Database::value('SELECT COUNT(*) FROM tickets WHERE created_at >= ?' . $extra, [$since]);
    }

    public static function resolvedSince(string $since): int
    {
        $extra = Auth::isStaff() ? '' : ' AND requester_id = ' . Auth::id();
        return (int) Database::value('SELECT COUNT(*) FROM tickets WHERE resolved_at >= ?' . $extra, [$since]);
    }

    public static function avgResolutionHours(string $since): ?float
    {
        $extra = Auth::isStaff() ? '' : ' AND requester_id = ' . Auth::id();
        $rows = Database::all(
            'SELECT created_at, resolved_at FROM tickets WHERE resolved_at >= ?' . $extra,
            [$since]
        );
        if (!$rows) {
            return null;
        }
        $total = 0.0;
        $n = 0;
        foreach ($rows as $r) {
            $total += (strtotime($r['resolved_at']) - strtotime($r['created_at'])) / 3600.0;
            $n++;
        }
        return $n ? round($total / $n, 1) : null;
    }

    public static function series14(): array
    {
        $extra = Auth::isStaff() ? '' : ' AND requester_id = ' . Auth::id();
        $since = date('Y-m-d H:i:s', strtotime('-13 days'));
        $rows = Database::all(
            'SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS c FROM tickets
             WHERE created_at >= ?' . $extra . ' GROUP BY day',
            [$since]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[$r['day']] = (int) $r['c'];
        }
        $out = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $out[] = ['day' => date('M j', strtotime($day)), 'count' => $map[$day] ?? 0];
        }
        return $out;
    }

    public static function openByPriority(): array
    {
        $extra = Auth::isStaff() ? '' : ' AND requester_id = ' . Auth::id();
        return Database::all(
            "SELECT priority, COUNT(*) AS c FROM tickets
             WHERE status NOT IN ('resolved','closed')" . $extra . ' GROUP BY priority'
        );
    }

    public static function agentWorkload(): array
    {
        return Database::all(
            "SELECT a.id, a.name, COUNT(*) AS c
             FROM tickets t JOIN users a ON a.id = t.assignee_id
             WHERE t.status NOT IN ('resolved','closed')
             GROUP BY a.id, a.name ORDER BY c DESC"
        );
    }

    // ---------------------------------------------------------------- reports

    public static function reportSummary(array $f): array
    {
        // Portable: all SLA math computed in PHP so it works identically on SQLite and MySQL.
        $rows = self::reportRows($f);
        $total   = count($rows);
        $resolved = 0;
        $openNow = 0;
        $within  = 0;
        $totalH  = 0.0;
        $n       = 0;
        foreach ($rows as $r) {
            if (in_array($r['status'], ['resolved', 'closed'], true)) {
                $resolved++;
            } else {
                $openNow++;
            }
            if ($r['resolved_at']) {
                $hours = (strtotime($r['resolved_at']) - strtotime($r['created_at'])) / 3600.0;
                $totalH += $hours;
                $n++;
                if ($hours <= (float) sla_policy($r['priority'])['resolution_hours']) {
                    $within++;
                }
            }
        }
        return [
            'total'          => $total,
            'resolved'       => $resolved,
            'open_now'       => $openNow,
            'avg_hours'      => $n ? round($totalH / $n, 1) : null,
            'sla_compliance' => $n ? round($within / $n * 100, 1) : null,
        ];
    }

    public static function reportRows(array $f): array
    {
        $params = [];
        $where = self::buildWhere($f, $params);
        return Database::all(
            'SELECT t.*, r.name AS requester_name, a.name AS assignee_name, c.name AS category_name
             FROM tickets t
             LEFT JOIN users r ON r.id = t.requester_id
             LEFT JOIN users a ON a.id = t.assignee_id
             LEFT JOIN categories c ON c.id = t.category_id
             ' . $where . ' ORDER BY t.created_at ASC',
            $params
        );
    }
}

// ---------------------------------------------------------------- replies

final class Replies
{
    public static function byTicket(int $ticketId): array
    {
        return Database::all(
            'SELECT tr.*, u.name AS author_name, u.role AS author_role
             FROM ticket_replies tr JOIN users u ON u.id = tr.author_id
             WHERE tr.ticket_id = ? ORDER BY tr.created_at ASC, tr.id ASC',
            [$ticketId]
        );
    }

    /** Returns the new reply id. Pass $withFiles=true when $_FILES carries attachments. */
    public static function add(int $ticketId, int $authorId, string $body, bool $internal, bool $withFiles = false): int
    {
        $id = Database::insert(
            'INSERT INTO ticket_replies (ticket_id, author_id, body, is_internal, created_at) VALUES (?, ?, ?, ?, ?)',
            [$ticketId, $authorId, trim($body), $internal ? 1 : 0, now()]
        );
        Database::exec('UPDATE tickets SET updated_at = ? WHERE id = ?', [now(), $ticketId]);
        if ($withFiles) {
            handle_uploads($ticketId, $id, $authorId);
        }
        audit($internal ? 'note_added' : 'reply_added', 'ticket', $ticketId, 'reply=' . $id);
        return $id;
    }

    public static function attachments(int $replyId): array
    {
        return Database::all('SELECT * FROM attachments WHERE reply_id = ? ORDER BY id ASC', [$replyId]);
    }

    public static function attachmentsOfTicket(int $ticketId): array
    {
        return Database::all('SELECT * FROM attachments WHERE ticket_id = ? ORDER BY id ASC', [$ticketId]);
    }
}

// ---------------------------------------------------------------- knowledge base

final class Kb
{
    public static function categories(): array
    {
        return Database::all('SELECT * FROM kb_categories ORDER BY sort_order ASC, name ASC');
    }

    public static function findCategory(int $id): ?array
    {
        return Database::one('SELECT * FROM kb_categories WHERE id = ?', [$id]);
    }

    /** @return bool false when a KB category with this name already exists. */
    public static function categoryCreate(string $name, string $description): bool
    {
        if (Database::value('SELECT COUNT(*) FROM kb_categories WHERE LOWER(name) = LOWER(?)', [$name])) {
            return false;
        }
        $sort = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM kb_categories');
        $id = Database::insert('INSERT INTO kb_categories (name, description, sort_order) VALUES (?, ?, ?)', [$name, $description, $sort]);
        audit('kb_category_created', 'kb_category', $id, 'name=' . $name);
        return true;
    }

    /** @return bool false when another KB category already uses this name. */
    public static function categoryUpdate(int $id, string $name, string $description): bool
    {
        if (Database::value('SELECT COUNT(*) FROM kb_categories WHERE LOWER(name) = LOWER(?) AND id <> ?', [$name, $id])) {
            return false;
        }
        Database::exec('UPDATE kb_categories SET name = ?, description = ? WHERE id = ?', [$name, $description, $id]);
        audit('kb_category_updated', 'kb_category', $id, 'name=' . $name);
        return true;
    }

    public static function categoryDelete(int $id): void
    {
        Database::exec('DELETE FROM kb_categories WHERE id = ?', [$id]);
        audit('kb_category_deleted', 'kb_category', $id);
    }

    public static function articleFind(int $id): ?array
    {
        return Database::one('SELECT * FROM kb_articles WHERE id = ?', [$id]);
    }

    public static function articleBySlug(string $slug): ?array
    {
        return Database::one(
            'SELECT a.*, c.name AS category_name, u.name AS author_name
             FROM kb_articles a
             LEFT JOIN kb_categories c ON c.id = a.category_id
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.slug = ?',
            [$slug]
        );
    }

    public static function articles(?int $categoryId = null, string $q = '', int $limit = 25, int $offset = 0, bool $publishedOnly = true): array
    {
        $w = [];
        $p = [];
        if ($publishedOnly && !Auth::isStaff()) {
            $w[] = 'a.published = 1';
        }
        if ($categoryId) {
            $w[] = 'a.category_id = ?';
            $p[] = $categoryId;
        }
        if ($q !== '') {
            $w[] = '(a.title LIKE ? OR a.body LIKE ?)';
            $like = '%' . $q . '%';
            array_push($p, $like, $like);
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return Database::all(
            'SELECT a.*, c.name AS category_name FROM kb_articles a
             LEFT JOIN kb_categories c ON c.id = a.category_id
             ' . $where . ' ORDER BY a.updated_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $p
        );
    }

    public static function articleCount(?int $categoryId = null, string $q = ''): int
    {
        $w = [];
        $p = [];
        if (!Auth::isStaff()) {
            $w[] = 'published = 1';
        }
        if ($categoryId) {
            $w[] = 'category_id = ?';
            $p[] = $categoryId;
        }
        if ($q !== '') {
            $w[] = '(title LIKE ? OR body LIKE ?)';
            $like = '%' . $q . '%';
            array_push($p, $like, $like);
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return (int) Database::value('SELECT COUNT(*) FROM kb_articles ' . $where, $p);
    }

    public static function articleCreate(int $authorId, int $categoryId, string $title, string $body, bool $published): int
    {
        $slug = self::uniqueSlug($title);
        $id = Database::insert(
            'INSERT INTO kb_articles (category_id, title, slug, body, author_id, published, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$categoryId, trim($title), $slug, trim($body), $authorId, $published ? 1 : 0, now(), now()]
        );
        audit('kb_article_created', 'kb_article', $id, 'title=' . trim($title));
        return $id;
    }

    public static function articleUpdate(int $id, int $categoryId, string $title, string $body, bool $published): void
    {
        Database::exec(
            'UPDATE kb_articles SET category_id = ?, title = ?, body = ?, published = ?, updated_at = ? WHERE id = ?',
            [$categoryId, trim($title), trim($body), $published ? 1 : 0, now(), $id]
        );
        audit('kb_article_updated', 'kb_article', $id, 'title=' . trim($title));
    }

    public static function articleDelete(int $id): void
    {
        Database::exec('DELETE FROM kb_articles WHERE id = ?', [$id]);
        audit('kb_article_deleted', 'kb_article', $id);
    }

    private static function uniqueSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        if ($slug === '') {
            $slug = 'article';
        }
        $base = $slug;
        $i = 2;
        while (Database::value('SELECT COUNT(*) FROM kb_articles WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public static function registerView(int $id): void
    {
        Database::exec('UPDATE kb_articles SET views = views + 1 WHERE id = ?', [$id]);
    }

    /** Returns true if the vote was recorded (once per user per article). */
    public static function vote(int $articleId, int $userId, int $vote): bool
    {
        $exists = Database::one(
            'SELECT * FROM kb_feedback WHERE article_id = ? AND user_id = ?',
            [$articleId, $userId]
        );
        if ($exists) {
            if ((int) $exists['vote'] === $vote) {
                return false;
            }
            // Revert the previous counter before applying the new vote.
            $col = (int) $exists['vote'] > 0 ? 'helpful' : 'not_helpful';
            Database::exec(
                'UPDATE kb_articles SET ' . $col . ' = CASE WHEN ' . $col . ' > 0 THEN ' . $col . ' - 1 ELSE 0 END WHERE id = ?',
                [$articleId]
            );
            Database::exec('UPDATE kb_feedback SET vote = ? WHERE id = ?', [$vote, (int) $exists['id']]);
        } else {
            Database::exec(
                'INSERT INTO kb_feedback (article_id, user_id, vote, created_at) VALUES (?, ?, ?, ?)',
                [$articleId, $userId, $vote, now()]
            );
        }
        if ($vote > 0) {
            Database::exec('UPDATE kb_articles SET helpful = helpful + 1 WHERE id = ?', [$articleId]);
        } else {
            Database::exec('UPDATE kb_articles SET not_helpful = not_helpful + 1 WHERE id = ?', [$articleId]);
        }
        return true;
    }
}

// ---------------------------------------------------------------- notifications

final class Notifications
{
    public static function forUser(int $userId, int $limit = 50): array
    {
        return Database::all(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit,
            [$userId]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
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
        return Database::all(
            'SELECT * FROM audit_log ' . $w . ' ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $p
        );
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
}

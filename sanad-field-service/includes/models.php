<?php
declare(strict_types=1);

/**
 * Sanad — data access layer (field service & job management).
 * One small static class per domain entity. Prepared statements everywhere,
 * whitelisted ordering, referential integrity enforced in the application layer.
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

    /** Field technicians (dispatch board columns). */
    public static function technicians(): array
    {
        return Database::all("SELECT * FROM users WHERE role = 'technician' AND active = 1 ORDER BY name ASC");
    }

    /** Office staff who may manage work: admins + dispatchers. */
    public static function office(): array
    {
        return Database::all("SELECT id, name, role FROM users WHERE role IN ('admin','dispatcher') AND active = 1 ORDER BY name ASC");
    }

    public static function create(array $d): int
    {
        $id = Database::insert(
            'INSERT INTO users (name, username, email, password_hash, role, phone, colour, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [
                $d['name'], $d['username'], $d['email'],
                password_hash($d['password'], PASSWORD_DEFAULT),
                $d['role'], $d['phone'] ?? '', $d['colour'] ?? '', now(),
            ]
        );
        audit('user_created', 'user', $id, 'username=' . $d['username'] . ' role=' . $d['role']);
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE users SET name = ?, username = ?, email = ?, role = ?, phone = ?, colour = ? WHERE id = ?',
            [$d['name'], $d['username'], $d['email'], $d['role'], $d['phone'] ?? '', $d['colour'] ?? '', $id]
        );
        audit('user_updated', 'user', $id, 'username=' . $d['username'] . ' role=' . $d['role']);
    }

    public static function setPassword(int $id, string $plain): void
    {
        Database::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($plain, PASSWORD_DEFAULT), $id]);
        audit('user_password_reset', 'user', $id);
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

    /** Workload: open jobs per technician. */
    public static function workload(): array
    {
        return Database::all(
            "SELECT u.id, u.name, u.colour, COUNT(j.id) AS open_jobs
             FROM users u
             LEFT JOIN jobs j ON j.assigned_to = u.id AND j.status IN ('new','scheduled','in_progress','on_hold')
             WHERE u.role = 'technician' AND u.active = 1
             GROUP BY u.id, u.name, u.colour ORDER BY open_jobs DESC, u.name ASC"
        );
    }
}

// ---------------------------------------------------------------- customers

final class Customers
{
    public static function all(bool $activeOnly = false, string $q = ''): array
    {
        $w = [];
        $p = [];
        if ($activeOnly) {
            $w[] = 'c.active = 1';
        }
        if ($q !== '') {
            $w[] = '(c.name LIKE ? OR c.code LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.city LIKE ?)';
            $like = '%' . $q . '%';
            array_push($p, $like, $like, $like, $like, $like);
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return Database::all(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM sites s WHERE s.customer_id = c.id) AS site_count,
                    (SELECT COUNT(*) FROM jobs j WHERE j.customer_id = c.id) AS job_count,
                    (SELECT COUNT(*) FROM contracts ct WHERE ct.customer_id = c.id AND ct.status = 'active') AS contract_count
             FROM customers c " . $where . ' ORDER BY c.name ASC',
            $p
        );
    }

    public static function count(bool $activeOnly = false): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM customers' . ($activeOnly ? ' WHERE active = 1' : ''));
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    public static function create(array $d): int
    {
        $id = Database::insert(
            'INSERT INTO customers (name, type, email, phone, address, city, tax_no, notes, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [
                trim($d['name']), $d['type'] ?? 'company', trim($d['email'] ?? ''), trim($d['phone'] ?? ''),
                trim($d['address'] ?? ''), trim($d['city'] ?? ''), trim($d['tax_no'] ?? ''), trim($d['notes'] ?? ''), now(),
            ]
        );
        $code = strtoupper((string) setting('customer_prefix', 'CUS')) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
        Database::exec('UPDATE customers SET code = ? WHERE id = ?', [$code, $id]);
        audit('customer_created', 'customer', $id, 'name=' . trim($d['name']));
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE customers SET name = ?, type = ?, email = ?, phone = ?, address = ?, city = ?, tax_no = ?, notes = ? WHERE id = ?',
            [
                trim($d['name']), $d['type'] ?? 'company', trim($d['email'] ?? ''), trim($d['phone'] ?? ''),
                trim($d['address'] ?? ''), trim($d['city'] ?? ''), trim($d['tax_no'] ?? ''), trim($d['notes'] ?? ''), $id,
            ]
        );
        audit('customer_updated', 'customer', $id, 'name=' . trim($d['name']));
    }

    public static function toggleActive(int $id): void
    {
        $c = self::find($id);
        if (!$c) {
            return;
        }
        Database::exec('UPDATE customers SET active = ? WHERE id = ?', [$c['active'] ? 0 : 1, $id]);
        audit($c['active'] ? 'customer_deactivated' : 'customer_activated', 'customer', $id);
    }

    public static function outstanding(int $customerId): float
    {
        return (float) Database::value(
            "SELECT COALESCE(SUM(i.total - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id), 0)), 0)
             FROM invoices i WHERE i.customer_id = ? AND i.status <> 'cancelled'",
            [$customerId]
        );
    }
}

// ---------------------------------------------------------------- sites

final class Sites
{
    public static function forCustomer(int $customerId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM sites WHERE customer_id = ?';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        return Database::all($sql . ' ORDER BY name ASC', [$customerId]);
    }

    public static function all(int $limit = 300): array
    {
        return Database::all(
            'SELECT s.*, c.name AS customer_name FROM sites s JOIN customers c ON c.id = s.customer_id
             ORDER BY c.name ASC, s.name ASC LIMIT ' . (int) $limit
        );
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT s.*, c.name AS customer_name FROM sites s JOIN customers c ON c.id = s.customer_id WHERE s.id = ?',
            [$id]
        );
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM sites');
    }

    public static function create(array $d): int
    {
        $id = Database::insert(
            'INSERT INTO sites (customer_id, name, address, city, contact_name, contact_phone, access_notes, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [
                (int) $d['customer_id'], trim($d['name']), trim($d['address'] ?? ''), trim($d['city'] ?? ''),
                trim($d['contact_name'] ?? ''), trim($d['contact_phone'] ?? ''), trim($d['access_notes'] ?? ''), now(),
            ]
        );
        audit('site_created', 'site', $id, 'name=' . trim($d['name']));
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE sites SET name = ?, address = ?, city = ?, contact_name = ?, contact_phone = ?, access_notes = ? WHERE id = ?',
            [
                trim($d['name']), trim($d['address'] ?? ''), trim($d['city'] ?? ''),
                trim($d['contact_name'] ?? ''), trim($d['contact_phone'] ?? ''), trim($d['access_notes'] ?? ''), $id,
            ]
        );
        audit('site_updated', 'site', $id, 'name=' . trim($d['name']));
    }
}

// ---------------------------------------------------------------- service catalog

final class Services
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM services';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        return Database::all($sql . ' ORDER BY sort_order ASC, name ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM services WHERE id = ?', [$id]);
    }

    public static function create(array $d): void
    {
        $sort = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM services');
        $id = Database::insert(
            'INSERT INTO services (code, name, description, price, duration_min, active, sort_order) VALUES (?, ?, ?, ?, ?, 1, ?)',
            [strtoupper(trim($d['code'])), trim($d['name']), trim($d['description'] ?? ''), (float) $d['price'], (int) ($d['duration_min'] ?? 60), $sort]
        );
        audit('service_created', 'service', $id, 'name=' . trim($d['name']));
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE services SET code = ?, name = ?, description = ?, price = ?, duration_min = ? WHERE id = ?',
            [strtoupper(trim($d['code'])), trim($d['name']), trim($d['description'] ?? ''), (float) $d['price'], (int) ($d['duration_min'] ?? 60), $id]
        );
        audit('service_updated', 'service', $id, 'name=' . trim($d['name']));
    }

    public static function toggleActive(int $id): void
    {
        $s = self::find($id);
        if (!$s) {
            return;
        }
        Database::exec('UPDATE services SET active = ? WHERE id = ?', [$s['active'] ? 0 : 1, $id]);
    }
}

// ---------------------------------------------------------------- parts / materials

final class Parts
{
    public static function all(bool $activeOnly = false, string $q = ''): array
    {
        $w = [];
        $p = [];
        if ($activeOnly) {
            $w[] = 'active = 1';
        }
        if ($q !== '') {
            $w[] = '(name LIKE ? OR sku LIKE ?)';
            $like = '%' . $q . '%';
            array_push($p, $like, $like);
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return Database::all('SELECT * FROM parts ' . $where . ' ORDER BY name ASC', $p);
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM parts WHERE id = ?', [$id]);
    }

    public static function create(array $d): void
    {
        $id = Database::insert(
            'INSERT INTO parts (sku, name, unit, stock_qty, reorder_level, cost_price, sell_price, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
            [
                strtoupper(trim($d['sku'])), trim($d['name']), trim($d['unit'] ?? 'pc'),
                (float) ($d['stock_qty'] ?? 0), (float) ($d['reorder_level'] ?? 0),
                (float) ($d['cost_price'] ?? 0), (float) ($d['sell_price'] ?? 0), now(), now(),
            ]
        );
        audit('part_created', 'part', $id, 'sku=' . strtoupper(trim($d['sku'])));
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE parts SET sku = ?, name = ?, unit = ?, reorder_level = ?, cost_price = ?, sell_price = ?, updated_at = ? WHERE id = ?',
            [
                strtoupper(trim($d['sku'])), trim($d['name']), trim($d['unit'] ?? 'pc'),
                (float) ($d['reorder_level'] ?? 0), (float) ($d['cost_price'] ?? 0),
                (float) ($d['sell_price'] ?? 0), now(), $id,
            ]
        );
        audit('part_updated', 'part', $id, 'sku=' . strtoupper(trim($d['sku'])));
    }

    /** Move stock up or down (+qty = receive, -qty = used on a job). */
    public static function adjustStock(int $id, float $delta, string $reason): void
    {
        $part = self::find($id);
        if (!$part) {
            return;
        }
        $new = max(0.0, (float) $part['stock_qty'] + $delta);
        Database::exec('UPDATE parts SET stock_qty = ?, updated_at = ? WHERE id = ?', [$new, now(), $id]);
        audit('part_stock', 'part', $id, sprintf('sku=%s %+.2f (%s) → %.2f', $part['sku'], $delta, $reason, $new));
    }

    public static function lowStock(): array
    {
        return Database::all('SELECT * FROM parts WHERE active = 1 AND stock_qty <= reorder_level ORDER BY (stock_qty - reorder_level) ASC');
    }

    public static function stockValue(): float
    {
        return (float) Database::value('SELECT COALESCE(SUM(stock_qty * cost_price), 0) FROM parts WHERE active = 1');
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM parts');
    }
}

// ---------------------------------------------------------------- jobs (work orders)

final class Jobs
{
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT j.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.code AS customer_code,
                    s.name AS site_name, s.address AS site_address, s.city AS site_city,
                    s.contact_name, s.contact_phone, s.access_notes,
                    sv.name AS service_name, sv.price AS service_price, sv.duration_min,
                    u.name AS technician_name, u.colour AS technician_colour,
                    ct.number AS contract_number
             FROM jobs j
             LEFT JOIN customers c ON c.id = j.customer_id
             LEFT JOIN sites s ON s.id = j.site_id
             LEFT JOIN services sv ON sv.id = j.service_id
             LEFT JOIN users u ON u.id = j.assigned_to
             LEFT JOIN contracts ct ON ct.id = j.contract_id
             WHERE j.id = ?',
            [$id]
        );
    }

    public static function create(array $d): int
    {
        $created = now();
        $id = Database::insert(
            'INSERT INTO jobs (customer_id, site_id, service_id, contract_id, type, priority, status, assigned_to,
                               scheduled_date, window_start, window_end, title, description, internal_notes,
                               created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $d['customer_id'], ($d['site_id'] ?? null) ?: null, ($d['service_id'] ?? null) ?: null, ($d['contract_id'] ?? null) ?: null,
                $d['type'] ?? 'one_time', $d['priority'] ?? 'normal', $d['status'] ?? 'new', ($d['assigned_to'] ?? null) ?: null,
                ($d['scheduled_date'] ?? null) ?: null, ($d['window_start'] ?? null) ?: null, ($d['window_end'] ?? null) ?: null,
                trim($d['title']), trim($d['description'] ?? ''), trim($d['internal_notes'] ?? ''),
                Auth::id() ?: null, $created, $created,
            ]
        );
        $number = strtoupper((string) setting('job_prefix', 'JOB')) . '-' . date('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
        Database::exec('UPDATE jobs SET number = ? WHERE id = ?', [$number, $id]);
        audit('job_created', 'job', $id, 'number=' . $number . ' title=' . trim($d['title']));

        if (!empty($d['assigned_to'])) {
            notify((int) $d['assigned_to'], t('notif.msg_assigned', ['number' => $number]), u('job&id=' . $id));
        }
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE jobs SET customer_id = ?, site_id = ?, service_id = ?, priority = ?, assigned_to = ?,
                    scheduled_date = ?, window_start = ?, window_end = ?, title = ?, description = ?, internal_notes = ?, updated_at = ?
             WHERE id = ?',
            [
                (int) $d['customer_id'], ($d['site_id'] ?? null) ?: null, ($d['service_id'] ?? null) ?: null, $d['priority'] ?? 'normal',
                ($d['assigned_to'] ?? null) ?: null, ($d['scheduled_date'] ?? null) ?: null, ($d['window_start'] ?? null) ?: null, ($d['window_end'] ?? null) ?: null,
                trim($d['title']), trim($d['description'] ?? ''), trim($d['internal_notes'] ?? ''), now(), $id,
            ]
        );
        audit('job_updated', 'job', $id, 'title=' . trim($d['title']));
    }

    public static function setStatus(int $id, string $status, string $note = ''): void
    {
        $job = self::find($id);
        if (!$job || !array_key_exists($status, job_statuses())) {
            return;
        }
        if ($status === 'done') {
            Database::exec('UPDATE jobs SET status = ?, completed_at = ?, updated_at = ? WHERE id = ?', [$status, now(), now(), $id]);
        } else {
            Database::exec('UPDATE jobs SET status = ?, updated_at = ? WHERE id = ?', [$status, now(), $id]);
        }
        self::addNote($id, $note !== '' ? $note : t('job.status_note', ['status' => job_statuses()[$status]]), $job['status'], $status);
        audit('job_status', 'job', $id, 'number=' . $job['number'] . ' → ' . $status);

        if ($status === 'done' && $job['contract_id']) {
            Contracts::markVisitDone((int) $job['contract_id'], $id);
        }
        if (!empty($job['assigned_to']) && (int) $job['assigned_to'] !== Auth::id()) {
            notify((int) $job['assigned_to'], t('notif.msg_status', ['number' => $job['number'], 'status' => job_statuses()[$status]]), u('job&id=' . $id));
        }
    }

    public static function assign(int $id, ?int $userId): void
    {
        $job = self::find($id);
        if (!$job) {
            return;
        }
        Database::exec('UPDATE jobs SET assigned_to = ?, updated_at = ? WHERE id = ?', [$userId, now(), $id]);
        audit('job_assigned', 'job', $id, 'number=' . $job['number'] . ' → ' . ($userId ?: 'none'));
        if ($userId) {
            notify($userId, t('notif.msg_assigned', ['number' => $job['number']]), u('job&id=' . $id));
        }
    }

    private static function buildWhere(array $f, array &$params): string
    {
        $w = [];
        if (!empty($f['q'])) {
            $w[] = '(j.number LIKE ? OR j.title LIKE ? OR c.name LIKE ? OR s.name LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($f['status'])) {
            $w[] = 'j.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['priority'])) {
            $w[] = 'j.priority = ?';
            $params[] = $f['priority'];
        }
        if (!empty($f['type'])) {
            $w[] = 'j.type = ?';
            $params[] = $f['type'];
        }
        if (!empty($f['technician'])) {
            if ($f['technician'] === 'none') {
                $w[] = 'j.assigned_to IS NULL';
            } else {
                $w[] = 'j.assigned_to = ?';
                $params[] = (int) $f['technician'];
            }
        }
        if (!empty($f['customer'])) {
            $w[] = 'j.customer_id = ?';
            $params[] = (int) $f['customer'];
        }
        if (!empty($f['date'])) {
            $w[] = 'j.scheduled_date = ?';
            $params[] = $f['date'];
        }
        if (!empty($f['from'])) {
            $w[] = 'j.scheduled_date >= ?';
            $params[] = $f['from'];
        }
        if (!empty($f['to'])) {
            $w[] = 'j.scheduled_date <= ?';
            $params[] = $f['to'];
        }
        // Technicians only ever see their own jobs.
        if (Auth::isTechnician()) {
            $w[] = 'j.assigned_to = ?';
            $params[] = Auth::id();
        }
        return $w ? ('WHERE ' . implode(' AND ', $w)) : '';
    }

    public static function count(array $f): int
    {
        $params = [];
        $where = self::buildWhere($f, $params);
        return (int) Database::value(
            'SELECT COUNT(*) FROM jobs j LEFT JOIN customers c ON c.id = j.customer_id LEFT JOIN sites s ON s.id = j.site_id ' . $where,
            $params
        );
    }

    public static function list(array $f, int $limit = 25, int $offset = 0): array
    {
        $params = [];
        $where = self::buildWhere($f, $params);
        $orderMap = [
            'scheduled' => 'j.scheduled_date IS NULL, j.scheduled_date ASC, j.window_start ASC',
            'created'   => 'j.created_at DESC',
            'priority'  => "CASE j.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END ASC, j.scheduled_date ASC",
            'number'    => 'j.number ASC',
        ];
        $order = $orderMap[$f['sort'] ?? 'scheduled'] ?? $orderMap['scheduled'];
        return Database::all(
            'SELECT j.*, c.name AS customer_name, s.name AS site_name, sv.name AS service_name,
                    u.name AS technician_name, u.colour AS technician_colour
             FROM jobs j
             LEFT JOIN customers c ON c.id = j.customer_id
             LEFT JOIN sites s ON s.id = j.site_id
             LEFT JOIN services sv ON sv.id = j.service_id
             LEFT JOIN users u ON u.id = j.assigned_to
             ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /** One day of work, grouped per technician (dispatch board columns). */
    public static function board(string $date): array
    {
        $rows = Database::all(
            "SELECT j.*, c.name AS customer_name, s.name AS site_name, sv.name AS service_name,
                    u.id AS tech_id, u.name AS technician_name, u.colour AS technician_colour
             FROM jobs j
             LEFT JOIN customers c ON c.id = j.customer_id
             LEFT JOIN sites s ON s.id = j.site_id
             LEFT JOIN services sv ON sv.id = j.service_id
             LEFT JOIN users u ON u.id = j.assigned_to
             WHERE j.scheduled_date = ? AND j.status <> 'cancelled'
             ORDER BY u.name ASC, j.window_start ASC",
            [$date]
        );
        $board = [];
        foreach ($rows as $row) {
            $key = $row['tech_id'] ? (string) $row['tech_id'] : 'none';
            if (!isset($board[$key])) {
                $board[$key] = [
                    'name'   => $row['technician_name'] ?? t('job.unassigned'),
                    'colour' => $row['technician_colour'] ?? '',
                    'jobs'   => [],
                ];
            }
            $board[$key]['jobs'][] = $row;
        }
        return $board;
    }

    public static function today(): array
    {
        return self::list(['date' => date('Y-m-d')], 200, 0);
    }

    /** Open jobs whose scheduled date is in the past. */
    public static function overdue(): array
    {
        return Database::all(
            "SELECT j.*, c.name AS customer_name, u.name AS technician_name, u.colour AS technician_colour
             FROM jobs j LEFT JOIN customers c ON c.id = j.customer_id LEFT JOIN users u ON u.id = j.assigned_to
             WHERE j.scheduled_date IS NOT NULL AND j.scheduled_date < ?
               AND j.status IN ('new','scheduled','in_progress','on_hold')
             ORDER BY j.scheduled_date ASC",
            [date('Y-m-d')]
        );
    }

    public static function unassigned(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM jobs WHERE assigned_to IS NULL AND status IN ('new','scheduled')");
    }

    public static function countByStatus(): array
    {
        $out = [];
        foreach (Database::all('SELECT status, COUNT(*) AS c FROM jobs GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    public static function openCount(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM jobs WHERE status IN ('new','scheduled','in_progress','on_hold')");
    }

    public static function recent(int $limit = 8): array
    {
        return self::list([], $limit, 0);
    }

    public static function forCustomer(int $customerId, int $limit = 50): array
    {
        return Database::all(
            'SELECT j.*, sv.name AS service_name, u.name AS technician_name
             FROM jobs j LEFT JOIN services sv ON sv.id = j.service_id LEFT JOIN users u ON u.id = j.assigned_to
             WHERE j.customer_id = ? ORDER BY j.created_at DESC LIMIT ' . (int) $limit,
            [$customerId]
        );
    }

    public static function forContract(int $contractId): array
    {
        return Database::all('SELECT * FROM jobs WHERE contract_id = ? ORDER BY scheduled_date ASC', [$contractId]);
    }

    // ------------------------------------------------------------- checklist

    public static function checklist(int $jobId): array
    {
        return Database::all('SELECT * FROM job_checklist WHERE job_id = ? ORDER BY sort_order ASC, id ASC', [$jobId]);
    }

    public static function addChecklistItem(int $jobId, string $label): void
    {
        $sort = (int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM job_checklist WHERE job_id = ?', [$jobId]);
        Database::insert('INSERT INTO job_checklist (job_id, label, done, sort_order) VALUES (?, ?, 0, ?)', [$jobId, $label, $sort]);
    }

    public static function toggleChecklistItem(int $itemId): void
    {
        $row = Database::one('SELECT * FROM job_checklist WHERE id = ?', [$itemId]);
        if (!$row) {
            return;
        }
        Database::exec('UPDATE job_checklist SET done = ? WHERE id = ?', [$row['done'] ? 0 : 1, $itemId]);
    }

    public static function deleteChecklistItem(int $itemId): void
    {
        Database::exec('DELETE FROM job_checklist WHERE id = ?', [$itemId]);
    }

    // ------------------------------------------------------------- parts used

    public static function partsUsed(int $jobId): array
    {
        return Database::all(
            'SELECT jp.*, p.name AS part_name, p.sku, p.unit FROM job_parts jp JOIN parts p ON p.id = jp.part_id
             WHERE jp.job_id = ? ORDER BY jp.id ASC',
            [$jobId]
        );
    }

    /** Returns an error key, or null on success. */
    public static function addPart(int $jobId, int $partId, float $qty): ?string
    {
        $part = Parts::find($partId);
        $job  = self::find($jobId);
        if (!$part || !$job) {
            return 'job.err_part_missing';
        }
        if ($qty <= 0) {
            return 'job.err_qty';
        }
        if ((float) $part['stock_qty'] < $qty) {
            return 'job.err_no_stock';
        }
        Database::insert(
            'INSERT INTO job_parts (job_id, part_id, qty, unit_price, created_at) VALUES (?, ?, ?, ?, ?)',
            [$jobId, $partId, $qty, (float) $part['sell_price'], now()]
        );
        Parts::adjustStock($partId, -$qty, 'job ' . $job['number']);
        audit('job_part_added', 'job', $jobId, 'sku=' . $part['sku'] . ' qty=' . $qty);
        return null;
    }

    public static function removePart(int $rowId): void
    {
        $row = Database::one('SELECT jp.*, p.sku FROM job_parts jp JOIN parts p ON p.id = jp.part_id WHERE jp.id = ?', [$rowId]);
        if (!$row) {
            return;
        }
        Database::exec('DELETE FROM job_parts WHERE id = ?', [$rowId]);
        Parts::adjustStock((int) $row['part_id'], (float) $row['qty'], 'removed from job');
    }

    public static function partsTotal(int $jobId): float
    {
        return (float) Database::value('SELECT COALESCE(SUM(qty * unit_price), 0) FROM job_parts WHERE job_id = ?', [$jobId]);
    }

    // ------------------------------------------------------------- notes + files

    public static function notes(int $jobId): array
    {
        return Database::all(
            'SELECT n.*, u.name AS author_name, u.role AS author_role FROM job_notes n
             LEFT JOIN users u ON u.id = n.user_id WHERE n.job_id = ? ORDER BY n.created_at ASC, n.id ASC',
            [$jobId]
        );
    }

    public static function addNote(int $jobId, string $note, ?string $from = null, ?string $to = null): int
    {
        return Database::insert(
            'INSERT INTO job_notes (job_id, user_id, note, status_from, status_to, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$jobId, Auth::id() ?: null, $note, $from, $to, now()]
        );
    }

    public static function files(int $jobId): array
    {
        return Database::all(
            'SELECT f.*, u.name AS uploaded_by_name FROM job_files f LEFT JOIN users u ON u.id = f.uploaded_by
             WHERE f.job_id = ? ORDER BY f.created_at DESC',
            [$jobId]
        );
    }

    /** Total sold value of a job: service price + parts used. */
    public static function valueOf(array $job): float
    {
        $labour = (float) ($job['service_price'] ?? 0);
        return round($labour + self::partsTotal((int) $job['id']), 2);
    }
}

// ---------------------------------------------------------------- AMC contracts

final class Contracts
{
    public static function all(bool $activeOnly = false, string $q = ''): array
    {
        $w = [];
        $p = [];
        if ($activeOnly) {
            $w[] = "ct.status = 'active'";
        }
        if ($q !== '') {
            $w[] = '(ct.number LIKE ? OR c.name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($p, $like, $like);
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return Database::all(
            'SELECT ct.*, c.name AS customer_name, s.name AS site_name, sv.name AS service_name,
                    (SELECT COUNT(*) FROM contract_visits v WHERE v.contract_id = ct.id) AS visits_generated,
                    (SELECT COUNT(*) FROM contract_visits v WHERE v.contract_id = ct.id AND v.job_id IS NOT NULL) AS visits_done,
                    (SELECT MIN(v.due_date) FROM contract_visits v WHERE v.contract_id = ct.id AND v.job_id IS NULL) AS next_due
             FROM contracts ct
             LEFT JOIN customers c ON c.id = ct.customer_id
             LEFT JOIN sites s ON s.id = ct.site_id
             LEFT JOIN services sv ON sv.id = ct.service_id
             ' . $where . ' ORDER BY ct.status ASC, ct.end_date ASC',
            $p
        );
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT ct.*, c.name AS customer_name, s.name AS site_name, sv.name AS service_name, sv.price AS service_price
             FROM contracts ct
             LEFT JOIN customers c ON c.id = ct.customer_id
             LEFT JOIN sites s ON s.id = ct.site_id
             LEFT JOIN services sv ON sv.id = ct.service_id
             WHERE ct.id = ?',
            [$id]
        );
    }

    public static function create(array $d): int
    {
        $id = Database::insert(
            'INSERT INTO contracts (customer_id, site_id, service_id, frequency, price_per_visit, start_date, end_date, visits_total, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, \'active\', ?, ?)',
            [
                (int) $d['customer_id'], $d['site_id'] ?: null, $d['service_id'] ?: null,
                $d['frequency'] ?? 'quarterly', (float) ($d['price_per_visit'] ?? 0),
                $d['start_date'], $d['end_date'] ?: null, trim($d['notes'] ?? ''), now(),
            ]
        );
        $number = strtoupper((string) setting('contract_prefix', 'AMC')) . '-' . date('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
        Database::exec('UPDATE contracts SET number = ? WHERE id = ?', [$number, $id]);
        $visits = self::generateVisits($id);
        audit('contract_created', 'contract', $id, 'number=' . $number . ' visits=' . $visits);
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        Database::exec(
            'UPDATE contracts SET site_id = ?, service_id = ?, frequency = ?, price_per_visit = ?, start_date = ?, end_date = ?, notes = ? WHERE id = ?',
            [
                $d['site_id'] ?: null, $d['service_id'] ?: null, $d['frequency'] ?? 'quarterly',
                (float) ($d['price_per_visit'] ?? 0), $d['start_date'], $d['end_date'] ?: null, trim($d['notes'] ?? ''), $id,
            ]
        );
        audit('contract_updated', 'contract', $id);
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!array_key_exists($status, contract_statuses())) {
            return;
        }
        Database::exec('UPDATE contracts SET status = ? WHERE id = ?', [$status, $id]);
        audit('contract_status', 'contract', $id, $status);
    }

    /** Create the visit schedule for a contract (skips visits that already exist). */
    public static function generateVisits(int $contractId): int
    {
        $c = self::find($contractId);
        if (!$c) {
            return 0;
        }
        $months = ['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'annual' => 12][$c['frequency']] ?? 3;
        $end = $c['end_date'] ?: date('Y-m-d', strtotime((string) $c['start_date'] . ' +1 year'));
        $due = (string) $c['start_date'];
        $created = 0;
        $guard = 0;
        while (strtotime($due) <= strtotime($end) && $guard < 60) {
            $guard++;
            $exists = Database::value('SELECT COUNT(*) FROM contract_visits WHERE contract_id = ? AND due_date = ?', [$contractId, $due]);
            if (!$exists) {
                Database::insert('INSERT INTO contract_visits (contract_id, due_date, created_at) VALUES (?, ?, ?)', [$contractId, $due, now()]);
                $created++;
            }
            $due = date('Y-m-d', strtotime($due . ' +' . $months . ' month'));
        }
        Database::exec('UPDATE contracts SET visits_total = (SELECT COUNT(*) FROM contract_visits WHERE contract_id = ?) WHERE id = ?', [$contractId, $contractId]);
        return $created;
    }

    public static function visits(int $contractId): array
    {
        return Database::all(
            'SELECT v.*, j.number AS job_number, j.status AS job_status
             FROM contract_visits v LEFT JOIN jobs j ON j.id = v.job_id
             WHERE v.contract_id = ? ORDER BY v.due_date ASC',
            [$contractId]
        );
    }

    /** Visits that are due (or overdue) and have no job yet. */
    public static function dueVisits(int $days = 30): array
    {
        return Database::all(
            "SELECT v.*, ct.number AS contract_number, ct.customer_id, ct.site_id, ct.service_id, ct.price_per_visit,
                    c.name AS customer_name, s.name AS site_name
             FROM contract_visits v
             JOIN contracts ct ON ct.id = v.contract_id
             JOIN customers c ON c.id = ct.customer_id
             LEFT JOIN sites s ON s.id = ct.site_id
             WHERE v.job_id IS NULL AND ct.status = 'active' AND v.due_date <= ?
             ORDER BY v.due_date ASC",
            [date('Y-m-d', strtotime('+' . $days . ' days'))]
        );
    }

    /** Turn a scheduled visit into a real job. Returns the job id. */
    public static function createJobFromVisit(int $visitId, ?int $assignedTo = null): ?int
    {
        $visit = Database::one(
            'SELECT v.*, ct.number AS contract_number, ct.customer_id, ct.site_id, ct.service_id, ct.price_per_visit, ct.frequency
             FROM contract_visits v JOIN contracts ct ON ct.id = v.contract_id WHERE v.id = ?',
            [$visitId]
        );
        if (!$visit || $visit['job_id']) {
            return null;
        }
        $service = $visit['service_id'] ? Services::find((int) $visit['service_id']) : null;
        $customer = Customers::find((int) $visit['customer_id']);
        $serviceName = $service['name'] ?? t('contract.visit');
        $jobId = Jobs::create([
            'customer_id'    => (int) $visit['customer_id'],
            'site_id'        => $visit['site_id'] ?: null,
            'service_id'     => $visit['service_id'] ?: null,
            'contract_id'    => (int) $visit['contract_id'],
            'type'           => 'contract',
            'priority'       => 'normal',
            'status'         => $assignedTo ? 'scheduled' : 'new',
            'assigned_to'    => $assignedTo,
            'scheduled_date' => $visit['due_date'],
            'title'          => $serviceName . ' — ' . ($customer['name'] ?? ''),
            'description'    => t('contract.job_description', ['number' => (string) $visit['contract_number']]),
        ]);
        Database::exec('UPDATE contract_visits SET job_id = ? WHERE id = ?', [$jobId, $visitId]);
        audit('contract_visit_planned', 'contract', (int) $visit['contract_id'], 'visit=' . $visitId . ' job=' . $jobId);
        return $jobId;
    }

    public static function markVisitDone(int $contractId, int $jobId): void
    {
        Database::exec('UPDATE contract_visits SET job_id = ? WHERE contract_id = ? AND job_id IS NULL AND due_date <= ?',
            [$jobId, $contractId, date('Y-m-d')]);
    }

    public static function expiring(int $days = 60): array
    {
        return Database::all(
            "SELECT ct.*, c.name AS customer_name FROM contracts ct JOIN customers c ON c.id = ct.customer_id
             WHERE ct.status = 'active' AND ct.end_date IS NOT NULL AND ct.end_date <= ?
             ORDER BY ct.end_date ASC",
            [date('Y-m-d', strtotime('+' . $days . ' days'))]
        );
    }

    public static function activeCount(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM contracts WHERE status = 'active'");
    }

    public static function monthlyRecurring(): float
    {
        $sum = 0.0;
        foreach (Database::all("SELECT frequency, price_per_visit FROM contracts WHERE status = 'active'") as $c) {
            $months = ['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'annual' => 12][$c['frequency']] ?? 3;
            $sum += ((float) $c['price_per_visit']) / $months;
        }
        return round($sum, 2);
    }
}

// ---------------------------------------------------------------- invoices

final class Invoices
{
    public static function all(array $f = [], int $limit = 100, int $offset = 0): array
    {
        $w = [];
        $p = [];
        if (!empty($f['q'])) {
            $w[] = '(i.number LIKE ? OR c.name LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($p, $like, $like);
        }
        if (!empty($f['status'])) {
            $w[] = 'i.status = ?';
            $p[] = $f['status'];
        }
        if (!empty($f['customer'])) {
            $w[] = 'i.customer_id = ?';
            $p[] = (int) $f['customer'];
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return Database::all(
            'SELECT i.*, c.name AS customer_name, j.number AS job_number,
                    COALESCE((SELECT SUM(pay.amount) FROM payments pay WHERE pay.invoice_id = i.id), 0) AS paid_amount
             FROM invoices i
             LEFT JOIN customers c ON c.id = i.customer_id
             LEFT JOIN jobs j ON j.id = i.job_id
             ' . $where . ' ORDER BY i.issued_at DESC, i.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $p
        );
    }

    public static function count(array $f = []): int
    {
        $w = [];
        $p = [];
        if (!empty($f['q'])) {
            $w[] = '(i.number LIKE ? OR c.name LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($p, $like, $like);
        }
        if (!empty($f['status'])) {
            $w[] = 'i.status = ?';
            $p[] = $f['status'];
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return (int) Database::value('SELECT COUNT(*) FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id ' . $where, $p);
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT i.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                    c.address AS customer_address, c.city AS customer_city, c.tax_no AS customer_tax_no,
                    c.code AS customer_code, j.number AS job_number
             FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id LEFT JOIN jobs j ON j.id = i.job_id
             WHERE i.id = ?',
            [$id]
        );
    }

    public static function items(int $invoiceId): array
    {
        return Database::all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC', [$invoiceId]);
    }

    public static function payments(int $invoiceId): array
    {
        return Database::all(
            'SELECT p.*, u.name AS recorded_by_name FROM payments p LEFT JOIN users u ON u.id = p.recorded_by
             WHERE p.invoice_id = ? ORDER BY p.paid_at ASC, p.id ASC',
            [$invoiceId]
        );
    }

    public static function paidAmount(int $invoiceId): float
    {
        return (float) Database::value('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?', [$invoiceId]);
    }

    /** Create an invoice from a job: labour (service price) + parts used. */
    public static function createFromJob(int $jobId, ?int $dueDays = null): ?int
    {
        $job = Jobs::find($jobId);
        if (!$job) {
            return null;
        }
        $existing = Database::value('SELECT id FROM invoices WHERE job_id = ?', [$jobId]);
        if ($existing) {
            return (int) $existing;
        }
        $dueDays = $dueDays ?? (int) setting('payment_term_days', '30');
        $id = Database::insert(
            'INSERT INTO invoices (job_id, customer_id, issued_at, due_at, subtotal, tax_rate, tax_amount, total, status, notes, created_by, created_at)
             VALUES (?, ?, ?, ?, 0, 0, 0, 0, \'unpaid\', ?, ?, ?)',
            [
                $jobId, (int) $job['customer_id'], date('Y-m-d'), date('Y-m-d', strtotime('+' . $dueDays . ' days')),
                t('invoice.from_job', ['number' => (string) $job['number']]), Auth::id() ?: null, now(),
            ]
        );
        $number = strtoupper((string) setting('invoice_prefix', 'INV')) . '-' . date('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
        Database::exec('UPDATE invoices SET number = ? WHERE id = ?', [$number, $id]);

        $sort = 1;
        if (!empty($job['service_id']) && (float) $job['service_price'] > 0) {
            self::addItem($id, (string) $job['service_name'], 1, (float) $job['service_price'], $sort++);
        }
        foreach (Jobs::partsUsed($jobId) as $part) {
            self::addItem($id, $part['part_name'] . ' (' . $part['sku'] . ')', (float) $part['qty'], (float) $part['unit_price'], $sort++);
        }
        self::recalculate($id);
        audit('invoice_created', 'invoice', $id, 'number=' . $number . ' job=' . $job['number']);
        return $id;
    }

    public static function addItem(int $invoiceId, string $description, float $qty, float $unitPrice, ?int $sort = null): void
    {
        $sort = $sort ?? ((int) Database::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM invoice_items WHERE invoice_id = ?', [$invoiceId]));
        Database::insert(
            'INSERT INTO invoice_items (invoice_id, description, qty, unit_price, amount, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
            [$invoiceId, trim($description), $qty, $unitPrice, round($qty * $unitPrice, 2), $sort]
        );
    }

    public static function removeItem(int $itemId): void
    {
        $item = Database::one('SELECT * FROM invoice_items WHERE id = ?', [$itemId]);
        if (!$item) {
            return;
        }
        Database::exec('DELETE FROM invoice_items WHERE id = ?', [$itemId]);
        self::recalculate((int) $item['invoice_id']);
    }

    public static function recalculate(int $invoiceId): void
    {
        $inv = self::find($invoiceId);
        if (!$inv) {
            return;
        }
        $subtotal = (float) Database::value('SELECT COALESCE(SUM(amount), 0) FROM invoice_items WHERE invoice_id = ?', [$invoiceId]);
        $rate = (float) $inv['tax_rate'];
        $tax = round($subtotal * $rate / 100, 2);
        Database::exec(
            'UPDATE invoices SET subtotal = ?, tax_amount = ?, total = ? WHERE id = ?',
            [$subtotal, $tax, round($subtotal + $tax, 2), $invoiceId]
        );
        self::refreshStatus($invoiceId);
    }

    public static function setTaxRate(int $invoiceId, float $rate): void
    {
        Database::exec('UPDATE invoices SET tax_rate = ? WHERE id = ?', [$rate, $invoiceId]);
        self::recalculate($invoiceId);
    }

    public static function refreshStatus(int $invoiceId): void
    {
        $inv = self::find($invoiceId);
        if (!$inv || $inv['status'] === 'cancelled') {
            return;
        }
        $paid = self::paidAmount($invoiceId);
        $status = $paid <= 0 ? 'unpaid' : ($paid + 0.01 >= (float) $inv['total'] ? 'paid' : 'partial');
        Database::exec('UPDATE invoices SET status = ? WHERE id = ?', [$status, $invoiceId]);
    }

    public static function addPayment(int $invoiceId, float $amount, string $method, string $reference, string $paidAt): void
    {
        Database::insert(
            'INSERT INTO payments (invoice_id, paid_at, amount, method, reference, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$invoiceId, $paidAt, $amount, $method, $reference, Auth::id() ?: null, now()]
        );
        self::refreshStatus($invoiceId);
        $inv = self::find($invoiceId);
        audit('payment_recorded', 'invoice', $invoiceId, 'number=' . ($inv['number'] ?? '') . ' amount=' . $amount);
    }

    public static function setStatus(int $invoiceId, string $status): void
    {
        Database::exec('UPDATE invoices SET status = ? WHERE id = ?', [$status, $invoiceId]);
        audit('invoice_status', 'invoice', $invoiceId, $status);
    }

    // ------------------------------------------------------------- statistics

    public static function outstandingTotal(): float
    {
        return (float) Database::value(
            "SELECT COALESCE(SUM(i.total - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id), 0)), 0)
             FROM invoices i WHERE i.status IN ('unpaid','partial')"
        );
    }

    public static function overdueCount(): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid','partial') AND due_at IS NOT NULL AND due_at < ?",
            [date('Y-m-d')]
        );
    }

    public static function invoicedThisMonth(): float
    {
        $start = date('Y-m-01');
        $end   = date('Y-m-01', strtotime('+1 month'));
        return (float) Database::value('SELECT COALESCE(SUM(total), 0) FROM invoices WHERE issued_at >= ? AND issued_at < ?', [$start, $end]);
    }

    public static function collectedThisMonth(): float
    {
        $start = date('Y-m-01');
        $end   = date('Y-m-01', strtotime('+1 month'));
        return (float) Database::value('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE paid_at >= ? AND paid_at < ?', [$start, $end]);
    }

    public static function aging(): array
    {
        $rows = Database::all(
            "SELECT i.*, c.name AS customer_name, COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id), 0) AS paid_amount
             FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id
             WHERE i.status IN ('unpaid','partial') ORDER BY i.due_at ASC"
        );
        $buckets = ['current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0];
        foreach ($rows as $row) {
            $due = $row['due_at'] ? strtotime((string) $row['due_at']) : time();
            $days = (int) floor((time() - $due) / 86400);
            $open = (float) $row['total'] - (float) $row['paid_amount'];
            if ($days <= 0) {
                $buckets['current'] += $open;
            } elseif ($days <= 30) {
                $buckets['d30'] += $open;
            } elseif ($days <= 60) {
                $buckets['d60'] += $open;
            } else {
                $buckets['d90'] += $open;
            }
        }
        return $buckets;
    }

    public static function recent(int $limit = 6): array
    {
        return self::all([], $limit, 0);
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

    public static function existsFor(int $userId, string $message): bool
    {
        return (bool) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND message = ?', [$userId, $message]);
    }
}

// ---------------------------------------------------------------- alerts

final class Alerts
{
    /** Daily alerts for due contract visits, overdue jobs and low stock. */
    public static function generateIfDue(int $days = 30): int
    {
        $today = date('Y-m-d');
        if ((string) setting('alerts_last_run', '') === $today) {
            return 0;
        }
        save_setting('alerts_last_run', $today);

        $office = Users::office();
        if (!$office) {
            return 0;
        }
        $created = 0;

        foreach (Contracts::dueVisits($days) as $visit) {
            $msg = t('notif.msg_visit', ['contract' => (string) $visit['contract_number'], 'customer' => (string) $visit['customer_name'], 'date' => (string) $visit['due_date']]);
            foreach ($office as $user) {
                if (!Notifications::existsFor((int) $user['id'], $msg)) {
                    notify((int) $user['id'], $msg, u('contract&id=' . (int) $visit['contract_id']));
                    $created++;
                }
            }
        }

        foreach (Jobs::overdue() as $job) {
            $msg = t('notif.msg_overdue_job', ['number' => (string) $job['number'], 'date' => (string) $job['scheduled_date']]);
            foreach ($office as $user) {
                if (!Notifications::existsFor((int) $user['id'], $msg)) {
                    notify((int) $user['id'], $msg, u('job&id=' . (int) $job['id']));
                    $created++;
                }
            }
        }

        foreach (Parts::lowStock() as $part) {
            $msg = t('notif.msg_low_stock', ['name' => (string) $part['name'], 'sku' => (string) $part['sku'], 'qty' => (string) $part['stock_qty']]);
            foreach ($office as $user) {
                if (!Notifications::existsFor((int) $user['id'], $msg)) {
                    notify((int) $user['id'], $msg, u('parts'));
                    $created++;
                }
            }
        }
        return $created;
    }
}

// ---------------------------------------------------------------- audit log

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

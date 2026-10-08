<?php
declare(strict_types=1);

/**
 * Authentication & authorization.
 * Roles: admin (full control) · agent (works tickets) · user (requester).
 */
final class Auth
{
    private const MAX_FAILURES  = 5;   // allowed failed logins per window
    private const WINDOW_MIN    = 15;  // minutes
    private const SESSION_TTL   = 7200; // 2 hours of inactivity

    public static function boot(): void
    {
        $last = (int) ($_SESSION['last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > self::SESSION_TTL) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['flash'][] = ['type' => 'info', 'msg' => 'Your session expired. Please sign in again.'];
            header('Location: ' . u('login'));
            exit;
        }
        $_SESSION['last_activity'] = time();
    }

    public static function current(): ?array
    {
        static $user = null;
        if ($user === null && !empty($_SESSION['uid'])) {
            $user = Database::one('SELECT * FROM users WHERE id = ? AND active = 1', [(int) $_SESSION['uid']]);
            if (!$user) {
                $user = [];
            }
        }
        return $user ?: null;
    }

    public static function id(): int
    {
        return (int) ($_SESSION['uid'] ?? 0);
    }

    public static function role(): string
    {
        return self::current()['role'] ?? 'guest';
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isAgent(): bool
    {
        return self::role() === 'agent';
    }

    public static function isStaff(): bool
    {
        return self::isAdmin() || self::isAgent();
    }

    public static function requireLogin(): void
    {
        if (!self::current()) {
            flash('info', 'Please sign in to continue.');
            redirect('login');
        }
    }

    public static function requireStaff(): void
    {
        self::requireLogin();
        if (!self::isStaff()) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    /**
     * Attempt a login. Returns the user row on success, or null.
     * Callers check Auth::throttled() first for a friendly lockout message.
     */
    public static function attempt(string $login, string $password): ?array
    {
        $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $login = trim($login);

        if (self::throttled($login, $ip)) {
            return null;
        }

        $user = Database::one(
            'SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1',
            [$login, $login]
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            Database::exec(
                'INSERT INTO login_attempts (username, ip, success, attempted_at) VALUES (?, ?, 1, ?)',
                [$login, $ip, now()]
            );
            Database::exec('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), (int) $user['id']]);
            session_regenerate_id(true);
            $_SESSION['uid']       = (int) $user['id'];
            $_SESSION['csrf']      = bin2hex(random_bytes(32));
            $_SESSION['last_activity'] = time();
            audit('login', 'user', $user['id']);
            return $user;
        }

        Database::exec(
            'INSERT INTO login_attempts (username, ip, success, attempted_at) VALUES (?, ?, 0, ?)',
            [$login, $ip, now()]
        );
        audit('login_failed', 'user', null, 'login=' . $login);
        return null;
    }

    public static function throttled(string $login, string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MIN * 60);
        $count = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE success = 0 AND attempted_at >= ? AND (username = ? OR ip = ?)',
            [$since, $login, $ip]
        );
        return $count >= self::MAX_FAILURES;
    }

    public static function logout(): void
    {
        if (self::id()) {
            audit('logout', 'user', self::id());
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        redirect('login');
    }
}

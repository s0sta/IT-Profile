<?php
declare(strict_types=1);

/**
 * Thin PDO wrapper with a dual driver: SQLite (zero-config demo) and
 * MySQL (production, e.g. Hostinger). All queries use prepared statements.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';
    /** @var array<string,bool> cached table.column existence */
    private static array $columns = [];

    public static function init(array $cfg): void
    {
        self::$driver = $cfg['driver'] === 'mysql' ? 'mysql' : 'sqlite';
        if (self::$driver === 'sqlite') {
            $path = $cfg['path'] ?? (APP_ROOT . '/data/idara.sqlite');
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            $port = $cfg['port'] ?? '3306';
            self::$pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $port, $cfg['name']),
                $cfg['user'],
                $cfg['pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('Database has not been initialised. Run install.php first.');
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = [], $default = null)
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    /** Returns affected row count. */
    public static function exec(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /** Insert and return the new auto-increment id. */
    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    /**
     * Whether a table has a column — cached, and driver-aware. Used so the app
     * keeps working on a database that has not yet run a newer migration
     * (e.g. must_change_password) and before/after it does.
     */
    public static function hasColumn(string $table, string $col): bool
    {
        $key = $table . '.' . $col;
        if (!array_key_exists($key, self::$columns)) {
            $found = false;
            if (self::$driver === 'sqlite') {
                foreach (self::all('PRAGMA table_info(' . $table . ')') as $row) {
                    if (($row['name'] ?? '') === $col) {
                        $found = true;
                        break;
                    }
                }
            } else {
                foreach (self::all('SHOW COLUMNS FROM `' . $table . '`') as $row) {
                    if (($row['Field'] ?? '') === $col) {
                        $found = true;
                        break;
                    }
                }
            }
            self::$columns[$key] = $found;
        }
        return self::$columns[$key];
    }
}

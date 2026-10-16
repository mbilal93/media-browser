<?php
declare(strict_types=1);
namespace MediaBrowser;

use PDO;
use InvalidArgumentException;
use RuntimeException;

final class Database
{
    public static function connect(array $options, string $storage): PDO
    {
        $driver = $options['driver'] ?? 'sqlite';
        if (!in_array($driver, ['sqlite', 'mysql'], true)) throw new InvalidArgumentException('Database driver must be sqlite or mysql.');
        if (!in_array($driver, PDO::getAvailableDrivers(), true)) throw new RuntimeException('Enable the pdo_' . $driver . ' extension.');
        $dsn = $options['dsn'] ?? '';
        if ($dsn === '') {
            if ($driver === 'sqlite') {
                $dsn = 'sqlite:' . $storage . '/media.sqlite';
            } else {
                $host = $options['host'] ?? '127.0.0.1';
                $port = $options['port'] ?? 3306;
                $name = $options['name'] ?? '';
                if ($name === '' || strpbrk($host . $name, ";\r\n") !== false || !is_numeric($port) || $port < 1 || $port > 65535) throw new InvalidArgumentException('Set a valid MySQL host, port and database name.');
                $dsn = 'mysql:host=' . $host . ';port=' . (int) $port . ';dbname=' . $name . ';charset=utf8mb4';
            }
        }
        if (!str_starts_with($dsn, $driver . ':')) throw new InvalidArgumentException('Database DSN does not match the configured driver.');
        $pdoOptions = ($options['options'] ?? []) + [PDO::ATTR_TIMEOUT => 5];
        $pdoOptions[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $pdoOptions[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        if ($driver === 'mysql') $pdoOptions[PDO::ATTR_EMULATE_PREPARES] = false;
        return new PDO($dsn, $options['username'] ?? null, $options['password'] ?? null, $pdoOptions);
    }

    public static function table(string $table): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/D', $table)) throw new InvalidArgumentException('Invalid media table name. Use letters, numbers and underscores (maximum 64 characters).');
        return '`' . $table . '`';
    }

    public static function schema(string $driver, string $table): string
    {
        $table = self::table($table);
        if ($driver === 'sqlite') return 'CREATE TABLE IF NOT EXISTS ' . $table . ' (id TEXT PRIMARY KEY, name TEXT NOT NULL, mime TEXT NOT NULL, extension TEXT NOT NULL, size INTEGER NOT NULL, created_at TEXT NOT NULL)';
        if ($driver !== 'mysql') throw new InvalidArgumentException('Unsupported database driver.');
        return 'CREATE TABLE IF NOT EXISTS ' . $table . ' (
            id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            name VARCHAR(240) NOT NULL,
            mime VARCHAR(127) NOT NULL,
            extension VARCHAR(16) NOT NULL,
            size BIGINT UNSIGNED NOT NULL,
            created_at CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            INDEX media_created_order (created_at, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }
}

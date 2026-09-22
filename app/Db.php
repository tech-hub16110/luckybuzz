<?php

declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
            self::ensureSchema(self::$pdo);
        }

        return self::$pdo;
    }

    /** Test helper: force a reconnect against whatever config says next. */
    public static function reopen(): void
    {
        self::$pdo = null;
    }

    public static function driver(): string
    {
        return Config::driver();
    }

    private static function connect(): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $db = Config::db();

        if (Config::isMysql()) {
            foreach (['host', 'database', 'username', 'password'] as $required) {
                if (empty($db[$required])) {
                    throw new RuntimeException(
                        "db.{$required} is not set — copy htdocs/app/config.local.example.php to htdocs/app/config.local.php and fill it in",
                    );
                }
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                (int) ($db['port'] ?? 3306),
                $db['database'],
                $db['charset'] ?? 'utf8mb4',
            );

            return new PDO($dsn, (string) $db['username'], (string) $db['password'], $options);
        }

        $file = Config::db()['sqlite_path'] ?? null;
        if (empty($file)) {
            throw new RuntimeException('db.sqlite_path is not set');
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }

        $pdo = new PDO('sqlite:' . $file, null, null, $options);

        // TRUNCATE rather than WAL: this project's working copy sits on a
        // fuseblk mount where WAL's shared-memory file is not reliable.
        $pdo->exec('PRAGMA journal_mode = TRUNCATE');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $pdo;
    }

    /**
     * Create the tables the first time they are missing, and not after that.
     *
     * On shared hosting every request is metered, so this costs one cheap
     * catalog query rather than a dozen CREATE IF NOT EXISTS statements.
     */
    private static function ensureSchema(PDO $db): void
    {
        if (self::tableCount($db) === count(Schema::TABLES)) {
            return;
        }

        foreach (Schema::statements() as $statement) {
            $db->exec($statement);
        }

        if (self::tableCount($db) !== count(Schema::TABLES)) {
            throw new RuntimeException(
                'database is not fully set up; import the SQL from bin/schema.php via phpMyAdmin',
            );
        }
    }

    private static function tableCount(PDO $db): int
    {
        $in = implode(',', array_fill(0, count(Schema::TABLES), '?'));

        if (Config::isMysql()) {
            $st = $db->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name IN ({$in})"
            );
        } else {
            $st = $db->prepare(
                "SELECT COUNT(*) FROM sqlite_master
                 WHERE type = 'table' AND name IN ({$in})"
            );
        }

        $st->execute(Schema::TABLES);

        return (int) $st->fetchColumn();
    }

    /**
     * Upsert, because the two engines spell it differently.
     *
     * MySQL 8 deprecates VALUES() in favour of row aliases, but VALUES() is
     * still accepted and is the only form that also works on MariaDB, which is
     * what most InfinityFree database servers actually are.
     */
    public static function putMeta(string $key, string $value): void
    {
        $sql = Config::isMysql()
            ? 'INSERT INTO meta (meta_key, meta_value) VALUES (?, ?)
               ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)'
            : 'INSERT INTO meta (meta_key, meta_value) VALUES (?, ?)
               ON CONFLICT(meta_key) DO UPDATE SET meta_value = excluded.meta_value';

        self::conn()->prepare($sql)->execute([$key, $value]);
    }

    public static function getMeta(string $key, int $default = 0): int
    {
        $st = self::conn()->prepare('SELECT meta_value FROM meta WHERE meta_key = ?');
        $st->execute([$key]);
        $value = $st->fetchColumn();

        return $value === false ? $default : (int) $value;
    }
}

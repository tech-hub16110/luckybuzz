<?php

declare(strict_types=1);

final class Config
{
    /** @var array<string, mixed> */
    private static array $values = [];

    /**
     * Load the committed defaults, then overlay config.local.php when it exists.
     *
     * config.local.php is git-ignored and holds the database credentials, so it
     * cannot be committed by accident. It is also the local-dev escape hatch: any
     * key in settings.php can be overridden there without touching version
     * control.
     *
     * The name deliberately differs by more than case from Config.php: this
     * project's working copy is on a case-insensitive mount, where a file called
     * config.php would *be* Config.php and the loader would require the class
     * file back into itself.
     */
    public static function load(string $path): void
    {
        self::$values = require $path;

        $override = dirname($path) . '/config.local.php';
        if (is_file($override)) {
            $loaded = require $override;
            if (is_array($loaded)) {
                self::$values = self::mergeArrays(self::$values, $loaded);
            }
        }
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $replacement
     * @return array<string, mixed>
     */
    private static function mergeArrays(array $base, array $replacement): array
    {
        foreach ($replacement as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeArrays($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$values[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$values[$key] = $value;
    }

    public static function int(string $key): int
    {
        $default = isset(self::$values[$key]) ? (int) self::$values[$key] : 0;
        try {
            $metaVal = Db::getMeta('cfg_' . $key, -1);
            if ($metaVal >= 0) {
                return $metaVal;
            }
        } catch (Throwable) {
            // DB not ready or during early bootstrap
        }

        return $default;
    }

    /** @return array<string, mixed> */
    public static function tier(string $tier): array
    {
        $tiers = self::$values['tiers'];
        if (!isset($tiers[$tier])) {
            throw new InvalidArgumentException("unknown tier {$tier}");
        }

        return $tiers[$tier];
    }

    /** @return list<string> */
    public static function tierNames(): array
    {
        return array_keys(self::$values['tiers']);
    }

    public static function prize(string $kind): int
    {
        $default = (int) (self::$values['prizes'][$kind] ?? 0);
        try {
            $metaVal = Db::getMeta('cfg_prize_' . $kind, -1);
            if ($metaVal >= 0) {
                return $metaVal;
            }
        } catch (Throwable) {
            // DB not ready
        }

        return $default;
    }

    /** @return array<string, mixed> */
    public static function db(): array
    {
        $db = self::$values['db'] ?? [];
        if (!isset($db['driver'])) {
            throw new RuntimeException('settings: no db.driver configured');
        }

        return $db;
    }

    public static function driver(): string
    {
        return (string) self::db()['driver'];
    }

    public static function isMysql(): bool
    {
        return self::driver() === 'mysql';
    }
}

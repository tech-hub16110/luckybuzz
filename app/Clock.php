<?php

declare(strict_types=1);

final class Clock
{
    private static ?DateTimeImmutable $frozen = null;

    public static function now(): DateTimeImmutable
    {
        return self::$frozen ?? new DateTimeImmutable('now', self::zone());
    }

    public static function freeze(?string $iso): void
    {
        self::$frozen = $iso === null ? null : new DateTimeImmutable($iso, self::zone());
    }

    public static function zone(): DateTimeZone
    {
        return new DateTimeZone(Config::get('timezone'));
    }
}

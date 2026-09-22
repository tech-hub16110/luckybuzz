<?php

declare(strict_types=1);

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(Config::get('session_name'));
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }

    public static function validateUsername(string $username): ?string
    {
        if (!preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
            return 'Username must be 3-20 characters: lowercase letters, digits or underscore.';
        }

        return null;
    }

    public static function validatePassword(string $password): ?string
    {
        if (strlen($password) < 6) {
            return 'Password must be at least 6 characters.';
        }

        return null;
    }

    /** @return array<string, mixed> the new user row */
    public static function register(string $username, string $password): array
    {
        $db = Db::conn();
        $at = Clock::now()->format(DateTimeInterface::ATOM);

        $db->prepare(
            'INSERT INTO users (username, pass_hash, secret, created_at, streak)
             VALUES (?, ?, ?, ?, 0)'
        )->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            bin2hex(random_bytes(16)),
            $at,
        ]);

        $userId = (int) $db->lastInsertId();
        Coins::grant($userId, Config::int('signup_grant'), 'signup_grant');

        return self::userById($userId);
    }

    /** @return array<string, mixed>|null */
    public static function attempt(string $username, string $password): ?array
    {
        $st = Db::conn()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$username]);
        $user = $st->fetch();

        if ($user === false || !password_verify($password, (string) $user['pass_hash'])) {
            return null;
        }

        return $user;
    }

    public static function login(int $userId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        $_SESSION['uid'] = $userId;
    }

    public static function logout(): void
    {
        unset($_SESSION['uid']);
    }

    public static function userId(): ?int
    {
        $id = $_SESSION['uid'] ?? null;

        return $id === null ? null : (int) $id;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $id = self::userId();

        return $id === null ? null : self::userById($id);
    }

    /** @return array<string, mixed> */
    public static function userById(int $userId): array
    {
        $st = Db::conn()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$userId]);
        $user = $st->fetch();

        if ($user === false) {
            throw new RuntimeException('session refers to a missing user');
        }

        return $user;
    }
}

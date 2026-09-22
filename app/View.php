<?php

declare(strict_types=1);

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $name, array $data = [], string $title = ''): void
    {
        $data['title'] = $title === '' ? Config::get('app_name') : $title . ' · ' . Config::get('app_name');
        $data['user'] = $data['user'] ?? Auth::user();
        $data['balance'] = $data['balance'] ?? ($data['user'] === null ? 0 : Coins::balance((int) $data['user']['id']));
        $data['is_admin'] = Admin::isAdmin();

        $content = self::capture(__DIR__ . '/views/' . $name . '.php', $data);
        echo self::capture(__DIR__ . '/views/layout.php', $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}

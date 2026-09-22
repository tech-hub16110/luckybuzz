<?php

declare(strict_types=1);

/**
 * The only ways coins enter a wallet. Everything here is free and time-gated —
 * there is no other funding path, by design.
 */
final class Bonuses
{
    /** @return array{claimed: bool, message: string, amount: int, streak: int} */
    public static function claimDaily(int $userId): array
    {
        $db = Db::conn();
        $today = Clock::now()->format('Y-m-d');
        $yesterday = Clock::now()->modify('-1 day')->format('Y-m-d');

        $st = $db->prepare('SELECT last_bonus_day, streak FROM users WHERE id = ?');
        $st->execute([$userId]);
        $row = $st->fetch();

        if ($row['last_bonus_day'] === $today) {
            return ['claimed' => false, 'message' => 'Daily bonus already collected today.', 'amount' => 0, 'streak' => (int) $row['streak']];
        }

        $streak = $row['last_bonus_day'] === $yesterday ? (int) $row['streak'] + 1 : 1;
        $step = min(Config::int('streak_bonus') * $streak, Config::int('streak_bonus_max'));
        $amount = Config::int('daily_bonus') + $step;

        $db->prepare('UPDATE users SET last_bonus_day = ?, streak = ? WHERE id = ?')
            ->execute([$today, $streak, $userId]);
        Coins::grant($userId, $amount, 'daily_bonus');

        return [
            'claimed' => true,
            'message' => "+{$amount} coins — day {$streak} of your streak.",
            'amount' => $amount,
            'streak' => $streak,
        ];
    }

    /** @return array{claimed: bool, message: string, amount: int} */
    public static function claimRefill(int $userId): array
    {
        $status = self::refillStatus($userId);
        if (!$status['eligible']) {
            return ['claimed' => false, 'message' => $status['why'], 'amount' => 0];
        }

        if (Coins::balance($userId) >= Config::int('refill_floor')) {
            return ['claimed' => false, 'message' => 'Refill is only available when you are under ' . Config::int('refill_floor') . ' coins.', 'amount' => 0];
        }

        $amount = Config::int('refill_amount');
        Coins::grant($userId, $amount, 'refill');

        return ['claimed' => true, 'message' => "+{$amount} coins of pocket money.", 'amount' => $amount];
    }

    /** @return array{eligible: bool, why: string, usedToday: int, cap: int} */
    public static function refillStatus(int $userId): array
    {
        $db = Db::conn();
        $cap = Config::int('refill_daily_cap');
        $cooldown = Config::int('refill_cooldown');
        $today = Clock::now()->format('Y-m-d');

        $st = $db->prepare(
            "SELECT COUNT(*) AS n, MAX(created_at) AS last_at FROM ledger
             WHERE user_id = ? AND reason = 'refill' AND substr(created_at, 1, 10) = ?"
        );
        $st->execute([$userId, $today]);
        $row = $st->fetch();
        $used = (int) $row['n'];

        if ($used * Config::int('refill_amount') >= $cap) {
            return ['eligible' => false, 'why' => "That's all the refill pocket money for today ({$cap} coins).", 'usedToday' => $used, 'cap' => $cap];
        }

        if ($row['last_at'] !== null) {
            $since = Clock::now()->getTimestamp() - strtotime((string) $row['last_at']);
            if ($since < $cooldown) {
                $mins = intdiv($cooldown - $since, 60) + 1;

                return ['eligible' => false, 'why' => "Next refill in about {$mins} minutes.", 'usedToday' => $used, 'cap' => $cap];
            }
        }

        return ['eligible' => true, 'why' => '', 'usedToday' => $used, 'cap' => $cap];
    }

    /** @return array{claimedToday: bool, streak: int, daily: int} */
    public static function dailyStatus(int $userId): array
    {
        $st = Db::conn()->prepare('SELECT last_bonus_day, streak FROM users WHERE id = ?');
        $st->execute([$userId]);
        $row = $st->fetch();

        return [
            'claimedToday' => $row['last_bonus_day'] === Clock::now()->format('Y-m-d'),
            'streak' => (int) $row['streak'],
            'daily' => Config::int('daily_bonus'),
        ];
    }
}

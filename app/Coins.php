<?php

declare(strict_types=1);

final class InsufficientCoins extends RuntimeException
{
}

/**
 * Append-only coin ledger. A user's balance is always SUM(delta) over this
 * table — nothing anywhere mutates a cached balance column, so the history is
 * the truth and can be re-audited at any time.
 */
final class Coins
{
    /**
     * The complete set of movements this wallet can record.
     *
     * Deliberately absent: any reason implying money. Buzz Coins cannot be
     * bought, topped up, deposited, withdrawn, exchanged or transferred, so
     * there is no reason code for it and Coins::record() rejects one. Tests
     * assert this list stays free of cash paths.
     */
    public const REASONS = [
        'signup_grant',
        'daily_bonus',
        'streak_bonus',
        'refill',
        'ticket',
        'win',
        'jackpot',
        'surprise_box',
        'refund',
    ];

    public static function balance(int $userId): int
    {
        $st = Db::conn()->prepare('SELECT COALESCE(SUM(delta), 0) FROM ledger WHERE user_id = ?');
        $st->execute([$userId]);

        return (int) $st->fetchColumn();
    }

    public static function record(
        int $userId,
        int $delta,
        string $reason,
        ?string $refTable = null,
        ?int $refId = null,
    ): void {
        if (!in_array($reason, self::REASONS, true)) {
            throw new InvalidArgumentException("refused ledger reason: {$reason}");
        }
        if ($delta === 0) {
            throw new InvalidArgumentException('refused zero-delta ledger entry');
        }

        $st = Db::conn()->prepare(
            'INSERT INTO ledger (user_id, delta, reason, ref_table, ref_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $userId,
            $delta,
            $reason,
            $refTable,
            $refId,
            Clock::now()->format(DateTimeInterface::ATOM),
        ]);
    }

    public static function grant(int $userId, int $amount, string $reason): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('grant must be positive');
        }

        self::record($userId, $amount, $reason);
    }

    /**
     * @throws InsufficientCoins when the wallet cannot cover the charge
     */
    public static function charge(int $userId, int $amount, string $reason, ?string $refTable = null, ?int $refId = null): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('charge must be positive');
        }
        if (self::balance($userId) < $amount) {
            throw new InsufficientCoins('not enough coins');
        }

        self::record($userId, -$amount, $reason, $refTable, $refId);
    }

    /** @return list<array<string, mixed>> */
    public static function history(int $userId, int $limit = 50): array
    {
        $st = Db::conn()->prepare(
            'SELECT delta, reason, ref_table, ref_id, created_at
             FROM ledger WHERE user_id = ? ORDER BY id DESC LIMIT ?'
        );
        $st->bindValue(1, $userId, PDO::PARAM_INT);
        $st->bindValue(2, $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll();
    }

    /** Total coins a user has been granted for free, for the "earned, not bought" panel. */
    public static function earnedFree(int $userId): int
    {
        $reasons = ['signup_grant', 'daily_bonus', 'streak_bonus', 'refill', 'win', 'jackpot', 'surprise_box'];
        $in = implode(',', array_fill(0, count($reasons), '?'));
        $st = Db::conn()->prepare("SELECT COALESCE(SUM(delta), 0) FROM ledger WHERE user_id = ? AND reason IN ({$in})");
        $st->execute(array_merge([$userId], $reasons));

        return (int) $st->fetchColumn();
    }
}

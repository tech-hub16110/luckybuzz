<?php

declare(strict_types=1);

final class Tickets
{
    public static function isNumber(string $number): bool
    {
        return (bool) preg_match('/^[0-9]{4,5}$/', $number);
    }

    public static function quickPick(int $digits = 5): string
    {
        $max = $digits === 5 ? 99999 : 9999;
        return str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{ticket_id: int, number: string, cost: int, balance: int}
     * @throws RuntimeException when the draw is closed or the user is capped out
     */
    public static function purchase(int $userId, int $drawId, string $number, int $sem = 1): array
    {
        if (!self::isNumber($number)) {
            throw new RuntimeException('Enter a valid 4 or 5 digit number.');
        }

        $db = Db::conn();
        $st = $db->prepare('SELECT * FROM draws WHERE id = ?');
        $st->execute([$drawId]);
        $draw = $st->fetch();

        if ($draw === false) {
            throw new RuntimeException('That draw no longer exists.');
        }
        if ($draw['result'] !== null || $draw['draw_at'] <= Clock::now()->format(DateTimeInterface::ATOM)) {
            throw new RuntimeException('That draw is closed.');
        }

        $cap = Config::int('tickets_per_draw');
        if (self::countFor($userId, $drawId) >= $cap) {
            throw new RuntimeException("You already have {$cap} tickets in this draw.");
        }

        $sem = max(1, min(95, $sem));
        $cost = Config::int('ticket_cost') * $sem;
        $at = Clock::now()->format(DateTimeInterface::ATOM);

        $db->beginTransaction();
        try {
            $ins = $db->prepare(
                'INSERT INTO tickets (user_id, draw_id, number, cost, created_at) VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute([$userId, $drawId, $number, $cost, $at]);
            $ticketId = (int) $db->lastInsertId();

            Coins::charge($userId, $cost, 'ticket', 'tickets', $ticketId);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return [
            'ticket_id' => $ticketId,
            'number' => $number,
            'cost' => $cost,
            'balance' => Coins::balance($userId),
        ];
    }

    /**
     * @param list<array{number: string, sem: int}> $items
     * @return array{count: int, total_cost: int, balance: int, numbers: list<string>}
     */
    public static function purchaseBulk(int $userId, int $drawId, array $items): array
    {
        if ($items === []) {
            throw new RuntimeException('Cart is empty.');
        }

        $db = Db::conn();
        $st = $db->prepare('SELECT * FROM draws WHERE id = ?');
        $st->execute([$drawId]);
        $draw = $st->fetch();

        if ($draw === false) {
            throw new RuntimeException('That draw no longer exists.');
        }
        if ($draw['result'] !== null || $draw['draw_at'] <= Clock::now()->format(DateTimeInterface::ATOM)) {
            throw new RuntimeException('That draw is closed.');
        }

        $baseCost = Config::int('ticket_cost');
        $at = Clock::now()->format(DateTimeInterface::ATOM);
        $totalCost = 0;
        $processed = [];

        foreach ($items as $item) {
            $num = trim((string) ($item['number'] ?? ''));
            if (!self::isNumber($num)) {
                if (preg_match('/^\d{1,5}$/', $num)) {
                    $num = str_pad($num, 5, '0', STR_PAD_LEFT);
                } else {
                    throw new RuntimeException("Invalid ticket number: {$num}");
                }
            }

            $sem = (int) ($item['sem'] ?? 1);
            if ($sem < 1 || $sem > 95) {
                $sem = 1;
            }

            $cost = $baseCost * $sem;
            $totalCost += $cost;
            $processed[] = ['number' => $num, 'cost' => $cost, 'sem' => $sem];
        }

        $userBalance = Coins::balance($userId);
        if ($userBalance < $totalCost) {
            throw new InsufficientCoins("Not enough balance (₹{$userBalance}) for total ₹{$totalCost}. Coins are allocated directly by the Game Owner/Admin.");
        }

        $db->beginTransaction();
        try {
            $ins = $db->prepare(
                'INSERT INTO tickets (user_id, draw_id, number, cost, created_at) VALUES (?, ?, ?, ?, ?)'
            );
            $firstId = 0;
            foreach ($processed as $p) {
                $ins->execute([$userId, $drawId, $p['number'], $p['cost'], $at]);
                if ($firstId === 0) {
                    $firstId = (int) $db->lastInsertId();
                }
            }

            Coins::charge($userId, $totalCost, 'ticket', 'tickets', $firstId);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return [
            'count' => count($processed),
            'total_cost' => $totalCost,
            'balance' => Coins::balance($userId),
            'numbers' => array_column($processed, 'number'),
        ];
    }

    public static function countFor(int $userId, int $drawId): int
    {
        $st = Db::conn()->prepare('SELECT COUNT(*) FROM tickets WHERE user_id = ? AND draw_id = ?');
        $st->execute([$userId, $drawId]);

        return (int) $st->fetchColumn();
    }

    /**
     * A user's tickets with their draw and grading, newest draw first.
     *
     * @return list<array<string, mixed>>
     */
    public static function mine(int $userId, int $limit = 40): array
    {
        $db = Db::conn();
        $st = $db->prepare(
            'SELECT t.id, t.number, t.cost, t.created_at,
                    d.id AS draw_id, d.day, d.tier, d.draw_at, d.result, d.settled_at,
                    w.match_kind, w.amount
             FROM tickets t
             JOIN draws d ON d.id = t.draw_id
             LEFT JOIN wins w ON w.ticket_id = t.id
             WHERE t.user_id = :user
             ORDER BY d.draw_at DESC, t.id DESC
             LIMIT :limit'
        );
        $st->bindValue(':user', $userId, PDO::PARAM_INT);
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll();
    }

    /** @return array{tickets: int, spent: int, won: int, best: int, open: int} */
    public static function summary(int $userId): array
    {
        $db = Db::conn();

        $st = $db->prepare(
            "SELECT COALESCE(SUM(-delta), 0) FROM ledger
             WHERE user_id = ? AND reason = 'ticket'"
        );
        $st->execute([$userId]);
        $spent = (int) $st->fetchColumn();

        $st = $db->prepare(
            'SELECT COUNT(*) AS tickets,
                    COALESCE(SUM(w.amount), 0) AS won,
                    COALESCE(MAX(w.amount), 0) AS best
             FROM tickets t LEFT JOIN wins w ON w.ticket_id = t.id
             WHERE t.user_id = ?'
        );
        $st->execute([$userId]);
        $row = $st->fetch();

        $st = $db->prepare(
            'SELECT COUNT(*) FROM tickets t JOIN draws d ON d.id = t.draw_id
             WHERE t.user_id = ? AND d.result IS NULL'
        );
        $st->execute([$userId]);

        return [
            'tickets' => (int) $row['tickets'],
            'spent' => $spent,
            'won' => (int) $row['won'],
            'best' => (int) $row['best'],
            'open' => (int) $st->fetchColumn(),
        ];
    }
}

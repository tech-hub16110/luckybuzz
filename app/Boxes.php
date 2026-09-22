<?php

declare(strict_types=1);

/**
 * The surprise box attached to every draw.
 *
 * One box per player per draw they entered, opened after that draw settles.
 * Contents are derived from the draw's already-published commitment plus the
 * player's id, so a box was decided the moment the draw was scheduled — before
 * the number existed, let alone before anyone tapped. There is nothing to pick,
 * no second chance, and no streak of near misses the server can lean on.
 *
 * Boxes are granted, never sold. They are not in Coins::REASONS under any name
 * that could charge coins for one, and a test keeps it that way. That is the
 * difference between this and the mechanic that gets loot boxes regulated.
 */
final class Boxes
{
    /** @return list<array{coins: int, weight: int}> */
    public static function table(): array
    {
        return Config::get('surprise_box')['rewards'];
    }

    public static function totalWeight(): int
    {
        return array_sum(array_column(self::table(), 'weight'));
    }

    /** @return list<array{coins: int, weight: int, chance: string}> */
    public static function odds(): array
    {
        $total = self::totalWeight();
        $out = [];

        foreach (self::table() as $row) {
            $out[] = [
                'coins' => (int) $row['coins'],
                'weight' => (int) $row['weight'],
                'chance' => round($row['weight'] / $total * 100, 2) . '%',
            ];
        }

        return $out;
    }

    /** Expected coins per box, so the economy is a number we can look at. */
    public static function expectedValue(): float
    {
        $total = self::totalWeight();
        $ev = 0.0;

        foreach (self::table() as $row) {
            $ev += $row['coins'] * ($row['weight'] / $total);
        }

        return round($ev, 2);
    }

    public static function contents(string $nonce, int $userId, int $drawId): int
    {
        $pick = Draws::deriveBounded($nonce . '|box|' . $userId . '|' . $drawId, self::totalWeight());

        foreach (self::table() as $row) {
            if ($pick < $row['weight']) {
                return (int) $row['coins'];
            }
            $pick -= $row['weight'];
        }

        // Unreachable while the weights are positive and deriveBounded stays in range.
        throw new RuntimeException('box draw fell off the reward table');
    }

    /**
     * Settled draws this player entered and has not opened a box for.
     *
     * @return list<array<string, mixed>>
     */
    public static function waiting(int $userId, int $limit = 20): array
    {
        $st = Db::conn()->prepare(
            'SELECT d.id, d.day, d.tier, d.draw_at, d.nonce, d.result,
                    (SELECT COUNT(*) FROM tickets t WHERE t.draw_id = d.id AND t.user_id = :u1) AS tickets
             FROM draws d
             WHERE d.result IS NOT NULL
               AND EXISTS (SELECT 1 FROM tickets t2 WHERE t2.draw_id = d.id AND t2.user_id = :u2)
               AND NOT EXISTS (SELECT 1 FROM box_opens b WHERE b.draw_id = d.id AND b.user_id = :u3)
             ORDER BY d.draw_at DESC
             LIMIT :limit'
        );
        $st->bindValue(':u1', $userId, PDO::PARAM_INT);
        $st->bindValue(':u2', $userId, PDO::PARAM_INT);
        $st->bindValue(':u3', $userId, PDO::PARAM_INT);
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll();
    }

    public static function waitingCount(int $userId): int
    {
        return count(self::waiting($userId, 100));
    }

    /** @return array{draw_id: int, coins: int, tier: string, balance: int} */
    public static function open(int $userId, int $drawId): array
    {
        $db = Db::conn();

        $st = $db->prepare('SELECT * FROM draws WHERE id = ?');
        $st->execute([$drawId]);
        $draw = $st->fetch();

        if ($draw === false || $draw['result'] === null) {
            throw new RuntimeException('That box is not open yet — the draw has to settle first.');
        }
        if (Tickets::countFor($userId, $drawId) === 0) {
            throw new RuntimeException('Boxes go to players. You had no ticket in that draw.');
        }

        $coins = self::contents((string) $draw['nonce'], $userId, $drawId);
        $at = Clock::now()->format(DateTimeInterface::ATOM);

        $db->beginTransaction();
        try {
            $ins = $db->prepare(
                'INSERT INTO box_opens (user_id, draw_id, coins, opened_at) VALUES (?, ?, ?, ?)'
            );
            $ins->execute([$userId, $drawId, $coins, $at]);

            Coins::record($userId, $coins, 'surprise_box', 'draws', $drawId);

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();

            // UNIQUE(user_id, draw_id): the honest reading is a double tap.
            if ($e->getCode() === '23000' || str_contains(strtolower($e->getMessage()), 'unique')) {
                throw new RuntimeException('You already opened that box.');
            }

            throw $e;
        }

        return [
            'draw_id' => $drawId,
            'coins' => $coins,
            'tier' => (string) $draw['tier'],
            'balance' => Coins::balance($userId),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function history(int $userId, int $limit = 20): array
    {
        $st = Db::conn()->prepare(
            'SELECT b.coins, b.opened_at, d.day, d.tier, d.result
             FROM box_opens b JOIN draws d ON d.id = b.draw_id
             WHERE b.user_id = ? ORDER BY b.id DESC LIMIT ?'
        );
        $st->bindValue(1, $userId, PDO::PARAM_INT);
        $st->bindValue(2, $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll();
    }
}

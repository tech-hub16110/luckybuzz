<?php

declare(strict_types=1);

/**
 * Draw schedule, provably-fair result derivation, grading and settlement.
 *
 * Fairness model: a draw row is created with a random 128-bit nonce that is
 * kept hidden, and only its SHA-256 commitment is published. The result is a
 * pure function of (nonce, day, tier), so once the nonce is revealed alongside
 * the result anyone can recompute both the commitment and the number. Results
 * cannot be chosen after tickets are in, and cannot be predicted before them.
 */
final class Draws
{
    /** Needs 64-bit integers; every target platform (arm64 Linux, InfinityFree) qualifies. */
    private const UINT32_SPACE = 4294967296;

    public static function commitFor(string $nonce, string $day, string $tier): string
    {
        return hash('sha256', $nonce . '|' . $day . '|' . $tier);
    }

    /**
     * A uniform integer in [0, $bound) taken from a hash.
     *
     * Rejection sampling, because 2^32 is not a multiple of most bounds: a plain
     * modulo would make the lowest values fractionally more likely. Discarding the
     * ragged tail keeps every outcome exactly equiprobable, which is what lets the
     * same helper serve both the drawn number and the surprise box.
     */
    public static function deriveBounded(string $material, int $bound): int
    {
        if ($bound < 1) {
            throw new InvalidArgumentException('bound must be at least 1');
        }

        $ceiling = intdiv(self::UINT32_SPACE, $bound) * $bound;

        for ($block = 0; ; $block++) {
            $value = (int) hexdec(substr(hash('sha256', $material . '|' . $block), 0, 8));
            if ($value < $ceiling) {
                return $value % $bound;
            }
        }
    }

    public static function deriveResult(string $nonce, string $day, string $tier): string
    {
        return str_pad(
            (string) self::deriveBounded($nonce . '|' . $day . '|' . $tier, 10000),
            4,
            '0',
            STR_PAD_LEFT,
        );
    }

    public static function verify(
        string $nonce,
        string $day,
        string $tier,
        string $commitHash,
        string $result,
    ): bool {
        return self::commitFor($nonce, $day, $tier) === $commitHash
            && self::deriveResult($nonce, $day, $tier) === $result;
    }

    /**
     * Highest tier only — a ticket is paid at most once per draw.
     *
     * @return string|null one of the Config prizes keys, or null for a dud
     */
    public static function grade(string $ticket, string $result): ?string
    {
        if ($ticket === $result) {
            return 'straight';
        }

        $t = str_split($ticket);
        $r = str_split($result);
        sort($t);
        sort($r);
        if ($t === $r) {
            return 'box';
        }

        for ($n = 3; $n >= 1; $n--) {
            if (substr($ticket, -$n) === substr($result, -$n)) {
                return 'back' . $n;
            }
        }

        foreach (str_split($ticket) as $digit) {
            if (str_contains($result, $digit)) {
                return 'anydigit';
            }
        }

        return null;
    }

    public static function drawAt(string $day, string $tier): DateTimeImmutable
    {
        return new DateTimeImmutable($day . ' ' . Config::tier($tier)['time'], Clock::zone());
    }

    /** Settle everything that came due, then make sure the board stays stocked. */
    public static function tick(): void
    {
        self::settleDue();
        self::scheduleUpcoming();
    }

    public static function settleDue(): int
    {
        $db = Db::conn();
        $now = Clock::now()->format(DateTimeInterface::ATOM);

        $due = $db->prepare(
            'SELECT * FROM draws WHERE result IS NULL AND draw_at <= ? ORDER BY draw_at'
        );
        $due->execute([$now]);

        $settled = 0;
        foreach ($due->fetchAll() as $draw) {
            self::settle($draw);
            $settled++;
        }

        return $settled;
    }

    /** @param array<string, mixed> $draw */
    private static function settle(array $draw): void
    {
        $db = Db::conn();
        $drawId = (int) $draw['id'];
        $nonce = (string) $draw['nonce'];
        $result = self::deriveResult($nonce, (string) $draw['day'], (string) $draw['tier']);

        $sold = self::ticketCount($drawId);
        $jackpot = (int) $draw['rollover_in'] + Config::int('jackpot_take') * $sold;
        $claimed = false;
        $at = Clock::now()->format(DateTimeInterface::ATOM);

        $db->beginTransaction();

        try {
            $db->prepare(
                'UPDATE draws SET result = ?, settled_at = ? WHERE id = ?'
            )->execute([$result, $at, $drawId]);

            $tickets = $db->prepare('SELECT * FROM tickets WHERE draw_id = ? ORDER BY id');
            $tickets->execute([$drawId]);

            $win = $db->prepare(
                'INSERT INTO wins (draw_id, ticket_id, user_id, match_kind, amount, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            foreach ($tickets->fetchAll() as $ticket) {
                $kind = self::grade((string) $ticket['number'], $result);
                if ($kind === null) {
                    continue;
                }

                $amount = $kind === 'straight' ? $jackpot : Config::prize($kind);
                if ($kind === 'straight') {
                    $claimed = true;
                }

                $win->execute([
                    $drawId,
                    (int) $ticket['id'],
                    (int) $ticket['user_id'],
                    $kind,
                    $amount,
                    $at,
                ]);
                Coins::record(
                    (int) $ticket['user_id'],
                    $amount,
                    $kind === 'straight' ? 'jackpot' : 'win',
                    'draws',
                    $drawId,
                );
            }

            $carry = $claimed ? Config::int('jackpot_seed') : $jackpot;
            $db->prepare('UPDATE draws SET carry_out = ? WHERE id = ?')->execute([$carry, $drawId]);

            // A later row may already be open for sales; its carry is not stored
            // as a balance, so rewriting rollover_in here is safe.
            $next = $db->prepare(
                'UPDATE draws SET rollover_in = ?
                 WHERE tier = ? AND day > ? AND result IS NULL'
            );
            $next->execute([$carry, $draw['tier'], $draw['day']]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function scheduleUpcoming(): void
    {
        $db = Db::conn();

        foreach (Config::tierNames() as $tier) {
            foreach ([0, 1] as $offsetDays) {
                $day = Clock::now()->modify("+{$offsetDays} days")->format('Y-m-d');
                $at = self::drawAt($day, $tier);
                if ($at <= Clock::now()) {
                    continue;
                }

                $exists = $db->prepare('SELECT id FROM draws WHERE day = ? AND tier = ?');
                $exists->execute([$day, $tier]);
                if ($exists->fetchColumn() !== false) {
                    continue;
                }

                $nonce = bin2hex(random_bytes(16));
                $db->prepare(
                    'INSERT INTO draws (day, tier, draw_at, commit_hash, nonce, rollover_in)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $day,
                    $tier,
                    $at->format(DateTimeInterface::ATOM),
                    self::commitFor($nonce, $day, $tier),
                    $nonce,
                    self::openingJackpot($tier),
                ]);
            }
        }
    }

    private static function openingJackpot(string $tier): int
    {
        $st = Db::conn()->prepare(
            'SELECT carry_out FROM draws
             WHERE tier = ? AND carry_out IS NOT NULL
             ORDER BY day DESC LIMIT 1'
        );
        $st->execute([$tier]);
        $carry = $st->fetchColumn();

        return $carry === false ? Config::int('jackpot_seed') : (int) $carry;
    }

    public static function ticketCount(int $drawId): int
    {
        $st = Db::conn()->prepare('SELECT COUNT(*) FROM tickets WHERE draw_id = ?');
        $st->execute([$drawId]);

        return (int) $st->fetchColumn();
    }

    /** @param array<string, mixed> $draw */
    public static function jackpotOf(array $draw): int
    {
        return (int) $draw['rollover_in']
            + Config::int('jackpot_take') * self::ticketCount((int) $draw['id']);
    }

    /**
     * The next open draw for every tier — what the home screen renders.
     *
     * Uses a single query with LEFT JOINs to avoid N+1 queries.
     * jackpot is pre-computed as rollover_in + jackpot_take * ticket_count
     * in the SQL so we don't call jackpotOf() per row.
     *
     * @return list<array<string, mixed>>
     */
    public static function board(): array
    {
        $db = Db::conn();
        $now = Clock::now()->format(DateTimeInterface::ATOM);
        $take = Config::int('jackpot_take');

        $st = $db->prepare(
            'SELECT d.*,
                    (d.rollover_in + ' . (int) $take . ' * (SELECT COUNT(*) FROM tickets t WHERE t.draw_id = d.id)) AS jackpot
             FROM draws d
             WHERE d.tier = ? AND d.result IS NULL AND d.draw_at > ?
             ORDER BY d.day LIMIT 1'
        );
        $board = [];
        foreach (Config::tierNames() as $tier) {
            $st->execute([$tier, $now]);
            $draw = $st->fetch();
            if ($draw !== false) {
                $draw['jackpot'] = (int) $draw['jackpot'];
                $board[] = $draw;
            }
        }

        return $board;
    }

    /** @return list<array<string, mixed>> */
    public static function recent(int $limit = 12): array
    {
        $st = Db::conn()->prepare(
            'SELECT d.*,
                    d.rollover_in + :take * (
                        SELECT COUNT(*) FROM tickets t WHERE t.draw_id = d.id
                    ) AS jackpot,
                    EXISTS (
                        SELECT 1 FROM wins w
                        WHERE w.draw_id = d.id AND w.match_kind = \'straight\'
                    ) AS paid
             FROM draws d
             WHERE d.result IS NOT NULL
             ORDER BY d.draw_at DESC LIMIT :limit'
        );
        $st->bindValue(':take', Config::int('jackpot_take'), PDO::PARAM_INT);
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll();
    }

    /**
     * Digit frequency across settled draws, for the hot/cold panel.
     *
     * @return array{hot: list<array{digit: string, hits: int}>, cold: list<array{digit: string, hits: int}>, samples: int, last: list<string>}
     */
    public static function digitStats(): array
    {
        $st = Db::conn()->prepare(
            'SELECT result FROM draws WHERE result IS NOT NULL
             ORDER BY draw_at DESC LIMIT :window'
        );
        $st->bindValue(':window', Config::int('hot_cold_window'), PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);

        $counts = array_fill_keys(range('0', '9'), 0);
        foreach ($rows as $result) {
            foreach (str_split((string) $result) as $digit) {
                $counts[$digit]++;
            }
        }

        arsort($counts);
        $hot = [];
        foreach (array_slice($counts, 0, 3, true) as $digit => $hits) {
            $hot[] = ['digit' => (string) $digit, 'hits' => $hits];
        }

        asort($counts);
        $cold = [];
        foreach (array_slice($counts, 0, 3, true) as $digit => $hits) {
            $cold[] = ['digit' => (string) $digit, 'hits' => $hits];
        }

        return [
            'hot' => $hot,
            'cold' => $cold,
            'samples' => count($rows),
            'last' => array_map('strval', array_slice($rows, 0, 10)),
        ];
    }
}

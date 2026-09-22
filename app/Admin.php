<?php

declare(strict_types=1);

final class Admin
{
    public static function isAdmin(): bool
    {
        return !empty($_SESSION['admin_auth']);
    }

    public static function isLoggedIn(): bool
    {
        return self::isAdmin();
    }

    public static function login(string $username, string $password): bool
    {
        $adminConfig = Config::get('admin', []);
        $cfgUser = (string) ($adminConfig['username'] ?? 'admin');
        $cfgPass = (string) ($adminConfig['password'] ?? 'admin123');

        if (hash_equals($cfgUser, $username) && hash_equals($cfgPass, $password)) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_regenerate_id(true);
            }
            $_SESSION['admin_auth'] = true;
            $_SESSION['admin_user'] = $username;
            return true;
        }

        return false;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin_auth'], $_SESSION['admin_user']);
    }

    /** @return array<string, mixed> */
    public static function stats(): array
    {
        $db = Db::conn();

        $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $ticketCount = (int) $db->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
        $totalSpent = (int) $db->query("SELECT COALESCE(SUM(-delta), 0) FROM ledger WHERE reason = 'ticket'")->fetchColumn();
        $totalPaidWins = (int) $db->query("SELECT COALESCE(SUM(amount), 0) FROM wins")->fetchColumn();
        $totalCirculation = (int) $db->query("SELECT COALESCE(SUM(delta), 0) FROM ledger")->fetchColumn();
        $openDraws = (int) $db->query("SELECT COUNT(*) FROM draws WHERE result IS NULL")->fetchColumn();
        $settledDraws = (int) $db->query("SELECT COUNT(*) FROM draws WHERE result IS NOT NULL")->fetchColumn();

        return [
            'users' => $userCount,
            'tickets' => $ticketCount,
            'total_spent' => $totalSpent,
            'total_won' => $totalPaidWins,
            'circulation' => $totalCirculation,
            'open_draws' => $openDraws,
            'settled_draws' => $settledDraws,
            'last_tick' => (int) Db::getMeta('last_tick'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function draws(int $limit = 50): array
    {
        $db = Db::conn();
        $take = Config::int('jackpot_take');
        $st = $db->prepare(
            'SELECT d.*,
                    (SELECT COUNT(*) FROM tickets t WHERE t.draw_id = d.id) AS ticket_count,
                    (SELECT COALESCE(SUM(amount), 0) FROM wins w WHERE w.draw_id = d.id) AS total_won,
                    (d.rollover_in + :take * (SELECT COUNT(*) FROM tickets t WHERE t.draw_id = d.id)) AS jackpot
             FROM draws d
             ORDER BY d.draw_at DESC
             LIMIT :limit'
        );
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':take', $take, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function users(int $limit = 50, string $search = ''): array
    {
        $db = Db::conn();
        $search = trim($search);

        if ($search !== '') {
            $st = $db->prepare(
                'SELECT u.*,
                        COALESCE(l.balance, 0) AS balance,
                        COALESCE(t.tickets_count, 0) AS tickets_count,
                        COALESCE(w.total_won, 0) AS total_won
                 FROM users u
                 LEFT JOIN (
                     SELECT user_id, SUM(delta) AS balance FROM ledger GROUP BY user_id
                 ) l ON l.user_id = u.id
                 LEFT JOIN (
                     SELECT user_id, COUNT(*) AS tickets_count FROM tickets GROUP BY user_id
                 ) t ON t.user_id = u.id
                 LEFT JOIN (
                     SELECT t.user_id, COALESCE(SUM(w.amount), 0) AS total_won
                     FROM tickets t JOIN wins w ON w.ticket_id = t.id GROUP BY t.user_id
                 ) w ON w.user_id = u.id
                 WHERE u.username LIKE :q
                 ORDER BY u.id DESC LIMIT :limit'
            );
            $st->bindValue(':q', '%' . $search . '%');
            $st->bindValue(':limit', $limit, PDO::PARAM_INT);
            $st->execute();
        } else {
            $st = $db->prepare(
                'SELECT u.*,
                        COALESCE(l.balance, 0) AS balance,
                        COALESCE(t.tickets_count, 0) AS tickets_count,
                        COALESCE(w.total_won, 0) AS total_won
                 FROM users u
                 LEFT JOIN (
                     SELECT user_id, SUM(delta) AS balance FROM ledger GROUP BY user_id
                 ) l ON l.user_id = u.id
                 LEFT JOIN (
                     SELECT user_id, COUNT(*) AS tickets_count FROM tickets GROUP BY user_id
                 ) t ON t.user_id = u.id
                 LEFT JOIN (
                     SELECT t.user_id, COALESCE(SUM(w.amount), 0) AS total_won
                     FROM tickets t JOIN wins w ON w.ticket_id = t.id GROUP BY t.user_id
                 ) w ON w.user_id = u.id
                 ORDER BY u.id DESC LIMIT :limit'
            );
            $st->bindValue(':limit', $limit, PDO::PARAM_INT);
            $st->execute();
        }

        return $st->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function tickets(int $limit = 50, ?int $drawId = null, ?int $userId = null): array
    {
        $db = Db::conn();
        $sql = 'SELECT t.id, t.number, t.cost, t.created_at,
                       u.id AS user_id, u.username,
                       d.id AS draw_id, d.day, d.tier, d.draw_at, d.result,
                       w.match_kind, w.amount
                FROM tickets t
                JOIN users u ON u.id = t.user_id
                JOIN draws d ON d.id = t.draw_id
                LEFT JOIN wins w ON w.ticket_id = t.id';
        
        $params = [];
        $wheres = [];
        if ($drawId !== null) {
            $wheres[] = 't.draw_id = ?';
            $params[] = $drawId;
        }
        if ($userId !== null) {
            $wheres[] = 't.user_id = ?';
            $params[] = $userId;
        }

        if ($wheres !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $wheres);
        }

        $sql .= ' ORDER BY t.id DESC LIMIT ' . (int) $limit;
        $st = $db->prepare($sql);
        $st->execute($params);

        return $st->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function wins(int $limit = 50): array
    {
        $db = Db::conn();
        $st = $db->prepare(
            'SELECT w.*, u.username, d.day, d.tier, d.result, t.number
             FROM wins w
             JOIN users u ON u.id = w.user_id
             JOIN draws d ON d.id = w.draw_id
             JOIN tickets t ON t.id = w.ticket_id
             ORDER BY w.id DESC LIMIT :limit'
        );
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll();
    }

    /**
     * Settle or manually draw a result for a draw.
     * @return array{result: string, winners: int, payout: int}
     */
    public static function forceSettle(int $drawId, ?string $customResult = null): array
    {
        $db = Db::conn();
        $st = $db->prepare('SELECT * FROM draws WHERE id = ?');
        $st->execute([$drawId]);
        $draw = $st->fetch();

        if ($draw === false) {
            throw new RuntimeException('Draw not found.');
        }
        if ($draw['result'] !== null) {
            throw new RuntimeException('Draw is already settled.');
        }

        $nonce = (string) $draw['nonce'];
        $customResult = $customResult !== null ? trim($customResult) : '';

        if ($customResult !== '') {
            if (!preg_match('/^\d{1,5}$/', $customResult)) {
                throw new RuntimeException('Draw result must be a valid 4 or 5 digit number.');
            }
            $result = strlen($customResult) <= 4 ? str_pad($customResult, 4, '0', STR_PAD_LEFT) : $customResult;
        } else {
            $result = Draws::deriveResult($nonce, (string) $draw['day'], (string) $draw['tier']);
        }

        $sold = Draws::ticketCount($drawId);
        $jackpot = (int) $draw['rollover_in'] + Config::int('jackpot_take') * $sold;
        $claimed = false;
        $at = Clock::now()->format(DateTimeInterface::ATOM);

        $db->beginTransaction();
        $winnersCount = 0;
        $totalPaid = 0;

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
                $kind = Draws::grade((string) $ticket['number'], $result);
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

                $winnersCount++;
                $totalPaid += $amount;
            }

            $carry = $claimed ? Config::int('jackpot_seed') : $jackpot;
            $db->prepare('UPDATE draws SET carry_out = ? WHERE id = ?')->execute([$carry, $drawId]);

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

        // Restock upcoming draws
        Draws::scheduleUpcoming();

        return [
            'result' => $result,
            'winners' => $winnersCount,
            'payout' => $totalPaid,
        ];
    }

    public static function adjustCoins(int $userId, int $delta, string $reason = 'refund'): void
    {
        if ($delta === 0) {
            return;
        }

        if ($delta > 0) {
            Coins::grant($userId, $delta, $reason);
        } else {
            Coins::charge($userId, abs($delta), $reason);
        }
    }

    public static function grantBulkCoins(int $amount, string $reason = 'refund'): int
    {
        if ($amount <= 0) {
            throw new RuntimeException('Coin amount must be greater than 0.');
        }

        $db = Db::conn();
        $users = $db->query('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;

        $db->beginTransaction();
        try {
            foreach ($users as $userId) {
                Coins::grant((int) $userId, $amount, $reason);
                $count++;
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return $count;
    }

    public static function createDraw(string $day, string $tier, string $drawAtTime, ?int $rolloverIn = null): int
    {
        $day = trim($day);
        $tier = trim($tier);
        if (!in_array($tier, Config::tierNames(), true)) {
            throw new RuntimeException('Invalid tier selected.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            throw new RuntimeException('Invalid day format (YYYY-MM-DD required).');
        }

        $drawAtTime = trim($drawAtTime);
        if ($drawAtTime === '') {
            $tierCfg = Config::tier($tier);
            $drawAtTime = $tierCfg['time'] ?? '13:00';
        }

        $at = new DateTimeImmutable($day . ' ' . $drawAtTime, Clock::zone());
        $db = Db::conn();

        $exists = $db->prepare('SELECT id FROM draws WHERE day = ? AND tier = ?');
        $exists->execute([$day, $tier]);
        if ($exists->fetchColumn() !== false) {
            throw new RuntimeException("A draw for {$day} ({$tier}) already exists.");
        }

        $nonce = bin2hex(random_bytes(16));
        $seed = $rolloverIn !== null && $rolloverIn >= 0 ? $rolloverIn : Config::int('jackpot_seed');

        $st = $db->prepare(
            'INSERT INTO draws (day, tier, draw_at, commit_hash, nonce, rollover_in)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $day,
            $tier,
            $at->format(DateTimeInterface::ATOM),
            Draws::commitFor($nonce, $day, $tier),
            $nonce,
            $seed,
        ]);

        return (int) $db->lastInsertId();
    }

    /** @return array<string, mixed> */
    public static function getEconomics(): array
    {
        return [
            'ticket_cost' => Config::int('ticket_cost'),
            'tickets_per_draw' => Config::int('tickets_per_draw'),
            'jackpot_seed' => Config::int('jackpot_seed'),
            'jackpot_take' => Config::int('jackpot_take'),
            'signup_grant' => Config::int('signup_grant'),
            'prizes' => [
                'box' => Config::prize('box'),
                'back3' => Config::prize('back3'),
                'back2' => Config::prize('back2'),
                'back1' => Config::prize('back1'),
                'anydigit' => Config::prize('anydigit'),
            ],
        ];
    }

    /** @param array<string, mixed> $params */
    public static function saveEconomics(array $params): void
    {
        if (isset($params['ticket_cost'])) {
            Db::putMeta('cfg_ticket_cost', (string) max(1, (int) $params['ticket_cost']));
        }
        if (isset($params['tickets_per_draw'])) {
            Db::putMeta('cfg_tickets_per_draw', (string) max(1, (int) $params['tickets_per_draw']));
        }
        if (isset($params['jackpot_seed'])) {
            Db::putMeta('cfg_jackpot_seed', (string) max(0, (int) $params['jackpot_seed']));
        }
        if (isset($params['jackpot_take'])) {
            Db::putMeta('cfg_jackpot_take', (string) max(0, (int) $params['jackpot_take']));
        }
        if (isset($params['signup_grant'])) {
            Db::putMeta('cfg_signup_grant', (string) max(0, (int) $params['signup_grant']));
        }

        // Prizes
        if (isset($params['prize_box'])) {
            Db::putMeta('cfg_prize_box', (string) max(0, (int) $params['prize_box']));
        }
        if (isset($params['prize_back3'])) {
            Db::putMeta('cfg_prize_back3', (string) max(0, (int) $params['prize_back3']));
        }
        if (isset($params['prize_back2'])) {
            Db::putMeta('cfg_prize_back2', (string) max(0, (int) $params['prize_back2']));
        }
        if (isset($params['prize_back1'])) {
            Db::putMeta('cfg_prize_back1', (string) max(0, (int) $params['prize_back1']));
        }
        if (isset($params['prize_anydigit'])) {
            Db::putMeta('cfg_prize_anydigit', (string) max(0, (int) $params['prize_anydigit']));
        }
    }

    /** @return list<array<string, mixed>> */
    public static function ledger(int $limit = 50, ?int $userId = null): array
    {
        $db = Db::conn();
        $sql = 'SELECT l.*, u.username
                FROM ledger l
                JOIN users u ON u.id = l.user_id';
        $params = [];

        if ($userId !== null) {
            $sql .= ' WHERE l.user_id = ?';
            $params[] = $userId;
        }

        $sql .= ' ORDER BY l.id DESC LIMIT ' . (int) $limit;
        $st = $db->prepare($sql);
        $st->execute($params);

        return $st->fetchAll();
    }

    public static function resetStreak(int $userId): void
    {
        $db = Db::conn();
        $db->prepare('UPDATE users SET streak = 0, last_bonus_day = NULL WHERE id = ?')->execute([$userId]);
    }

    public static function deleteUser(int $userId): void
    {
        $db = Db::conn();
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }

    /**
     * Create a new player/user account from Admin console.
     *
     * @return array<string, mixed> the new user row
     */
    public static function createUser(string $username, string $password, ?int $initialCoins = null): array
    {
        $username = strtolower(trim($username));
        $uErr = Auth::validateUsername($username);
        if ($uErr !== null) {
            throw new RuntimeException($uErr);
        }

        $pErr = Auth::validatePassword($password);
        if ($pErr !== null) {
            throw new RuntimeException($pErr);
        }

        $db = Db::conn();
        $st = $db->prepare('SELECT id FROM users WHERE username = ?');
        $st->execute([$username]);
        if ($st->fetchColumn() !== false) {
            throw new RuntimeException("Username '{$username}' is already taken.");
        }

        $user = Auth::register($username, $password);
        $userId = (int) $user['id'];

        if ($initialCoins !== null && $initialCoins >= 0) {
            $currentBal = Coins::balance($userId);
            $diff = $initialCoins - $currentBal;
            if ($diff > 0) {
                Coins::grant($userId, $diff, 'refund');
            } elseif ($diff < 0) {
                Coins::charge($userId, abs($diff), 'refund');
            }
        }

        return $user;
    }
}

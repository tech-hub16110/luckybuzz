<?php

declare(strict_types=1);

/**
 * Orchestrator service for importing Lottery Sambad results.
 *
 * Implements two-stage extraction:
 * 1. Primary: Official Sambad prizes API
 * 2. Secondary Fallback: Result file image download + Vision Model (nex-n2.5-pro) OCR
 * 3. Manual override tracking
 *
 * Provides database caching, duplicate protection, audit logging,
 * and lazy background execution without blocking site visitors.
 */
final class LotteryImporter
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETRYING = 'retrying';

    public const SOURCE_API = 'api';
    public const SOURCE_VISION = 'vision';
    public const SOURCE_MANUAL = 'manual';

    /**
     * Map of daily draw times and their publication target times (in Asia/Kolkata).
     * 1 PM => 13:00
     * 6 PM => 18:00
     * 8 PM => 20:00
     */
    public const DRAW_SCHEDULE = [
        '1 PM' => ['hour' => 13, 'minute' => 0, 'tier' => 'morning'],
        '6 PM' => ['hour' => 18, 'minute' => 0, 'tier' => 'day'],
        '8 PM' => ['hour' => 20, 'minute' => 0, 'tier' => 'evening'],
    ];

    /**
     * Main background / lazy tick handler.
     * Throttled and never throws or crashes the host application.
     *
     * @return int Number of draws attempted/imported
     */
    public static function tick(int $timeout = 10): int
    {
        $processed = 0;
        try {
            $cfg = Config::get('lottery_import') ?? [];
            if (($cfg['enabled'] ?? true) !== true) {
                return 0;
            }

            $tz = new DateTimeZone($cfg['timezone'] ?? 'Asia/Kolkata');
            $now = Clock::now()->setTimezone($tz);
            $today = $now->format('Y-m-d');
            $retryInterval = (int) ($cfg['retry_interval'] ?? 300);
            $maxAttempts = (int) ($cfg['max_attempts'] ?? 4);

            $lastCheck = (int) Db::getMeta('last_lottery_import_tick', 0);
            if ($lastCheck > 0 && ($now->getTimestamp() - $lastCheck) < 30) {
                return 0;
            }
            Db::putMeta('last_lottery_import_tick', (string) $now->getTimestamp());

            // Check each scheduled draw time
            foreach (self::DRAW_SCHEDULE as $drawTime => $sched) {
                $targetTime = (clone $now)->setTime($sched['hour'], $sched['minute'], 0);

                // Only attempt if target draw time has passed
                if ($now < $targetTime) {
                    continue;
                }

                $existing = self::getImport($today, $drawTime);
                if ($existing !== null && $existing['status'] === self::STATUS_SUCCESS) {
                    continue; // Already successfully imported
                }

                $attemptCount = $existing ? (int) $existing['attempt_count'] : 0;
                if ($attemptCount >= $maxAttempts) {
                    continue; // Reached max attempts for today
                }

                if ($existing && $existing['last_attempt_at'] !== null) {
                    $lastAttemptTs = strtotime($existing['last_attempt_at']);
                    if ($lastAttemptTs !== false && ($now->getTimestamp() - $lastAttemptTs) < $retryInterval) {
                        continue; // Still within cooldown window
                    }
                }

                // Run import attempt with short timeout for lazy requests
                $res = self::importDraw($today, $drawTime, false, $timeout);
                if ($res['success']) {
                    $processed++;
                }
            }
        } catch (Throwable $e) {
            // Never crash the main Lucky Buzz application on external import error
            error_log('LotteryImporter::tick error: ' . $e->getMessage());
        }

        return $processed;
    }

    /**
     * Import a specific draw with primary API and optional Vision fallback.
     *
     * @param string $date "YYYY-MM-DD"
     * @param string $drawTime "1 PM", "6 PM", or "8 PM"
     * @param bool $force Force re-fetch even if already imported
     * @param int $timeout Request timeout in seconds
     * @return array{success: bool, source: string, count: int, error: ?string}
     */
    public static function importDraw(string $date, string $drawTime, bool $force = false, int $timeout = 20): array
    {
        $normDate = LotteryResultNormalizer::normalizeDate($date);
        $normTime = LotteryResultNormalizer::normalizeDrawTime($drawTime);
        $nowStr = Clock::now()->format(DateTimeInterface::ATOM);

        $import = self::getImport($normDate, $normTime);
        if ($import !== null && $import['status'] === self::STATUS_SUCCESS && !$force) {
            return [
                'success' => true,
                'source' => (string) $import['source'],
                'count' => count(self::getResults((int) $import['id'])),
                'error' => null,
            ];
        }

        $importId = self::ensureImportRecord($normDate, $normTime);
        $attempt = ($import ? (int) $import['attempt_count'] : 0) + 1;

        // Stage 1: Try Primary Sambad Prizes API
        $client = new LotterySambadClient(null, null, $timeout);
        $apiRes = $client->fetchPrizes($normDate, $normTime);

        if ($apiRes['success'] && is_array($apiRes['data'])) {
            $normalizedPrizes = LotteryResultNormalizer::normalizePrizes($apiRes['data']);
            $val = LotteryResultNormalizer::validate($normalizedPrizes);

            if ($val['valid']) {
                self::saveResults($importId, $normalizedPrizes, self::SOURCE_API);
                self::updateImportStatus($importId, self::STATUS_SUCCESS, self::SOURCE_API, $attempt, null);
                self::logAttempt($normDate, $normTime, $attempt, self::SOURCE_API, $apiRes['http_status'], 1, $val['total_count'], 'valid', 0, null, $apiRes['duration_ms'], null, self::STATUS_SUCCESS);

                return [
                    'success' => true,
                    'source' => self::SOURCE_API,
                    'count' => $val['total_count'],
                    'error' => null,
                ];
            }
        }

        // Stage 2: Fallback to Vision Extraction if API failed or returned invalid data
        $vision = new LotteryVisionExtractor(null, null, null, max(30, $timeout));
        if ($vision->isAvailable()) {
            $fileRes = $client->fetchResultFile($normDate, $normTime);
            if ($fileRes['success'] && is_string($fileRes['bytes']) && strlen($fileRes['bytes']) > 100) {
                $visRes = $vision->extractFromImage(
                    $fileRes['bytes'],
                    $fileRes['content_type'] ?? 'image/jpeg',
                    $normDate,
                    $normTime
                );

                if ($visRes['success'] && !empty($visRes['prizes'])) {
                    self::saveResults($importId, $visRes['prizes'], self::SOURCE_VISION);
                    self::updateImportStatus($importId, self::STATUS_SUCCESS, self::SOURCE_VISION, $attempt, null);
                    self::logAttempt(
                        $normDate,
                        $normTime,
                        $attempt,
                        self::SOURCE_VISION,
                        $fileRes['http_status'],
                        1,
                        $visRes['validation']['total_count'],
                        'valid',
                        1,
                        $visRes['model'],
                        $visRes['duration_ms'],
                        null,
                        self::STATUS_SUCCESS
                    );

                    return [
                        'success' => true,
                        'source' => self::SOURCE_VISION,
                        'count' => $visRes['validation']['total_count'],
                        'error' => null,
                    ];
                }
            }
        }

        // If both failed or unavailable
        $existingResults = self::getResults((int) $importId);
        if ($existingResults !== []) {
            // Retain the existing valid data in database
            return [
                'success' => true,
                'source' => (string) ($import['source'] ?? self::SOURCE_API),
                'count' => count($existingResults),
                'error' => null,
            ];
        }

        $errMsg = $apiRes['error'] ?? 'Result data not yet published or unavailable';
        self::updateImportStatus($importId, self::STATUS_RETRYING, self::SOURCE_API, $attempt, $errMsg);
        self::logAttempt(
            $normDate,
            $normTime,
            $attempt,
            self::SOURCE_API,
            $apiRes['http_status'] ?: 0,
            0,
            0,
            'failed',
            0,
            null,
            $apiRes['duration_ms'] ?? 0,
            $errMsg,
            self::STATUS_RETRYING
        );

        return [
            'success' => false,
            'source' => self::SOURCE_API,
            'count' => 0,
            'error' => $errMsg,
        ];
    }

    /**
     * Admin manual result update or correction.
     *
     * @param string $date
     * @param string $drawTime
     * @param array<string|int, mixed> $rawPrizes
     * @return array{success: bool, count: int, error: ?string}
     */
    public static function manualSave(string $date, string $drawTime, array $rawPrizes): array
    {
        $normDate = LotteryResultNormalizer::normalizeDate($date);
        $normTime = LotteryResultNormalizer::normalizeDrawTime($drawTime);

        $normalizedPrizes = LotteryResultNormalizer::normalizePrizes($rawPrizes);
        $val = LotteryResultNormalizer::validate($normalizedPrizes);

        if (!$val['valid']) {
            return ['success' => false, 'count' => 0, 'error' => $val['reason']];
        }

        $importId = self::ensureImportRecord($normDate, $normTime);
        self::saveResults($importId, $normalizedPrizes, self::SOURCE_MANUAL);
        self::updateImportStatus($importId, self::STATUS_SUCCESS, self::SOURCE_MANUAL, 1, null);
        self::logAttempt($normDate, $normTime, 1, self::SOURCE_MANUAL, 200, 1, $val['total_count'], 'valid', 0, null, 0, null, self::STATUS_SUCCESS);

        return [
            'success' => true,
            'count' => $val['total_count'],
            'error' => null,
        ];
    }

    /**
     * Get or create import header record.
     */
    public static function ensureImportRecord(string $date, string $drawTime): int
    {
        $db = Db::conn();
        $st = $db->prepare('SELECT id FROM lottery_imports WHERE draw_date = ? AND draw_time = ?');
        $st->execute([$date, $drawTime]);
        $id = $st->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $now = Clock::now()->format(DateTimeInterface::ATOM);
        $ins = $db->prepare(
            'INSERT INTO lottery_imports (draw_date, draw_time, source, status, attempt_count, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, 0, ?, ?)'
        );
        $ins->execute([$date, $drawTime, self::SOURCE_API, self::STATUS_PENDING, $now, $now]);

        return (int) $db->lastInsertId();
    }

    /**
     * Retrieve import header and all prizes for a specific date and draw time.
     *
     * @return array{import: array<string, mixed>, prizes: array<string, list<array<string, mixed>>>}|null
     */
    public static function getDrawPrizes(string $date, string $drawTime): ?array
    {
        $imp = self::getImport($date, $drawTime);
        if ($imp === null) {
            return null;
        }

        return [
            'import' => $imp,
            'prizes' => self::getResults((int) $imp['id']),
        ];
    }

    /**
     * Public logging helper for audit tracking.
     */
    public static function log(
        string $date,
        string $drawTime,
        string $source,
        string $status,
        string $message,
        int $durationMs = 0
    ): void {
        self::logAttempt(
            LotteryResultNormalizer::normalizeDate($date) ?? $date,
            LotteryResultNormalizer::normalizeDrawTime($drawTime) ?? $drawTime,
            1,
            $source,
            200,
            $status === self::STATUS_SUCCESS ? 1 : 0,
            0,
            $status,
            $source === self::SOURCE_VISION ? 1 : 0,
            $source === self::SOURCE_VISION ? (string) (Config::get('lottery_import')['fallback_ai']['vision_model'] ?? 'nex-n2.5-pro') : null,
            $durationMs,
            $status === self::STATUS_SUCCESS ? null : $message,
            $status
        );
    }

    /**
     * Retrieve import header by date and time.
     *
     * @return array<string, mixed>|null
     */
    public static function getImport(string $date, string $drawTime): ?array
    {
        $normDate = LotteryResultNormalizer::normalizeDate($date);
        $normTime = LotteryResultNormalizer::normalizeDrawTime($drawTime);

        $st = Db::conn()->prepare('SELECT * FROM lottery_imports WHERE draw_date = ? AND draw_time = ?');
        $st->execute([$normDate, $normTime]);
        $row = $st->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Retrieve all results for an import ID, grouped by prize category.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function getResults(int $importId): array
    {
        $st = Db::conn()->prepare(
            'SELECT * FROM lottery_results WHERE import_id = ? ORDER BY prize_category ASC, sort_order ASC'
        );
        $st->execute([$importId]);
        $rows = $st->fetchAll();

        $grouped = [
            '1' => [],
            '2' => [],
            '3' => [],
            '4' => [],
            '5' => [],
        ];

        foreach ($rows as $row) {
            $cat = (string) $row['prize_category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $row;
        }

        return $grouped;
    }

    /**
     * Get draw results for a specific date (includes all scheduled time slots).
     * Automatically attempts on-demand live import if records are not yet saved.
     *
     * @return array{date: string, draws: array<string, array{import: array<string, mixed>|null, prizes: array<string, list<array<string, mixed>>>}>}
     */
    public static function getDaySummary(string $date, bool $autoFetch = true): array
    {
        $normDate = LotteryResultNormalizer::normalizeDate($date) ?? Clock::now()->format('Y-m-d');
        $tz = new DateTimeZone(Config::get('timezone') ?? 'Asia/Kolkata');
        $now = Clock::now()->setTimezone($tz);
        $today = $now->format('Y-m-d');
        $isPastDate = ($normDate < $today);
        $isToday = ($normDate === $today);

        $dayData = [
            'date' => $normDate,
            'draws' => [],
        ];

        foreach (self::DRAW_SCHEDULE as $drawTime => $sched) {
            $imp = self::getImport($normDate, $drawTime);

            // Determine if this draw time has occurred
            $isDue = false;
            if ($isPastDate) {
                $isDue = true;
            } elseif ($isToday) {
                $targetTime = (clone $now)->setTime($sched['hour'], $sched['minute'], 0);
                if ($now >= $targetTime) {
                    $isDue = true;
                }
            }

            // On-demand live fetch if not already successfully imported
            if ($autoFetch && $isDue && ($imp === null || $imp['status'] !== self::STATUS_SUCCESS)) {
                try {
                    self::importDraw($normDate, $drawTime, false, 12);
                    $imp = self::getImport($normDate, $drawTime);
                } catch (Throwable $e) {
                    error_log("On-demand import error for {$normDate} {$drawTime}: " . $e->getMessage());
                }
            }

            $prizes = ($imp !== null && $imp['status'] === self::STATUS_SUCCESS) 
                ? self::getResults((int) $imp['id']) 
                : [
                    '1' => [], '2' => [], '3' => [], '4' => [], '5' => [],
                ];

            $dayData['draws'][$drawTime] = [
                'import' => $imp,
                'prizes' => $prizes,
            ];
        }

        return $dayData;
    }

    /**
     * Get list of unique dates that have imported draws in the system.
     *
     * @return list<string>
     */
    public static function getAvailableDates(int $limit = 30): array
    {
        $db = Db::conn();
        $st = $db->prepare(
            'SELECT DISTINCT draw_date FROM lottery_imports WHERE status = ? ORDER BY draw_date DESC LIMIT ?'
        );
        $st->execute([self::STATUS_SUCCESS, $limit]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get recent daily Lottery Sambad summaries for user results view.
     *
     * @return list<array<string, mixed>>
     */
    public static function getRecentSummary(int $limitDays = 7): array
    {
        $db = Db::conn();
        $st = $db->prepare(
            'SELECT DISTINCT draw_date FROM lottery_imports WHERE status = ? ORDER BY draw_date DESC LIMIT ?'
        );
        $st->execute([self::STATUS_SUCCESS, $limitDays]);
        $dates = $st->fetchAll(PDO::FETCH_COLUMN);

        $out = [];
        foreach ($dates as $date) {
            $dayData = [
                'date' => $date,
                'draws' => [],
            ];

            foreach (array_keys(self::DRAW_SCHEDULE) as $drawTime) {
                $imp = self::getImport((string) $date, $drawTime);
                if ($imp !== null && $imp['status'] === self::STATUS_SUCCESS) {
                    $dayData['draws'][$drawTime] = [
                        'import' => $imp,
                        'prizes' => self::getResults((int) $imp['id']),
                    ];
                }
            }

            if (!empty($dayData['draws'])) {
                $out[] = $dayData;
            }
        }

        return $out;
    }

    /**
     * Save normalized prize numbers to lottery_results table.
     *
     * @param array<string, list<array<string, mixed>>> $prizes
     */
    private static function saveResults(int $importId, array $prizes, string $sourceType): void
    {
        $db = Db::conn();
        $now = Clock::now()->format(DateTimeInterface::ATOM);

        $inTx = $db->inTransaction();
        if (!$inTx) {
            $db->beginTransaction();
        }

        try {
            // Delete any existing items for this import id
            $del = $db->prepare('DELETE FROM lottery_results WHERE import_id = ?');
            $del->execute([$importId]);

            $ins = $db->prepare(
                'INSERT INTO lottery_results (import_id, prize_category, display_result, series, number, sort_order, source_type, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($prizes as $cat => $items) {
                foreach ($items as $item) {
                    $ins->execute([
                        $importId,
                        (string) $cat,
                        (string) ($item['display'] ?? $item['number']),
                        $item['series'] ?? null,
                        (string) $item['number'],
                        (int) ($item['sort_order'] ?? 1),
                        $sourceType,
                        $now,
                    ]);
                }
            }

            if (!$inTx) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if (!$inTx) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update import status header.
     */
    private static function updateImportStatus(
        int $importId,
        string $status,
        string $source,
        int $attempt,
        ?string $error
    ): void {
        $now = Clock::now()->format(DateTimeInterface::ATOM);
        $importedAt = ($status === self::STATUS_SUCCESS) ? $now : null;

        $st = Db::conn()->prepare(
            'UPDATE lottery_imports SET '
            . 'status = ?, source = ?, attempt_count = ?, last_attempt_at = ?, '
            . ($importedAt !== null ? 'imported_at = ?, ' : '')
            . 'error_message = ?, updated_at = ? '
            . 'WHERE id = ?'
        );

        $params = [$status, $source, $attempt, $now];
        if ($importedAt !== null) {
            $params[] = $importedAt;
        }
        $params[] = $error;
        $params[] = $now;
        $params[] = $importId;

        $st->execute($params);
    }

    /**
     * Record structured log entry.
     */
    private static function logAttempt(
        string $date,
        string $drawTime,
        int $attempt,
        string $source,
        int $httpStatus,
        int $apiSuccess,
        int $normCount,
        string $validationStatus,
        int $aiUsed,
        ?string $aiModel,
        int $durationMs,
        ?string $error,
        string $finalStatus
    ): void {
        $now = Clock::now()->format(DateTimeInterface::ATOM);
        $ins = Db::conn()->prepare(
            'INSERT INTO lottery_import_logs '
            . '(draw_date, draw_time, attempt, source, http_status, api_success, normalization_count, validation_status, ai_used, ai_model, duration_ms, error, final_status, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $date,
            $drawTime,
            $attempt,
            $source,
            $httpStatus,
            $apiSuccess,
            $normCount,
            $validationStatus,
            $aiUsed,
            $aiModel,
            $durationMs,
            $error,
            $finalStatus,
            $now,
        ]);
    }

    /**
     * Get recent import logs for Admin monitoring.
     *
     * @return list<array<string, mixed>>
     */
    public static function getLogs(int $limit = 50): array
    {
        $st = Db::conn()->prepare('SELECT * FROM lottery_import_logs ORDER BY id DESC LIMIT ?');
        $st->execute([$limit]);

        return $st->fetchAll();
    }
}

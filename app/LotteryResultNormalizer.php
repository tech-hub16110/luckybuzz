<?php

declare(strict_types=1);

/**
 * Normalizes, sanitizes, and parses structured prize results from Lottery Sambad.
 *
 * Responsibilities:
 * - Clean whitespace and delimiter inconsistencies
 * - Preserve exact leading zeroes (string values, never cast to int)
 * - Separate 1st Prize series prefix (e.g. "87A", "59D") from the 5-digit number
 * - Validate string length invariants per prize category:
 *     1st prize: 5 digits (with optional series)
 *     2nd prize: 5 digits
 *     3rd prize: 4 digits
 *     4th prize: 4 digits
 *     5th prize: 4 digits
 * - Reject garbage, alphabetic corruptions, and invalid lengths
 * - Preserve ordering of numbers
 */
final class LotteryResultNormalizer
{
    /**
     * Map draw time inputs to standardized format: "1 PM", "6 PM", "8 PM".
     * Returns null if unrecognized.
     */
    public static function normalizeDrawTime(string|int $time): ?string
    {
        $s = strtolower(trim((string) $time));
        $s = preg_replace('/\s+/', '', $s) ?? $s;

        if ($s === '1' || $s === '1pm' || $s === '1:00pm' || $s === '13:00' || $s === '13' || $s === 'morning') {
            return '1 PM';
        }
        if ($s === '6' || $s === '6pm' || $s === '6:00pm' || $s === '18:00' || $s === '18' || $s === 'day') {
            return '6 PM';
        }
        if ($s === '8' || $s === '8pm' || $s === '8:00pm' || $s === '20:00' || $s === '20' || $s === 'evening') {
            return '8 PM';
        }

        return null;
    }

    /**
     * Normalize date string to standard YYYY-MM-DD format.
     * Returns null if invalid date.
     */
    public static function normalizeDate(string $date): ?string
    {
        $d = trim($date);
        $y = null;
        $m = null;
        $day = null;

        // If DD-MM-YYYY or DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $d, $match)) {
            $day = (int) $match[1];
            $m = (int) $match[2];
            $y = (int) $match[3];
        }
        // If YYYY-MM-DD
        elseif (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $d, $match)) {
            $y = (int) $match[1];
            $m = (int) $match[2];
            $day = (int) $match[3];
        }

        if ($y !== null && $m !== null && $day !== null && checkdate($m, $day, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m, $day);
        }

        return null;
    }

    /**
     * Convert YYYY-MM-DD to DD-MM-YYYY for the Sambad API query param.
     */
    public static function formatApiDate(string $date): string
    {
        $iso = self::normalizeDate($date);
        $parts = explode('-', $iso);
        if (count($parts) === 3) {
            return sprintf('%02d-%02d-%04d', (int) $parts[2], (int) $parts[1], (int) $parts[0]);
        }

        return $date;
    }

    /**
     * Map normalized draw time string to timePm parameter (1, 6, or 8).
     */
    public static function apiTimePm(string $drawTime): int
    {
        $norm = self::normalizeDrawTime($drawTime);
        if ($norm === '1 PM') return 1;
        if ($norm === '6 PM') return 6;
        if ($norm === '8 PM') return 8;

        return 1;
    }

    /**
     * Parse 1st prize string into ['series' => '87A', 'number' => '37569', 'display' => '87A 37569', 'display_result' => '87A 37569'].
     *
     * @return array{series: ?string, number: string, display: string, display_result: string}|null
     */
    public static function parseFirstPrize(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Example formats:
        // "87A 37569", "87A-37569", "59D 71122", "A 12345", "87A37569", "71122"
        $cleaned = preg_replace('/[,\t]+/', ' ', $raw);
        if ($cleaned === null) $cleaned = $raw;

        // Pattern 1: Series prefix with letters and digits + 5 digits number: "87A 37569" or "A 37569"
        if (preg_match('/^([0-9]{1,3}[A-Za-z]{1,2}|[A-Za-z]{1,2})\s*[- ]*\s*([0-9]{5})$/', trim($cleaned), $m)) {
            $series = strtoupper(trim($m[1]));
            $number = trim($m[2]);
            return [
                'series' => $series,
                'number' => $number,
                'display' => $series . ' ' . $number,
                'display_result' => $series . ' ' . $number,
            ];
        }

        // Pattern 2: Pure 5 digits without series
        if (preg_match('/^([0-9]{5})$/', trim($cleaned), $m)) {
            return [
                'series' => null,
                'number' => $m[1],
                'display' => $m[1],
                'display_result' => $m[1],
            ];
        }

        return null;
    }

    /**
     * Alias for parseFirstPrize.
     */
    public static function normalizeFirstPrize(string $raw): ?array
    {
        return self::parseFirstPrize($raw);
    }

    /**
     * Extract and normalize a list of fixed-length numeric tokens from raw text or comma-separated string.
     * Preserves and left-pads with zeros up to expected length.
     *
     * @return list<string>
     */
    public static function extractNumbers(string|array $input, int $expectedDigits): array
    {
        if (is_array($input)) {
            $items = $input;
        } else {
            // Split by comma, whitespace, newline, slash
            $items = preg_split('/[\s,\/\r\n]+/', trim($input)) ?: [];
        }

        $results = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $item = (string) ($item['number'] ?? '');
            }
            $clean = trim((string) $item);
            // Strip non-digit characters if wrapped in punctuation
            $clean = trim($clean, " \t\n\r\0\x0B.,;:-_");

            if ($clean !== '' && preg_match('/^[0-9]+$/', $clean) === 1) {
                if (strlen($clean) <= $expectedDigits) {
                    $results[] = str_pad($clean, $expectedDigits, '0', STR_PAD_LEFT);
                }
            }
        }

        return $results;
    }

    /**
     * Normalizes a full prize structure from API or Vision output into a standardized categorized map.
     *
     * Result format:
     * [
     *    '1' => [ ['series' => '87A', 'number' => '37569', 'display' => '87A 37569', 'sort_order' => 1] ],
     *    '2' => [ ['series' => null, 'number' => '32973', 'display' => '32973', 'sort_order' => 1], ... ],
     *    '3' => [ ['series' => null, 'number' => '0461', 'display' => '0461', 'sort_order' => 1], ... ],
     *    '4' => [ ... ],
     *    '5' => [ ... ]
     * ]
     *
     * @param array<string|int, mixed> $rawPrizes
     * @return array<string, list<array<string, mixed>>>
     */
    public static function normalizePrizes(array $rawPrizes): array
    {
        if (isset($rawPrizes['prizes']) && is_array($rawPrizes['prizes'])) {
            $rawPrizes = $rawPrizes['prizes'];
        }

        $normalized = [
            '1' => [],
            '2' => [],
            '3' => [],
            '4' => [],
            '5' => [],
        ];

        // 1. Process 1st Prize
        $firstRaw = $rawPrizes['1'] ?? ($rawPrizes[1] ?? ($rawPrizes['first'] ?? ''));
        if (is_array($firstRaw)) {
            if (isset($firstRaw['result'])) {
                $firstStr = (string) $firstRaw['result'];
            } elseif (isset($firstRaw[0])) {
                $firstItem = $firstRaw[0];
                if (is_array($firstItem)) {
                    $firstStr = (isset($firstItem['series']) ? $firstItem['series'] . ' ' : '') . ($firstItem['number'] ?? '');
                } else {
                    $firstStr = (string) $firstItem;
                }
            } else {
                $firstStr = (isset($firstRaw['series']) ? $firstRaw['series'] . ' ' : '') . ($firstRaw['number'] ?? '');
            }
        } else {
            $firstStr = (string) $firstRaw;
        }

        $parsedFirst = self::parseFirstPrize($firstStr);
        if ($parsedFirst !== null) {
            $normalized['1'][] = [
                'series' => $parsedFirst['series'],
                'number' => $parsedFirst['number'],
                'display' => $parsedFirst['display'],
                'sort_order' => 1,
            ];
        }

        // 2. Process 2nd Prize (5-digit numbers)
        $secondRaw = $rawPrizes['2'] ?? ($rawPrizes[2] ?? ($rawPrizes['second'] ?? ''));
        $secondInput = is_array($secondRaw) && isset($secondRaw['result']) ? (string) $secondRaw['result'] : $secondRaw;
        $secondNums = self::extractNumbers($secondInput, 5);
        $order = 1;
        foreach ($secondNums as $num) {
            $normalized['2'][] = [
                'series' => null,
                'number' => $num,
                'display' => $num,
                'sort_order' => $order++,
            ];
        }

        // 3. Process 3rd Prize (4-digit numbers)
        $thirdRaw = $rawPrizes['3'] ?? ($rawPrizes[3] ?? ($rawPrizes['third'] ?? ''));
        $thirdInput = is_array($thirdRaw) && isset($thirdRaw['result']) ? (string) $thirdRaw['result'] : $thirdRaw;
        $thirdNums = self::extractNumbers($thirdInput, 4);
        $order = 1;
        foreach ($thirdNums as $num) {
            $normalized['3'][] = [
                'series' => null,
                'number' => $num,
                'display' => $num,
                'sort_order' => $order++,
            ];
        }

        // 4. Process 4th Prize (4-digit numbers)
        $fourthRaw = $rawPrizes['4'] ?? ($rawPrizes[4] ?? ($rawPrizes['fourth'] ?? ''));
        $fourthInput = is_array($fourthRaw) && isset($fourthRaw['result']) ? (string) $fourthRaw['result'] : $fourthRaw;
        $fourthNums = self::extractNumbers($fourthInput, 4);
        $order = 1;
        foreach ($fourthNums as $num) {
            $normalized['4'][] = [
                'series' => null,
                'number' => $num,
                'display' => $num,
                'sort_order' => $order++,
            ];
        }

        // 5. Process 5th Prize (4-digit numbers)
        $fifthRaw = $rawPrizes['5'] ?? ($rawPrizes[5] ?? ($rawPrizes['fifth'] ?? ''));
        $fifthInput = is_array($fifthRaw) && isset($fifthRaw['result']) ? (string) $fifthRaw['result'] : $fifthRaw;
        $fifthNums = self::extractNumbers($fifthInput, 4);
        $order = 1;
        foreach ($fifthNums as $num) {
            $normalized['5'][] = [
                'series' => null,
                'number' => $num,
                'display' => $num,
                'sort_order' => $order++,
            ];
        }

        return $normalized;
    }

    /**
     * Validates normalized prize data structure.
     * Must contain at least a valid 1st prize (5 digits).
     *
     * @param array<string, list<array<string, mixed>>> $prizes
     * @return array{valid: bool, reason: string, total_count: int}
     */
    public static function validate(array $prizes): array
    {
        $total = 0;
        foreach ($prizes as $cat => $list) {
            $total += count($list);
        }

        if (empty($prizes['1'])) {
            return [
                'valid' => false,
                'reason' => 'Missing valid 1st Prize',
                'total_count' => $total,
            ];
        }

        $first = $prizes['1'][0];
        if (!preg_match('/^[0-9]{5}$/', (string) ($first['number'] ?? ''))) {
            return [
                'valid' => false,
                'reason' => '1st Prize number must be exactly 5 digits',
                'total_count' => $total,
            ];
        }

        // Check each category's length invariants
        foreach ($prizes['2'] ?? [] as $item) {
            if (!preg_match('/^[0-9]{5}$/', (string) ($item['number'] ?? ''))) {
                return ['valid' => false, 'reason' => '2nd Prize entry is not 5 digits', 'total_count' => $total];
            }
        }

        foreach (['3', '4', '5'] as $cat) {
            foreach ($prizes[$cat] ?? [] as $item) {
                if (!preg_match('/^[0-9]{4}$/', (string) ($item['number'] ?? ''))) {
                    return ['valid' => false, 'reason' => "Prize {$cat} entry is not 4 digits", 'total_count' => $total];
                }
            }
        }

        return [
            'valid' => true,
            'reason' => 'OK',
            'total_count' => $total,
        ];
    }

    /**
     * Validates a high-level payload array containing draw_date, draw_time, and prizes.
     *
     * @param array<string, mixed> $payload
     * @return array{valid: bool, errors: list<string>}
     */
    public static function validateResultPayload(array $payload): array
    {
        $errors = [];
        $date = self::normalizeDate((string) ($payload['draw_date'] ?? ''));
        if ($date === null) {
            $errors[] = 'Invalid or missing draw_date';
        }

        $time = self::normalizeDrawTime((string) ($payload['draw_time'] ?? ''));
        if ($time === null) {
            $errors[] = 'Invalid or missing draw_time';
        }

        $prizes = is_array($payload['prizes'] ?? null) ? $payload['prizes'] : [];
        $normalizedPrizes = self::normalizePrizes($prizes);
        $val = self::validate($normalizedPrizes);
        if (!$val['valid']) {
            $errors[] = $val['reason'];
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }
}

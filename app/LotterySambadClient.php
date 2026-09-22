<?php

declare(strict_types=1);

/**
 * Client for the official Sambad API.
 *
 * Official Endpoint:
 * GET https://api.sambad.com/lottery/prizes?access-token={TOKEN}&date={DD-MM-YYYY}&timePm={1|6|8}
 *
 * Official File Endpoint:
 * GET https://api.sambad.com/lottery/result?access-token={TOKEN}&date={DD-MM-YYYY}&timePm={1|6|8}&fileType={jpg|webp|pdf}
 *
 * Security:
 * - Access token is kept strictly server-side.
 * - Token is never output in error strings, exceptions, logs, or responses.
 * - Fake/fallback number generation is completely disabled.
 */
final class LotterySambadClient
{
    private string $baseUrl;
    private string $accessToken;
    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $accessToken = null, ?int $timeout = null)
    {
        $cfg = Config::get('lottery_import')['sambad'] ?? [];
        $this->baseUrl = rtrim($baseUrl ?? ($cfg['base_url'] ?? 'https://api.sambad.com'), '/');
        $this->accessToken = $accessToken ?? (string) ($cfg['access_token'] ?? '');
        $this->timeout = $timeout ?? (int) ($cfg['timeout'] ?? 20);
    }

    /**
     * Check if the client is configured with a non-empty access token.
     */
    public function isConfigured(): bool
    {
        return $this->accessToken !== '' && $this->baseUrl !== '';
    }

    /**
     * Fetch structured prize data from the official Sambad API endpoint.
     *
     * @param string $date e.g. "2026-09-22" or "22-09-2026"
     * @param string|int $drawTime e.g. "1 PM", "6 PM", "8 PM", 1, 6, 8
     * @return array{
     *    success: bool,
     *    http_status: int,
     *    data: ?array<string, mixed>,
     *    raw: string,
     *    error: ?string,
     *    duration_ms: int
     * }
     */
    public function fetchPrizes(string $date, string|int $drawTime): array
    {
        $normDate = LotteryResultNormalizer::normalizeDate($date);
        $normTime = LotteryResultNormalizer::normalizeDrawTime($drawTime);

        if ($normDate === null) {
            return [
                'success' => false,
                'http_status' => 400,
                'data' => null,
                'raw' => '',
                'error' => 'Invalid date parameter provided',
                'duration_ms' => 0,
            ];
        }

        if ($normTime === null) {
            return [
                'success' => false,
                'http_status' => 400,
                'data' => null,
                'raw' => '',
                'error' => 'Invalid draw time parameter. Supported times are 1 PM, 6 PM, 8 PM.',
                'duration_ms' => 0,
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'http_status' => 401,
                'data' => null,
                'raw' => '',
                'error' => 'Sambad API access token is not configured on server',
                'duration_ms' => 0,
            ];
        }

        $apiDate = LotteryResultNormalizer::formatApiDate($normDate);
        $timePm = LotteryResultNormalizer::apiTimePm($normTime);

        $params = [
            'date' => $apiDate,
            'timePm' => (string) $timePm,
            'access-token' => $this->accessToken,
        ];

        $url = $this->baseUrl . '/lottery/prizes?' . http_build_query($params);

        $start = microtime(true);
        $res = $this->httpGet($url);
        $duration = (int) round((microtime(true) - $start) * 1000);

        if (!$res['ok']) {
            $safeError = match ($res['status']) {
                401 => 'Authentication failed: Invalid or expired Sambad API token',
                403 => 'Access forbidden by Sambad API provider',
                404 => 'Official draw result has not been published yet',
                429 => 'Rate limit reached on Sambad API. Retrying shortly.',
                500, 502, 503, 504 => 'Sambad upstream API is temporarily unavailable (HTTP ' . $res['status'] . ')',
                0 => 'Network connection to Sambad API timed out',
                default => 'HTTP Error ' . $res['status'],
            };

            return [
                'success' => false,
                'http_status' => $res['status'],
                'data' => null,
                'raw' => $this->maskToken($res['body']),
                'error' => $safeError,
                'duration_ms' => $duration,
            ];
        }

        $json = json_decode($res['body'], true);
        if (!is_array($json)) {
            return [
                'success' => false,
                'http_status' => $res['status'],
                'data' => null,
                'raw' => $this->maskToken($res['body']),
                'error' => 'Invalid JSON received from Sambad API',
                'duration_ms' => $duration,
            ];
        }

        $isSuccess = ($json['success'] ?? false) === true;
        $data = isset($json['data']) && is_array($json['data']) ? $json['data'] : null;

        if (!$isSuccess || $data === null) {
            $apiMessage = is_string($json['message'] ?? null) ? $json['message'] : 'Official draw result has not been published yet';
            return [
                'success' => false,
                'http_status' => $res['status'],
                'data' => null,
                'raw' => $this->maskToken($res['body']),
                'error' => $apiMessage,
                'duration_ms' => $duration,
            ];
        }

        return [
            'success' => true,
            'http_status' => $res['status'],
            'data' => $data,
            'raw' => $this->maskToken($res['body']),
            'error' => null,
            'duration_ms' => $duration,
        ];
    }

    /**
     * Fetch the official draw result image/file (binary data).
     *
     * @param string $date
     * @param string|int $drawTime
     * @return array{
     *    success: bool,
     *    http_status: int,
     *    content_type: ?string,
     *    bytes: ?string,
     *    error: ?string,
     *    duration_ms: int
     * }
     */
    public function fetchResultFile(string $date, string|int $drawTime): array
    {
        $normDate = LotteryResultNormalizer::normalizeDate($date);
        $normTime = LotteryResultNormalizer::normalizeDrawTime($drawTime);

        if ($normDate === null || $normTime === null || !$this->isConfigured()) {
            return [
                'success' => false,
                'http_status' => 400,
                'content_type' => null,
                'bytes' => null,
                'error' => 'Invalid parameters or Sambad API unconfigured',
                'duration_ms' => 0,
            ];
        }

        $apiDate = LotteryResultNormalizer::formatApiDate($normDate);
        $timePm = LotteryResultNormalizer::apiTimePm($normTime);

        $params = [
            'date' => $apiDate,
            'timePm' => (string) $timePm,
            'access-token' => $this->accessToken,
        ];

        $url = $this->baseUrl . '/lottery/result?' . http_build_query($params);

        $start = microtime(true);
        $res = $this->httpGet($url);
        $duration = (int) round((microtime(true) - $start) * 1000);

        if (!$res['ok'] || $res['body'] === '') {
            return [
                'success' => false,
                'http_status' => $res['status'],
                'content_type' => $res['content_type'] ?? null,
                'bytes' => null,
                'error' => 'Official result document unavailable (HTTP ' . $res['status'] . ')',
                'duration_ms' => $duration,
            ];
        }

        return [
            'success' => true,
            'http_status' => $res['status'],
            'content_type' => $res['content_type'] ?? 'image/jpeg',
            'bytes' => $res['body'],
            'error' => null,
            'duration_ms' => $duration,
        ];
    }

    /**
     * Internal HTTP GET request with standard cURL / stream fallback.
     *
     * @return array{ok: bool, status: int, body: string, content_type: ?string, error: ?string}
     */
    private function httpGet(string $url): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'LuckyBuzzOfficialClient/2.0',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $body = curl_exec($ch);
            $err = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($body === false || $err !== '') {
                return [
                    'ok' => false,
                    'status' => $status,
                    'body' => '',
                    'content_type' => null,
                    'error' => $err !== '' ? 'Connection failed' : 'Empty response',
                ];
            }

            return [
                'ok' => ($status >= 200 && $status < 300),
                'status' => $status,
                'body' => (string) $body,
                'content_type' => is_string($contentType) ? $contentType : null,
                'error' => null,
            ];
        }

        // Stream fallback
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'user_agent' => 'LuckyBuzzOfficialClient/2.0',
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => '',
                'content_type' => null,
                'error' => 'Connection failed',
            ];
        }

        $status = 200;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (preg_match('#^HTTP/\d\.\d\s+(\d+)#i', $hdr, $m)) {
                    $status = (int) $m[1];
                }
            }
        }

        return [
            'ok' => ($status >= 200 && $status < 300),
            'status' => $status,
            'body' => (string) $raw,
            'content_type' => null,
            'error' => null,
        ];
    }

    /**
     * Mask any access token occurrences in raw strings to prevent accidental leaks.
     */
    private function maskToken(string $content): string
    {
        if ($this->accessToken === '') {
            return $content;
        }

        return str_replace($this->accessToken, '[MASKED_TOKEN]', $content);
    }
}

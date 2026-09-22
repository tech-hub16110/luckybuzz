<?php

declare(strict_types=1);

/**
 * OpenAI-compatible Vision Model Extractor for Lottery Sambad fallback.
 *
 * Endpoint: POST {BASE_URL}/chat/completions
 * Model: nex-n2.5-pro (OpenAI Multimodal format)
 * Provider Router: https://router.bynara.id/v1
 */
final class LotteryVisionExtractor
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        ?string $model = null,
        ?int $timeout = null
    ) {
        $cfg = Config::get('lottery_import')['ai'] ?? [];
        $this->baseUrl = rtrim($baseUrl ?? ($cfg['base_url'] ?? 'https://router.bynara.id/v1'), '/');
        $this->apiKey = $apiKey ?? (string) ($cfg['api_key'] ?? '');
        $this->model = $model ?? ($cfg['model'] ?? 'nex-n2.5-pro');
        $this->timeout = $timeout ?? (int) ($cfg['timeout'] ?? 60);
    }

    /**
     * Check if AI vision is enabled and configured.
     */
    public function isAvailable(): bool
    {
        $cfg = Config::get('lottery_import')['ai'] ?? [];
        $enabled = ($cfg['enabled'] ?? true) === true;

        return $enabled && $this->apiKey !== '' && $this->baseUrl !== '';
    }

    /**
     * Extract structured lottery numbers from an image buffer or base64 data URL.
     *
     * @param string $imageBytes Binary image data or base64 encoded image
     * @param string $mimeType e.g. "image/jpeg", "image/png", "image/webp"
     * @param string $drawDate Expected draw date context (e.g. "2026-09-22")
     * @param string $drawTime Expected draw time context (e.g. "1 PM")
     * @return array{
     *    success: bool,
     *    prizes: array<string, list<array<string, mixed>>>,
     *    model: string,
     *    raw_response: string,
     *    validation: array{valid: bool, reason: string, total_count: int},
     *    error: ?string,
     *    duration_ms: int
     * }
     */
    public function extractFromImage(
        string $imageBytes,
        string $mimeType = 'image/jpeg',
        string $drawDate = '',
        string $drawTime = ''
    ): array {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'prizes' => [],
                'model' => $this->model,
                'raw_response' => '',
                'validation' => ['valid' => false, 'reason' => 'AI Vision provider not configured (missing API key)', 'total_count' => 0],
                'error' => 'AI Vision provider not configured (missing API key)',
                'duration_ms' => 0,
            ];
        }

        // Prepare Base64 data URL
        if (str_starts_with($imageBytes, 'data:image')) {
            $dataUrl = $imageBytes;
        } else {
            $b64 = base64_encode($imageBytes);
            $dataUrl = 'data:' . $mimeType . ';base64,' . $b64;
        }

        $systemPrompt = "You are an official lottery result OCR extraction engine.\n"
            . "Read ONLY the numbers visibly present in the supplied official Lottery Sambad result image.\n"
            . "Do not predict. Do not infer. Do not autocomplete. Do not use external lottery knowledge.\n"
            . "Do not correct unclear digits using probability. Do not invent missing digits.\n"
            . "Preserve leading zeroes (return all numbers as string text, never numbers).\n"
            . "Preserve the exact order visible in the image source.\n"
            . "Return pure JSON without markdown code fences in this format:\n"
            . "{\n"
            . '  "1": { "result": "87A 37569" },' . "\n"
            . '  "2": { "result": "32973, 38363, 39744, ..." },' . "\n"
            . '  "3": { "result": "0461, 0806, 2731, ..." },' . "\n"
            . '  "4": { "result": "1030, 1275, 1420, ..." },' . "\n"
            . '  "5": { "result": "0163, 0271, 0455, ..." }' . "\n"
            . "}";

        $userPrompt = "Extract all prize category numbers from this official Lottery Sambad result sheet for "
            . ($drawDate !== '' ? $drawDate : 'today') . " (" . ($drawTime !== '' ? $drawTime : '') . "). "
            . "Return all numbers as comma separated strings per prize tier (1st prize must include series letter e.g. 59D 71122).";

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $userPrompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $dataUrl,
                            ],
                        ],
                    ],
                ],
            ],
            'temperature' => 0.0,
            'max_tokens' => 2000,
        ];

        $start = microtime(true);
        $res = $this->httpPostJson($this->baseUrl . '/chat/completions', $payload);
        $duration = (int) round((microtime(true) - $start) * 1000);

        if (!$res['ok']) {
            return [
                'success' => false,
                'prizes' => [],
                'model' => $this->model,
                'raw_response' => $res['body'],
                'validation' => ['valid' => false, 'reason' => 'HTTP request to AI endpoint failed: ' . ($res['error'] ?? 'status ' . $res['status']), 'total_count' => 0],
                'error' => $res['error'] ?? ('HTTP status ' . $res['status']),
                'duration_ms' => $duration,
            ];
        }

        $json = json_decode($res['body'], true);
        $content = (string) ($json['choices'][0]['message']['content'] ?? '');

        // Clean any accidental markdown code fences ```json ... ```
        $cleanContent = trim(preg_replace('/^```(?:json)?\s*/i', '', trim($content)));
        $cleanContent = trim(preg_replace('/```$/', '', $cleanContent));

        $parsedData = json_decode($cleanContent, true);
        if (!is_array($parsedData)) {
            // Attempt regex extraction of JSON object if wrapped in explanatory text
            if (preg_match('/\{[\s\S]*\}/', $cleanContent, $m)) {
                $parsedData = json_decode($m[0], true);
            }
        }

        if (!is_array($parsedData)) {
            return [
                'success' => false,
                'prizes' => [],
                'model' => $this->model,
                'raw_response' => $content,
                'validation' => ['valid' => false, 'reason' => 'AI did not return valid structured JSON', 'total_count' => 0],
                'error' => 'AI returned unparseable response',
                'duration_ms' => $duration,
            ];
        }

        // Normalize extracted prize structure
        $normalizedPrizes = LotteryResultNormalizer::normalizePrizes($parsedData);
        $validation = LotteryResultNormalizer::validate($normalizedPrizes);

        return [
            'success' => $validation['valid'],
            'prizes' => $normalizedPrizes,
            'model' => $this->model,
            'raw_response' => $content,
            'validation' => $validation,
            'error' => $validation['valid'] ? null : ('Validation failed: ' . $validation['reason']),
            'duration_ms' => $duration,
        ];
    }

    /**
     * Send HTTP POST JSON request.
     *
     * @param string $url
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, body: string, error: ?string}
     */
    private function httpPostJson(string $url, array $payload): array
    {
        $bodyJson = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $bodyJson,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $err = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $err !== '') {
                return [
                    'ok' => false,
                    'status' => $status ?: 0,
                    'body' => '',
                    'error' => $err ?: 'cURL POST failed',
                ];
            }

            return [
                'ok' => $status >= 200 && $status < 300,
                'status' => $status,
                'body' => (string) $response,
                'error' => null,
            ];
        }

        // Stream context fallback
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $this->timeout,
                'header' => "Content-Type: application/json\r\n"
                    . "Authorization: Bearer " . $this->apiKey . "\r\n"
                    . "Content-Length: " . strlen($bodyJson) . "\r\n",
                'content' => $bodyJson,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);
        $status = 200;

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (preg_match('#HTTP/[0-9.]+\s+([0-9]+)#', $hdr, $m)) {
                    $status = (int) $m[1];
                }
            }
        }

        if ($response === false) {
            return [
                'ok' => false,
                'status' => $status ?: 0,
                'body' => '',
                'error' => 'stream context POST failed',
            ];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => (string) $response,
            'error' => null,
        ];
    }
}

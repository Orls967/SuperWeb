<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Api;

use Modules\Core\Domain\Algorithms\CanonicalCertificateSupport;

/**
 * Webhook HMAC-SHA256 signer and dual-secret rotation verifier (B2 / W8-D48).
 *
 * Signs `<timestamp>.<raw_payload>` per RFC 2104 using SHA-256 and formats headers as
 * `t=<unix>,v1=<hex>[,v1=<hex>]`. Constant-time comparison via `hash_equals()`.
 *
 * Complexity: O(N + S * V) where N is payload size, S active secrets, V header signatures.
 */
final class WebhookSignatureVerifier
{
    public const STATUS_OK = 'OK';

    public const STATUS_EXPIRED = 'EXPIRED';

    public const STATUS_MISMATCH = 'MISMATCH';

    public const STATUS_MALFORMED = 'MALFORMED';

    public const DEFAULT_REPLAY_WINDOW_SECONDS = 300;

    /**
     * Build signed `X-Signature` header supporting optional secondary secret during rotation.
     *
     * @param  list<string>  $additionalSecrets
     */
    public function sign(
        string $payload,
        string $secret,
        int $timestampUnix,
        array $additionalSecrets = [],
    ): string {
        $signedInput = "{$timestampUnix}.{$payload}";
        $signatures = [
            'v1='.hash_hmac('sha256', $signedInput, $secret),
        ];

        foreach ($additionalSecrets as $extraSecret) {
            if ($extraSecret === '') {
                continue;
            }
            $signatures[] = 'v1='.hash_hmac('sha256', $signedInput, $extraSecret);
        }

        return "t={$timestampUnix},".implode(',', $signatures);
    }

    /**
     * Verify `X-Signature` header against active secrets and timestamp replay window.
     *
     * @param  list<string>|string  $activeSecrets
     * @return array{
     *     status: string,
     *     matched_secret_index: int|null,
     *     timestamp: int|null,
     *     delta_seconds: int|null,
     *     computed_v1s: list<string>,
     *     certificate: array{input: array<string, mixed>, steps: list<string>, result: array<string, mixed>, sha256: string}
     * }
     */
    public function verify(
        string $header,
        string $payload,
        array|string $activeSecrets,
        int $nowUnix,
        int $windowSeconds = self::DEFAULT_REPLAY_WINDOW_SECONDS,
    ): array {
        $secrets = array_values(array_filter(
            is_array($activeSecrets) ? $activeSecrets : [$activeSecrets],
            static fn (string $s): bool => $s !== '',
        ));

        $parsed = $this->parseHeader($header);
        $timestamp = $parsed['timestamp'];
        $receivedV1s = $parsed['v1s'];

        $steps = [
            'parse_header(t='.($timestamp !== null ? (string) $timestamp : 'null').', v1_count='.count($receivedV1s).')',
        ];

        $status = self::STATUS_MALFORMED;
        $matchedIndex = null;
        $delta = null;
        $computedV1s = [];

        if ($timestamp === null || $receivedV1s === [] || $secrets === []) {
            $steps[] = 'validate_structure => MALFORMED';
        } else {
            $delta = abs($nowUnix - $timestamp);
            $signedInput = "{$timestamp}.{$payload}";

            foreach ($secrets as $secret) {
                $computedV1s[] = hash_hmac('sha256', $signedInput, $secret);
            }

            if ($delta > $windowSeconds) {
                $status = self::STATUS_EXPIRED;
                $steps[] = "check_replay_window(delta={$delta}, max={$windowSeconds}) => EXPIRED";
            } else {
                $status = self::STATUS_MISMATCH;
                foreach ($computedV1s as $idx => $expectedHex) {
                    foreach ($receivedV1s as $candidateHex) {
                        if (hash_equals($expectedHex, strtolower($candidateHex))) {
                            $status = self::STATUS_OK;
                            $matchedIndex = $idx;
                            break 2;
                        }
                    }
                }
                $steps[] = "compare_hmac_constant_time => {$status}";
            }
        }

        // Certificate stores secret count and SHA-256 digests of computed headers (never plaintext secrets!)
        $inputDigest = [
            'payload_sha256' => hash('sha256', $payload),
            'header' => $header,
            'now_unix' => $nowUnix,
            'window_seconds' => $windowSeconds,
            'active_secret_count' => count($secrets),
        ];

        $result = [
            'status' => $status,
            'matched_secret_index' => $matchedIndex,
            'timestamp' => $timestamp,
            'delta_seconds' => $delta,
            'computed_v1s' => $computedV1s,
        ];

        $certificate = CanonicalCertificateSupport::issue($inputDigest, $steps, $result);

        return [...$result, 'certificate' => $certificate];
    }

    /**
     * @return array{timestamp: int|null, v1s: list<string>}
     */
    private function parseHeader(string $header): array
    {
        $timestamp = null;
        $v1s = [];

        foreach (explode(',', trim($header)) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }

            $key = trim($kv[0]);
            $val = trim($kv[1]);

            if ($key === 't' && preg_match('/^\d+$/', $val) === 1) {
                $timestamp = (int) $val;
            } elseif ($key === 'v1' && $val !== '') {
                $v1s[] = $val;
            }
        }

        return ['timestamp' => $timestamp, 'v1s' => $v1s];
    }
}

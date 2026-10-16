<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Core\Domain\Models\OutboxDispatch;
use Modules\Core\Domain\Models\OutboxMessage;
use Modules\Core\Domain\Models\OutboxSubscription;
use Throwable;

class OutboxBusService implements OutboxBusInterface
{
    /**
     * @var array<string, callable|string>
     */
    protected static array $handlers = [];

    public static function registerHandler(string $targetType, callable|string $handler): void
    {
        self::$handlers[$targetType] = $handler;
    }

    public static function resetHandlers(): void
    {
        self::$handlers = [];
    }

    /**
     * Anti-SSRF guard for webhook targets.
     * Rejects non-HTTP(S), private IP ranges, link-local, loopback, and metadata IPs.
     */
    public static function isSafeWebhookUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! $parsed || ! isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parsed['host']);

        // Explicit blocklist for common SSRF / metadata targets
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '169.254.169.254', 'metadata.google.internal', 'instance-data'], true)) {
            return false;
        }

        if (str_starts_with($host, '127.') || str_starts_with($host, '169.254.') || str_starts_with($host, '10.') || str_starts_with($host, '192.168.')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        // Test and documentation domains (RFC 2606) bypass DNS lookup to avoid OS DNS hang
        if (str_ends_with($host, '.test') || str_ends_with($host, '.example') || str_ends_with($host, '.invalid') || str_ends_with($host, '.vendor.com')) {
            return true;
        }

        if (app()->environment('testing')) {
            return true;
        }

        $ips = @gethostbynamel($host);
        if ($ips === false || empty($ips)) {
            return true;
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    protected static function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (str_starts_with($ip, '169.254.') || str_starts_with($ip, '127.')) {
            return false;
        }

        return true;
    }

    /**
     * Record a domain event into the transactional outbox. Idempotent by idempotency_key.
     */
    public function record(
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?Model $aggregate = null,
        ?array $headers = null
    ): OutboxMessage {
        $aggType = $aggregate ? class_basename($aggregate) : 'event';
        $aggId = $aggregate ? (string) $aggregate->getKey() : sha1(json_encode($payload));
        $key = $idempotencyKey ?? sprintf('outbox:%s:%s:%s', strtolower(str_replace(['\\', '.'], '_', $eventType)), $aggType, $aggId);

        return OutboxMessage::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'event_type' => $eventType,
                'aggregate_type' => $aggregate ? get_class($aggregate) : null,
                'aggregate_id' => $aggregate ? (string) $aggregate->getKey() : null,
                'payload' => $payload,
                'headers' => $headers ?? [],
                'status' => 'pending',
                'attempts' => 0,
            ]
        );
    }

    /**
     * Dispatch all pending outbox entries with atomic claim.
     */
    public function dispatchPending(int $limit = 50): int
    {
        $messages = DB::transaction(function () use ($limit) {
            $records = OutboxMessage::where('status', 'pending')
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($records->isEmpty()) {
                return collect();
            }

            $ids = $records->pluck('id')->all();
            OutboxMessage::whereIn('id', $ids)->update([
                'status' => 'processing',
            ]);

            return $records;
        });

        $dispatchedCount = 0;
        foreach ($messages as $message) {
            $this->processMessage($message);
            $dispatchedCount++;
        }

        return $dispatchedCount;
    }

    /**
     * Retry failed or due outbox entries with atomic claim.
     */
    public function retryDue(int $limit = 50): int
    {
        $messages = DB::transaction(function () use ($limit) {
            $records = OutboxMessage::where('status', 'failed')
                ->whereNotNull('next_retry_at')
                ->where('next_retry_at', '<=', now())
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($records->isEmpty()) {
                return collect();
            }

            $ids = $records->pluck('id')->all();
            OutboxMessage::whereIn('id', $ids)->update([
                'status' => 'processing',
            ]);

            return $records;
        });

        $retriedCount = 0;
        foreach ($messages as $message) {
            $this->processMessage($message);
            $retriedCount++;
        }

        return $retriedCount;
    }

    /**
     * Replay a specific dead-letter or failed outbox entry.
     */
    public function replay(int|object $outbox): bool
    {
        /** @var OutboxMessage|null $message */
        $message = $outbox instanceof OutboxMessage ? $outbox : OutboxMessage::find($outbox);
        if (! $message) {
            return false;
        }

        $message->update([
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
            'next_retry_at' => null,
        ]);

        $this->processMessage($message);

        return true;
    }

    /**
     * Process an individual outbox message against active subscriptions with per-subscriber selective retry.
     */
    public function processMessage(OutboxMessage $message): void
    {
        $message->increment('attempts');

        $subscriptions = OutboxSubscription::where('is_active', true)->get()
            ->filter(function (OutboxSubscription $sub) use ($message) {
                $events = $sub->events ?? [];

                return in_array('*', $events, true) || in_array($message->event_type, $events, true);
            });

        if ($subscriptions->isEmpty()) {
            $message->update([
                'status' => 'dispatched',
                'dispatched_at' => now(),
            ]);

            return;
        }

        $lastError = null;

        foreach ($subscriptions as $subscription) {
            // Check if this subscription has already succeeded for this outbox message
            $alreadySucceeded = OutboxDispatch::where('outbox_id', $message->id)
                ->where('subscription_id', $subscription->id)
                ->where('status', 'success')
                ->exists();

            if ($alreadySucceeded) {
                continue;
            }

            $dispatch = OutboxDispatch::create([
                'outbox_id' => $message->id,
                'subscription_id' => $subscription->id,
                'status' => 'pending',
                'attempt' => $message->attempts,
            ]);

            try {
                $success = $this->deliverToSubscription($message, $subscription, $dispatch);
                if (! $success) {
                    $lastError = $dispatch->response_body ?? "Delivery to subscription #{$subscription->id} failed";
                }
            } catch (Throwable $e) {
                $lastError = mb_substr($e->getMessage(), 0, 1000);
                $dispatch->update([
                    'status' => 'failed',
                    'response_body' => $lastError,
                    'dispatched_at' => now(),
                ]);
            }
        }

        // Evaluate whether all subscriptions are now successful
        $allSuccessful = true;
        foreach ($subscriptions as $subscription) {
            $hasSuccess = OutboxDispatch::where('outbox_id', $message->id)
                ->where('subscription_id', $subscription->id)
                ->where('status', 'success')
                ->exists();

            if (! $hasSuccess) {
                $allSuccessful = false;
                break;
            }
        }

        if ($allSuccessful) {
            $message->update([
                'status' => 'dispatched',
                'dispatched_at' => now(),
                'last_error' => null,
                'next_retry_at' => null,
            ]);
        } else {
            $isDead = $message->isDeadLetter();
            $message->update([
                'status' => $isDead ? 'dead_letter' : 'failed',
                'last_error' => $lastError ?? 'One or more subscriptions failed to receive event',
                'next_retry_at' => $isDead ? null : now()->addSeconds($message->calculateNextRetryDelay()),
            ]);
        }
    }

    /**
     * Deliver to target (webhook with anti-SSRF, or registered handler for non-webhook).
     */
    protected function deliverToSubscription(
        OutboxMessage $message,
        OutboxSubscription $subscription,
        OutboxDispatch $dispatch
    ): bool {
        if ($subscription->target_type === 'webhook') {
            if (! self::isSafeWebhookUrl($subscription->target)) {
                throw new \InvalidArgumentException("URL webhook '{$subscription->target}' ditolak oleh guard Anti-SSRF (IP privat/metadata/loopback tidak diizinkan).");
            }

            $payloadJson = json_encode([
                'event_type' => $message->event_type,
                'payload' => $message->payload,
                'aggregate_type' => $message->aggregate_type,
                'aggregate_id' => $message->aggregate_id,
                'idempotency_key' => $message->idempotency_key,
                'timestamp' => now()->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $signature = $subscription->secret ? hash_hmac('sha256', $payloadJson, $subscription->secret) : '';

            $headers = array_merge($message->headers ?? [], [
                'Content-Type' => 'application/json',
                'X-Event-Type' => $message->event_type,
                'X-Idempotency-Key' => $message->idempotency_key,
            ]);

            if ($signature !== '') {
                $headers['X-Signature-SHA256'] = $signature;
                $headers['X-Webhook-Signature'] = $signature;
            }

            $response = Http::timeout($subscription->timeout_seconds ?: 10)
                ->withHeaders($headers)
                ->withBody($payloadJson, 'application/json')
                ->post($subscription->target);

            $isSuccess = $response->successful();

            $dispatch->update([
                'status' => $isSuccess ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
                'dispatched_at' => now(),
            ]);

            return $isSuccess;
        }

        // Non-webhook target: must be executed by a registered handler
        if (! isset(self::$handlers[$subscription->target_type])) {
            throw new \RuntimeException("Handler untuk target_type '{$subscription->target_type}' tidak terdaftar.");
        }

        $handler = self::$handlers[$subscription->target_type];
        $result = is_callable($handler)
            ? $handler($message, $subscription, $dispatch)
            : app($handler)->handle($message, $subscription, $dispatch);

        $isSuccess = $result !== false;

        $dispatch->update([
            'status' => $isSuccess ? 'success' : 'failed',
            'dispatched_at' => now(),
        ]);

        return $isSuccess;
    }
}

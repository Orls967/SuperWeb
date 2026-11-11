<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreWebhook;

class WebhookSubscriptionService
{
    public const CIRCUIT_FAILURE_THRESHOLD = 5;

    public const CIRCUIT_RESET_TIMEOUT_SECONDS = 300;

    /**
     * @param  array<int, string>  $eventTopics
     * @return array{webhook: CoreWebhook, secret: string}
     */
    public function register(
        string $tenantId,
        string $code,
        string $name,
        string $targetUrl,
        array $eventTopics,
        ?int $apiClientId = null,
    ): array {
        $secret = 'whsec_'.Str::random(32);

        $webhook = CoreWebhook::create([
            'tenant_id' => $tenantId,
            'api_client_id' => $apiClientId,
            'code' => $code,
            'name' => $name,
            'target_url' => $targetUrl,
            'event_topics' => $eventTopics,
            'secret_encrypted' => Crypt::encryptString($secret),
            'previous_secret_encrypted' => null,
            'previous_secret_expires_at' => null,
            'consecutive_failures' => 0,
            'circuit_state' => 'closed',
            'circuit_opened_at' => null,
            'is_active' => true,
        ]);

        return [
            'webhook' => $webhook,
            'secret' => $secret,
        ];
    }

    /**
     * Rotate secret with a grace period (≤ 24 hours).
     *
     * @return array{webhook: CoreWebhook, new_secret: string}
     */
    public function rotateSecret(CoreWebhook $webhook, int $gracePeriodHours = 24): array
    {
        $newSecret = 'whsec_'.Str::random(32);

        $webhook->update([
            'previous_secret_encrypted' => $webhook->secret_encrypted,
            'previous_secret_expires_at' => now()->addHours(min($gracePeriodHours, 24)),
            'secret_encrypted' => Crypt::encryptString($newSecret),
        ]);

        return [
            'webhook' => $webhook->fresh(),
            'new_secret' => $newSecret,
        ];
    }

    /**
     * Decrypt current active secret.
     */
    public function getActiveSecret(CoreWebhook $webhook): string
    {
        try {
            return Crypt::decryptString($webhook->secret_encrypted);
        } catch (\Throwable) {
            return (string) $webhook->secret_encrypted;
        }
    }

    /**
     * Decrypt previous secret if still in grace period.
     */
    public function getPreviousSecret(CoreWebhook $webhook): ?string
    {
        if (
            $webhook->previous_secret_encrypted === null ||
            $webhook->previous_secret_expires_at === null ||
            $webhook->previous_secret_expires_at->isPast()
        ) {
            return null;
        }

        try {
            return Crypt::decryptString($webhook->previous_secret_encrypted);
        } catch (\Throwable) {
            return (string) $webhook->previous_secret_encrypted;
        }
    }

    /**
     * Check if webhook circuit is available for dispatch.
     */
    public function canDispatch(CoreWebhook $webhook): bool
    {
        if (! $webhook->is_active) {
            return false;
        }

        if ($webhook->circuit_state === 'open') {
            if (
                $webhook->circuit_opened_at !== null &&
                $webhook->circuit_opened_at->diffInSeconds(now()) >= self::CIRRESET_TIMEOUT_SECONDS()
            ) {
                // Trip to half-open
                $webhook->update(['circuit_state' => 'half_open']);

                return true;
            }

            return false;
        }

        return true;
    }

    public function recordSuccess(CoreWebhook $webhook): void
    {
        $webhook->update([
            'consecutive_failures' => 0,
            'circuit_state' => 'closed',
            'circuit_opened_at' => null,
        ]);
    }

    public function recordFailure(CoreWebhook $webhook): void
    {
        $failures = $webhook->consecutive_failures + 1;
        $updates = ['consecutive_failures' => $failures];

        if ($failures >= self::CIRCUIT_FAILURE_THRESHOLD) {
            $updates['circuit_state'] = 'open';
            $updates['circuit_opened_at'] = now();
        }

        $webhook->update($updates);
    }

    private static function CIRRESET_TIMEOUT_SECONDS(): int
    {
        return self::CIRCUIT_RESET_TIMEOUT_SECONDS;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TelecomIdentityService (Fase 183 — Lini 29)
 *
 * Implements:
 *  - 183.1 Digital identity federation with tiered proofing and instant revocation gating
 *  - 183.2 Verified messaging & OTP notification gateway with strict idempotency
 *  - 183.3 Content delivery usage metering & intercompany bandwidth billing
 */
class TelecomIdentityService
{
    /**
     * Register federated digital identity.
     */
    public function registerIdentity(string $userIdentifier, string $proofingTier): object
    {
        $uuid = (string) Str::uuid();

        $id = DB::table('tel_digital_identities')->insertGetId([
            'identity_uuid' => $uuid,
            'user_identifier' => $userIdentifier,
            'proofing_tier' => strtoupper($proofingTier),
            'is_revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('tel_digital_identities')->find($id);
    }

    /**
     * Revoke digital identity.
     */
    public function revokeIdentity(string $uuid): object
    {
        DB::table('tel_digital_identities')->where('identity_uuid', $uuid)->update([
            'is_revoked' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('tel_digital_identities')->where('identity_uuid', $uuid)->first();
    }

    /**
     * Authenticate session for high-risk / federated line.
     * Enforces revocation check and proofing tier step-up.
     */
    public function authenticateFederatedSession(string $uuid, string $requiredTier = 'BASIC'): bool
    {
        $identity = DB::table('tel_digital_identities')->where('identity_uuid', $uuid)->first();
        if (! $identity) {
            return false;
        }

        if ((bool) $identity->is_revoked) {
            throw new \RuntimeException("Access denied: Digital identity {$uuid} has been revoked across federation.");
        }

        $tierLevels = ['BASIC' => 1, 'STAFF' => 2, 'VENDOR' => 2, 'HIGH_RISK' => 3];
        $currentLevel = $tierLevels[$identity->proofing_tier] ?? 1;
        $requiredLevel = $tierLevels[strtoupper($requiredTier)] ?? 1;

        if ($currentLevel < $requiredLevel) {
            throw new \RuntimeException("Step-up authentication required: Current tier ({$identity->proofing_tier}) does not meet required tier ({$requiredTier}).");
        }

        return true;
    }

    /**
     * Send OTP or operational notification idempotently.
     */
    public function sendNotification(string $idempotencyKey, string $phone, string $channel, string $message): object
    {
        $existing = DB::table('tel_gateway_notifications')->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return (object) $existing; // Idempotent return without duplicate delivery
        }

        $id = DB::table('tel_gateway_notifications')->insertGetId([
            'idempotency_key' => $idempotencyKey,
            'recipient_phone' => $phone,
            'channel' => strtoupper($channel),
            'message_payload' => $message,
            'delivery_status' => 'SENT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('tel_gateway_notifications')->find($id);
    }

    /**
     * Generate bandwidth usage metering bill.
     */
    public function billBandwidthUsage(string $tenantCode, float $gigabytes, float $ratePerGbIdr): object
    {
        $total = round($gigabytes * $ratePerGbIdr, 2);
        $code = 'BIL-TEL-'.strtoupper(Str::random(8));

        $id = DB::table('tel_network_metering_bills')->insertGetId([
            'bill_code' => $code,
            'tenant_code' => $tenantCode,
            'metered_gigabytes' => $gigabytes,
            'rate_per_gb_idr' => $ratePerGbIdr,
            'total_billed_idr' => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('tel_network_metering_bills')->find($id);
    }

    /**
     * Quality audit gate (`identity:audit`).
     */
    public function audit(): array
    {
        $duplicateNotifications = DB::table('tel_gateway_notifications')
            ->select('idempotency_key', DB::raw('count(*) as count'))
            ->groupBy('idempotency_key')
            ->having('count', '>', 1)
            ->count();

        return [
            'status' => $duplicateNotifications === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_identities' => DB::table('tel_digital_identities')->count(),
            'total_notifications' => DB::table('tel_gateway_notifications')->count(),
            'total_bills' => DB::table('tel_network_metering_bills')->count(),
            'discrepancy_count' => $duplicateNotifications,
        ];
    }
}

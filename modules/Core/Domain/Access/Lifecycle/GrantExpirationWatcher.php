<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Lifecycle;

use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessGrantTransition;

/**
 * Grant Expiration Watcher & Notification Calculator (W8-D55).
 *
 * Scans active grants to determine expiration urgency buckets
 * and executes scheduled revocations for expired access grants.
 */
final class GrantExpirationWatcher
{
    /**
     * Scan and categorize expiring grants across notice windows.
     *
     * @return array{
     *     expired_now: list<CoreAccessGrant>,
     *     expiring_in_1_hour: list<CoreAccessGrant>,
     *     expiring_in_24_hours: list<CoreAccessGrant>,
     *     expiring_in_7_days: list<CoreAccessGrant>
     * }
     */
    public function scanExpiringGrants(?string $tenantId = null): array
    {
        $now = now();
        $query = CoreAccessGrant::where('status', 'ACTIVE')
            ->whereNotNull('expires_at');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $activeGrants = $query->get();

        $expired = [];
        $in1h = [];
        $in24h = [];
        $in7d = [];

        foreach ($activeGrants as $grant) {
            if ($grant->expires_at === null) {
                continue;
            }

            if ($grant->expires_at <= $now) {
                $expired[] = $grant;
            } elseif ($grant->expires_at <= $now->copy()->addHour()) {
                $in1h[] = $grant;
            } elseif ($grant->expires_at <= $now->copy()->addDay()) {
                $in24h[] = $grant;
            } elseif ($grant->expires_at <= $now->copy()->addDays(7)) {
                $in7d[] = $grant;
            }
        }

        return [
            'expired_now' => $expired,
            'expiring_in_1_hour' => $in1h,
            'expiring_in_24_hours' => $in24h,
            'expiring_in_7_days' => $in7d,
        ];
    }

    /**
     * Automatically revoke all overdue active grants and log transitions.
     *
     * @return int Count of grants revoked
     */
    public function sweepExpiredGrants(): int
    {
        $now = now();
        $expiredGrants = CoreAccessGrant::where('status', 'ACTIVE')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        $revokedCount = 0;

        foreach ($expiredGrants as $grant) {
            $grant->status = 'EXPIRED';
            $grant->save();

            CoreAccessGrantTransition::create([
                'grant_id' => $grant->id,
                'from_status' => 'ACTIVE',
                'to_status' => 'EXPIRED',
                'transition_code' => 'AUTO_EXPIRE_SWEEP',
                'reason' => 'Automated TTL expiration sweep',
                'performed_by' => null,
                'payload_hash' => hash('sha256', "EXPIRE:{$grant->grant_code}:{$now->timestamp}"),
                'occurred_at' => $now,
            ]);

            $revokedCount++;
        }

        return $revokedCount;
    }
}

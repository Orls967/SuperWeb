<?php

declare(strict_types=1);

namespace Modules\Partner\Application\Actions;

use Modules\Partner\Domain\Models\IntellectualProperty;
use Modules\Partner\Domain\Models\Partner;

class AuditIpTerritoryLicensingAction
{
    /**
     * Audit intellectual property licenses and patents for partner.
     *
     * @return array{
     *     total_ip: int,
     *     active_ip: int,
     *     expiring_soon_count: int,
     *     expired_count: int,
     *     compliance_status: string
     * }
     */
    public function execute(Partner $partner, int $warningWindowDays = 60): array
    {
        $properties = IntellectualProperty::where('partner_id', (string) $partner->id)->get();

        $activeCount = 0;
        $expiringSoonCount = 0;
        $expiredCount = 0;
        $thresholdDate = now()->addDays($warningWindowDays)->toDateString();
        $today = now()->toDateString();

        foreach ($properties as $ip) {
            $expiry = $ip->expires_at?->toDateString();

            if ($expiry !== null && $expiry < $today) {
                $expiredCount++;
                $ip->update(['status' => 'expired']);
            } elseif ($expiry !== null && $expiry <= $thresholdDate) {
                $expiringSoonCount++;
                $activeCount++;
                $ip->update(['status' => 'expiring_soon']);
            } else {
                $activeCount++;
                $ip->update(['status' => 'active']);
            }
        }

        $complianceStatus = ($expiredCount > 0)
            ? 'non_compliant_expired_licenses'
            : (($expiringSoonCount > 0) ? 'warning_renewal_required' : 'compliant');

        return [
            'total_ip' => $properties->count(),
            'active_ip' => $activeCount,
            'expiring_soon_count' => $expiringSoonCount,
            'expired_count' => $expiredCount,
            'compliance_status' => $complianceStatus,
        ];
    }
}

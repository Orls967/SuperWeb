<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Hcm\Domain\Models\Gig\GovBounty;
use Modules\Hcm\Domain\Models\Gig\GovBountyClaim;

class HcmGigBountyT1Seeder extends Seeder
{
    /**
     * Seed scale T1: 200 bounties and 50 claims.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $costCenters = ['WMS_CIKARANG_01', 'RESTO_CK01', 'MALL_DUTA_EVENT', 'LOGISTICS_FLEET', 'GEN'];
            $certs = [null, 'K3_LOGISTICS', 'FOOD_SAFETY_HACCP', 'HEAVY_EQUIPMENT'];

            $baseDate = Carbon::parse('2026-11-01 08:00:00');

            for ($i = 1; $i <= 200; $i++) {
                $code = sprintf('BNT-T1-%04d', $i);
                $cc = $costCenters[$i % count($costCenters)];
                $cert = $certs[$i % count($certs)];
                $start = $baseDate->copy()->addHours($i * 2);
                $end = $start->copy()->addHours(4);

                $bounty = GovBounty::firstOrCreate(
                    ['bounty_code' => $code],
                    [
                        'business_unit_cost_center' => $cc,
                        'title' => "Bounty T1 Scale Task #{$i}",
                        'description' => "Automated scale T1 task description for bounty {$i}",
                        'shift_start' => $start,
                        'shift_end' => $end,
                        'duration_hours' => 4.0,
                        'hourly_rate_idr' => 75_000 + (($i % 5) * 5_000),
                        'required_certification' => $cert,
                        'requires_high_risk_permit' => ($i % 10 === 0),
                        'embedded_insurance_policy_ref' => "INS-T1-{$code}",
                        'speed_bonus_idr' => ($i % 4 === 0) ? 25_000 : 0,
                        'unit_rating_score' => 4.85,
                        'status' => ($i <= 50) ? 'CLAIMED' : 'OPEN',
                    ]
                );

                if ($i <= 50) {
                    $claimCode = sprintf('CLM-T1-%04d', $i);
                    GovBountyClaim::firstOrCreate(
                        ['claim_code' => $claimCode],
                        [
                            'bounty_id' => $bounty->id,
                            'employee_id' => 1000 + $i,
                            'work_permit_code' => ($bounty->requires_high_risk_permit) ? "PERMIT-{$code}" : null,
                            'mobility_points_earned' => 10,
                            'claimed_at' => $start->copy()->subHours(12),
                            'status' => 'ASSIGNED',
                        ]
                    );
                }
            }
        });
    }
}

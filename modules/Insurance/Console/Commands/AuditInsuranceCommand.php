<?php

declare(strict_types=1);

namespace Modules\Insurance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Insurance\Application\Services\FullInsuranceService;
use Modules\Insurance\Application\Services\InsurTechClaimsService;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;

class AuditInsuranceCommand extends Command
{
    protected $signature = 'ins:audit';

    protected $description = 'Perform two-source verification of InsurTech products, policies, full insurance actuarial reserves, and claim payouts';

    public function handle(InsurTechClaimsService $service, FullInsuranceService $fullService): int
    {
        $this->info('Starting InsurTech & Claims Autopilot Operations Audit...');

        $result = $service->auditInsurance();
        $discrepancies = (int) $result['discrepancies'];

        // Audit Lini 18 Asuransi Penuh (Fase 156)
        $fullPolicies = InsurancePolicyPenuh::with('product')->get();
        foreach ($fullPolicies as $policy) {
            // Verifikasi jumlah ceded + retained = gross
            if (($policy->ceded_premium_idr + $policy->retained_premium_idr) !== $policy->gross_premium_idr) {
                $discrepancies++;
            }
        }

        $fullClaims = InsuranceClaimPenuh::all();
        foreach ($fullClaims as $claim) {
            // Verifikasi net payout + subrogation + reinsurance = approved
            if ($claim->status === 'paid') {
                $sum = $claim->net_payout_idr + $claim->subrogation_recovered_idr + $claim->reinsurance_recovered_idr;
                if ($sum !== $claim->approved_amount_idr) {
                    $discrepancies++;
                }
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY'],
                ['Products Checked', $result['product_count']],
                ['Policies Checked', $result['policy_count'] + $fullPolicies->count()],
                ['Claims Checked', $result['claim_count'] + $fullClaims->count()],
                ['Discrepancies', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Insurance audit FAILED with {$discrepancies} discrepancies found.");

            return 1;
        }

        $this->info('Insurance audit PASSED cleanly.');

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Application\Services\MedicalUnderwritingEngine;
use Modules\Insurance\Application\Services\WellnessVaultPrivacyService;
use Modules\Insurance\Domain\Models\LifePolicy;

final class UnderwriteMedicalApplicationAction
{
    public function __construct(
        private readonly MedicalUnderwritingEngine $engine,
        private readonly WellnessVaultPrivacyService $vault,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Mengevaluasi aplikasi asuransi jiwa/kesehatan dengan data kuisioner medis tertanggung.
     *
     * @param  array{
     *     height_cm: int,
     *     weight_kg: int,
     *     systolic_bp: int,
     *     diastolic_bp: int,
     *     is_smoker: bool,
     *     has_diabetes: bool,
     *     has_hypertension: bool,
     *     has_heart_disease: bool,
     *     family_cancer_history: bool
     * }  $medicalData
     * @return array{
     *     policy_number: string,
     *     decision: string,
     *     extra_mortality_loading_bps: int,
     *     adjusted_annual_premium_idr: int,
     *     vault_token: string,
     *     underwriting_notes: string
     * }
     */
    public function execute(
        string $policyNumber,
        array $medicalData,
        string $underwriterId,
        string $idempotencyKey,
    ): array {
        $policy = LifePolicy::where('policy_number', $policyNumber)->firstOrFail();

        return $this->guard->run(
            key: $idempotencyKey,
            scope: 'ins:uw:med:'.$policyNumber,
            payload: [
                'policy_number' => $policyNumber,
                'underwriter_id' => $underwriterId,
            ],
            actorId: is_numeric($underwriterId) ? (int) $underwriterId : null,
            operation: function () use ($policy, $policyNumber, $medicalData) {
                return DB::transaction(function () use ($policy, $policyNumber, $medicalData) {
                    $evaluation = $this->engine->evaluate($medicalData);
                    $vaultToken = $this->vault->encryptPayload($medicalData);

                    $loadingBps = $evaluation['extra_mortality_loading_bps'];
                    $basePremium = $policy->annual_premium_idr;

                    // Loading rate ditambahkan ke premi dasar
                    $extraPremium = (int) intdiv($basePremium * $loadingBps, 10000);
                    $adjustedPremium = $basePremium + $extraPremium;

                    if ($evaluation['decision'] === 'DECLINE') {
                        $policy->update(['status' => 'lapsed']);
                    } else {
                        $policy->update(['annual_premium_idr' => $adjustedPremium]);
                    }

                    return [
                        'policy_number' => $policyNumber,
                        'decision' => $evaluation['decision'],
                        'extra_mortality_loading_bps' => $loadingBps,
                        'adjusted_annual_premium_idr' => $adjustedPremium,
                        'vault_token' => $vaultToken,
                        'underwriting_notes' => $evaluation['underwriting_notes'],
                    ];
                });
            }
        );
    }
}

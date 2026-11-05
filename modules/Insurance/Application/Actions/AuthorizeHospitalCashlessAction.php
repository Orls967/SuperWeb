<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Hospital\Contracts\EncounterBillReader;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\GuaranteeLetter;
use Modules\Insurance\Domain\Models\HealthPolicy;

final class AuthorizeHospitalCashlessAction
{
    public function __construct(
        private readonly EncounterBillReader $encounterReader,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    public function execute(
        string $policyNumber,
        string $hospitalEncounterRef,
        string $actorId,
        string $idempotencyKey,
    ): GuaranteeLetter {
        $policy = HealthPolicy::where('policy_number', $policyNumber)->firstOrFail();
        if ($policy->status !== 'active') {
            throw new InvalidArgumentException("Polis kesehatan {$policyNumber} tidak aktif.");
        }

        // Baca tagihan encounter via kontrak G7-K4 EncounterBillReader
        $bill = $this->encounterReader->readEncounterBill($hospitalEncounterRef);
        if (! $bill['is_eligible_for_cashless']) {
            throw new InvalidArgumentException("Encounter RS {$hospitalEncounterRef} tidak memenuhi syarat cashless admission.");
        }

        $remainingAnnualLimit = max(0, $policy->annual_limit_idr - $policy->annual_utilized_idr);
        if ($remainingAnnualLimit <= 0) {
            throw new InvalidArgumentException("Batas limit tahunan polis {$policyNumber} telah habis.");
        }

        $approvedCeiling = min($bill['total_billed_idr'], $remainingAnnualLimit);

        return $this->guard->run(
            key: $idempotencyKey,
            scope: 'ins:cashless:gl:'.$hospitalEncounterRef,
            payload: [
                'policy_number' => $policyNumber,
                'encounter_ref' => $hospitalEncounterRef,
                'ceiling' => $approvedCeiling,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use ($policy, $hospitalEncounterRef, $bill, $approvedCeiling) {
                return DB::transaction(function () use ($policy, $hospitalEncounterRef, $bill, $approvedCeiling) {
                    $glNumber = sprintf('GL-%s-%04d', date('Ymd'), rand(1000, 9999));
                    $now = CarbonImmutable::now();

                    return GuaranteeLetter::create([
                        'gl_number' => $glNumber,
                        'health_policy_id' => $policy->id,
                        'hospital_encounter_ref' => $hospitalEncounterRef,
                        'hospital_party_id' => $bill['hospital_party_id'],
                        'diagnosis_icd10' => $bill['diagnosis_icd10'],
                        'approved_ceiling_idr' => $approvedCeiling,
                        'status' => 'issued',
                        'issued_at' => $now,
                        'valid_until' => $now->addDays(7),
                    ]);
                });
            }
        );
    }
}

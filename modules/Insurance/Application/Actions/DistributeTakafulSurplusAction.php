<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\TakafulFund;
use Modules\Insurance\Domain\Models\TakafulSurplusDistribution;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;
use Modules\Shared\Domain\Algorithms\Reinsurance\TakafulSurplus;
use Modules\Shared\Domain\Algorithms\Reinsurance\TakafulSurplusCertificateVerifier;

final class DistributeTakafulSurplusAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly TakafulSurplus $calculator,
        private readonly TakafulSurplusCertificateVerifier $verifier,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Menghitung dan membagikan surplus underwriting dana Tabarru' (Fase 160.1).
     */
    public function execute(
        string $fundCode,
        int $fiscalYear,
        int $technicalReserveAdditionIdr,
        int $participantShareBps = 5000,
        int $contingencyReserveBps = 3000,
        int $operatorMudharabahBps = 2000,
        string $actorId = '1',
        string $idempotencyKey = '',
    ): TakafulSurplusDistribution {
        $fund = TakafulFund::where('fund_code', $fundCode)->firstOrFail();
        $key = $idempotencyKey !== '' ? $idempotencyKey : ('tk:surplus:'.$fundCode.':'.$fiscalYear);

        return $this->guard->run(
            key: $key,
            scope: 'ins:takaful:surplus:'.$fundCode.':'.$fiscalYear,
            payload: [
                'fund_code' => $fundCode,
                'fiscal_year' => $fiscalYear,
                'reserve_addition' => $technicalReserveAdditionIdr,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use (
                $fund,
                $fiscalYear,
                $technicalReserveAdditionIdr,
                $participantShareBps,
                $contingencyReserveBps,
                $operatorMudharabahBps
            ) {
                return DB::transaction(function () use (
                    $fund,
                    $fiscalYear,
                    $technicalReserveAdditionIdr,
                    $participantShareBps,
                    $contingencyReserveBps,
                    $operatorMudharabahBps
                ) {
                    $netContributions = (int) $fund->total_contributions_idr;
                    $netClaims = (int) $fund->total_claims_paid_idr;

                    // Hitung surplus via Algoritma Bersertifikat A8
                    $result = $this->calculator->calculate(
                        netContributionIdr: $netContributions,
                        netIncurredClaimsIdr: $netClaims,
                        technicalReserveIdr: $technicalReserveAdditionIdr,
                        participantShareBps: $participantShareBps,
                        contingencyReserveBps: $contingencyReserveBps,
                        operatorMudharabahBps: $operatorMudharabahBps,
                    );

                    // Verifikasi integritas sertifikat
                    $this->verifier->verify($result);

                    $distribution = TakafulSurplusDistribution::create([
                        'fund_id' => $fund->id,
                        'fiscal_year' => $fiscalYear,
                        'net_contributions_idr' => $netContributions,
                        'net_claims_idr' => $netClaims,
                        'technical_reserve_idr' => $technicalReserveAdditionIdr,
                        'gross_surplus_idr' => $result->grossSurplusIdr,
                        'participant_share_idr' => $result->participantSurplusShareIdr,
                        'contingency_reserve_idr' => $result->contingencyReserveShareIdr,
                        'mudharabah_operator_idr' => $result->mudharabahOperatorShareIdr,
                        'calculation_certificate' => $result->certificate,
                        'distributed_at' => CarbonImmutable::now(),
                    ]);

                    // Update cadangan kontinjensi fund
                    $fund->increment('contingency_reserve_idr', $result->contingencyReserveShareIdr);

                    if ($result->grossSurplusIdr > 0) {
                        $lines = Posting::lines();
                        $lines->debit('ins:claim_expense:IDR', $result->grossSurplusIdr);
                        $lines->credit('ins:claim_clearing:IDR', $result->grossSurplusIdr);

                        $this->ledger->post(new PostingDTO(
                            type: InsuranceTransactionTypes::INSURANCE_CLAIM_PAYOUT->value,
                            description: "Takaful Surplus Distribution FY{$fiscalYear} for Fund {$fund->fund_code}",
                            idempotencyKey: 'tk:surplus:post:'.$fund->fund_code.':'.$fiscalYear,
                            entries: $lines->toEntries('IDR'),
                            referenceType: 'ins_tk_fund',
                            referenceId: $fund->fund_code,
                        ));
                    }

                    return $distribution;
                });
            }
        );
    }
}

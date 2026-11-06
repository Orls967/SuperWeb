<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\EscoContract;
use Modules\Egy\Domain\Models\EscoSettlement;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Algorithms\Utility\EscoMeasurementVerification;

final class SettleEscoPerformanceContractAction extends BaseAction
{
    public function __construct(
        private readonly EscoMeasurementVerification $escoVerification,
        private readonly LedgerService $ledgerService
    ) {}

    public function execute(
        int $contractId,
        string $periodMonth,
        int $cddFactorBps,
        int $actualKwh,
        string $idempotencyKey
    ): EscoSettlement {
        if ($contractId <= 0 || $actualKwh < 0) {
            throw new InvalidArgumentException('ID Kontrak dan actual kWh tidak valid');
        }

        return DB::transaction(function () use (
            $contractId,
            $periodMonth,
            $cddFactorBps,
            $actualKwh,
            $idempotencyKey
        ) {
            $contract = EscoContract::findOrFail($contractId);

            $existing = EscoSettlement::where('esco_contract_id', $contract->id)
                ->where('period_month', $periodMonth)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // Hitung penghematan energi & bagi hasil bersertifikat E6
            $res = $this->escoVerification->calculate(
                baselineKwh: $contract->baseline_monthly_kwh,
                cddFactorBps: $cddFactorBps,
                actualKwh: $actualKwh,
                rateIdrPerKwh: $contract->baseline_tariff_idr,
                escoShareBps: $contract->esco_share_bps
            );

            $settlement = EscoSettlement::create([
                'esco_contract_id' => $contract->id,
                'period_month' => $periodMonth,
                'baseline_kwh' => $res->baselineKwh,
                'cdd_factor_bps' => $res->cddFactorBps,
                'adjusted_baseline_kwh' => $res->adjustedBaselineKwh,
                'actual_kwh' => $res->actualKwh,
                'gross_savings_kwh' => $res->grossSavingsKwh,
                'rate_idr_per_kwh' => $res->rateIdrPerKwh,
                'total_savings_idr' => $res->totalSavingsIdr,
                'esco_share_bps' => $res->escoShareBps,
                'esco_share_idr' => $res->escoSavingsShareIdr,
                'client_share_idr' => $res->clientSavingsShareIdr,
                'status' => 'settled',
            ]);

            // Posting Ledger ESCO Service Split (D127-6)
            $clientArAccount = EgyAccounts::getOrCreateEscoClientAr((string) $contract->client_party_id);
            EgyAccounts::ensureAccount('egy:esco_service_revenue:IDR', 'ESCO Service Revenue', AccountKind::REVENUE, 'IDR', false);

            $postingLines = Posting::lines()
                ->debit($clientArAccount->code, $res->escoSavingsShareIdr)
                ->credit('egy:esco_service_revenue:IDR', $res->escoSavingsShareIdr);

            $tx = $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                description: "ESCO performance split settlement {$contract->contract_number} ({$periodMonth})",
                idempotencyKey: "POST-{$idempotencyKey}",
                entries: $postingLines->toEntries('IDR'),
                referenceType: 'ESCO_SETTLEMENT',
                referenceId: (string) $settlement->id
            ));

            $settlement->update(['ledger_transaction_id' => (int) $tx->id]);

            return $settlement;
        });
    }
}

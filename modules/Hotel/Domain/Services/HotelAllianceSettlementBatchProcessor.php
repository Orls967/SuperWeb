<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\HtlAlliancePartner;
use Modules\Hotel\Domain\Models\HtlAllianceTransferLog;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class HotelAllianceSettlementBatchProcessor
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     batch_id: string,
     *     partner_code: string,
     *     settlement_currency: string,
     *     total_transfers: int,
     *     total_points_debited: int,
     *     total_partner_units: int,
     *     gross_payable_idr: int,
     *     total_fee_idr: int,
     *     net_settlement_idr: int,
     *     settled_at: string
     * }
     */
    public function executePartnerSettlementBatch(
        string $partnerCode,
        string $settlementCurrency = 'IDR'
    ): array {
        return DB::transaction(function () use ($partnerCode, $settlementCurrency) {
            $partner = HtlAlliancePartner::where('partner_code', $partnerCode)->first();
            if (! $partner) {
                throw new RuntimeException("Mitra aliansi dengan kode '{$partnerCode}' tidak ditemukan.");
            }

            // Find all pending SUCCESS transfer logs that haven't been netted into a batch
            $pendingLogs = HtlAllianceTransferLog::where('partner_code', $partnerCode)
                ->where('status', 'SUCCESS')
                ->whereNull('settlement_batch_id')
                ->lockForUpdate()
                ->get();

            if ($pendingLogs->isEmpty()) {
                throw new RuntimeException("Tidak ada mutasi transfer pending yang membutuhkan netting settlement untuk '{$partnerCode}'.");
            }

            $batchId = 'SETTLE-'.now()->format('Ymd').'-'.$partnerCode.'-'.bin2hex(random_bytes(3));
            $totalPoints = (int) $pendingLogs->sum('points_debited');
            $totalUnits = (int) $pendingLogs->sum('partner_units_credited');
            $totalFee = (int) $pendingLogs->sum('transfer_fee_idr');
            $netPayable = (int) $pendingLogs->sum('net_payable_idr');
            $grossPayable = $netPayable + $totalFee;

            // Mark logs as settled with batch ID
            foreach ($pendingLogs as $log) {
                $log->update([
                    'settlement_batch_id' => $batchId,
                    'settled_at' => now(),
                ]);
            }

            // Ledger clearing: Debit Partner Points Payable, Credit External Bank Clearing
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::HTL_ALLIANCE_PTS_TRANSFER->value,
                description: "Netting settlement batch {$batchId} for {$partnerCode}: {$totalUnits} units, Net IDR ".number_format($netPayable),
                idempotencyKey: "HTL-ALN-SETTLE-{$batchId}",
                entries: Posting::lines()
                    ->debit('htl:partner_points_payable:IDR', $netPayable)
                    ->credit('clearing:external:IDR', $netPayable)
                    ->toEntries('IDR'),
                referenceType: 'ALLIANCE_BATCH_SETTLEMENT',
                referenceId: $batchId,
            ));

            return [
                'batch_id' => $batchId,
                'partner_code' => $partnerCode,
                'settlement_currency' => $settlementCurrency,
                'total_transfers' => $pendingLogs->count(),
                'total_points_debited' => $totalPoints,
                'total_partner_units' => $totalUnits,
                'gross_payable_idr' => $grossPayable,
                'total_fee_idr' => $totalFee,
                'net_settlement_idr' => $netPayable,
                'settled_at' => now()->toIso8601String(),
            ];
        });
    }
}

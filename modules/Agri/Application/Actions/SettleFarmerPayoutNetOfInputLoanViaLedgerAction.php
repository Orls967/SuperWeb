<?php

declare(strict_types=1);

namespace Modules\Agri\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Agri\Domain\Models\AgriCollectionBatch;
use Modules\Agri\Domain\Models\AgriContract;
use Modules\Agri\Ledger\AgriTransactionTypes;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;

class SettleFarmerPayoutNetOfInputLoanViaLedgerAction
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * @param array{
     *     contract_id: string,
     *     collection_center_id: string,
     *     gross_weight_kg: float|int,
     *     grade?: string,
     *     moisture_percentage?: float|int,
     *     destination_unit?: string,
     *     batch_number?: string,
     *     received_date?: string
     * } $data
     */
    public function execute(array $data): AgriCollectionBatch
    {
        return DB::transaction(function () use ($data) {
            $contract = AgriContract::where('id', $data['contract_id'])->lockForUpdate()->firstOrFail();

            $grossWeight = (float) ($data['gross_weight_kg'] ?? 0);
            if ($grossWeight <= 0) {
                throw new InvalidArgumentException('Gross harvest weight must be greater than zero kg.');
            }

            $grade = $data['grade'] ?? 'GRADE_A';
            $multiplier = match ($grade) {
                'GRADE_A' => 1.00,
                'GRADE_B' => 0.90,
                'GRADE_C' => 0.80,
                default => 1.00,
            };

            $floorPrice = $contract->guaranteed_floor_price_idr_per_kg;
            $buyingPricePerKg = (int) round($floorPrice * $multiplier);
            $grossPayout = (int) round($grossWeight * $buyingPricePerKg);

            // Calculate advance loan deduction
            $advanceDeduction = 0;
            if ($contract->total_advance_deductible_idr > 0) {
                $advanceDeduction = min($grossPayout, $contract->total_advance_deductible_idr);
                $contract->decrement('total_advance_deductible_idr', $advanceDeduction);
            }

            $netPayout = $grossPayout - $advanceDeduction;

            $batchNumber = $data['batch_number'] ?? 'BATCH-AGR-'.strtoupper(Str::random(8));

            $batch = AgriCollectionBatch::create([
                'batch_number' => $batchNumber,
                'collection_center_id' => $data['collection_center_id'],
                'contract_id' => $contract->id,
                'received_date' => $data['received_date'] ?? now()->toDateString(),
                'gross_weight_kg' => $grossWeight,
                'grade' => $grade,
                'moisture_percentage' => (float) ($data['moisture_percentage'] ?? 14.0),
                'buying_price_per_kg' => $buyingPricePerKg,
                'gross_payout_idr' => $grossPayout,
                'advance_deduction_idr' => $advanceDeduction,
                'net_payout_idr' => $netPayout,
                'payment_status' => 'paid',
                'destination_unit' => $data['destination_unit'] ?? 'RESTO_CK01',
            ]);

            // Post ledger entry for commodity reception and net farmer payout
            if ($grossPayout > 0) {
                $lines = Posting::lines()->debit('inventory:agri:raw_commodities:IDR', $grossPayout);
                if ($advanceDeduction > 0) {
                    $lines->credit('agri:farmer_advance_receivable:IDR', $advanceDeduction);
                }
                if ($netPayout > 0) {
                    $lines->credit('ap:agri:farmer_payable:IDR', $netPayout);
                }

                $this->ledgerService->post(new PostingDTO(
                    type: AgriTransactionTypes::AGRI_HARVEST_PAYOUT->value,
                    description: "Agri harvest reception batch {$batch->batch_number} from contract {$contract->contract_number} net of loan",
                    idempotencyKey: "AGRI-HARVEST-PAYOUT-{$batch->batch_number}",
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'AGRI_COLLECTION_BATCH',
                    referenceId: (string) $batch->id,
                ));
            }

            return $batch;
        });
    }
}

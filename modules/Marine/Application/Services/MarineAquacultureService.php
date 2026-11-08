<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Marine\Domain\Models\MarineAquacultureLedgerEntry;
use Modules\Marine\Domain\Models\MarineCycle;
use Modules\Marine\Domain\Models\MarineFeedLog;
use Modules\Marine\Domain\Models\MarineHarvest;
use Modules\Marine\Domain\Models\MarinePayout;
use Modules\Marine\Domain\Models\MarinePond;
use Modules\Marine\Ledger\MarineAccounts;
use Modules\Marine\Ledger\MarineTransactionTypes;
use RuntimeException;

class MarineAquacultureService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * D22-2: Register a new pond/cage.
     */
    public function createPond(
        string $code,
        string $name,
        int $farmerId,
        int $capacityUnits,
        string $location
    ): MarinePond {
        return MarinePond::create([
            'code' => $code,
            'name' => $name,
            'farmer_id' => $farmerId,
            'capacity_units' => $capacityUnits,
            'location' => $location,
        ]);
    }

    /**
     * D22-3 & D22-4: Start a new culture cycle with advance disbursement to farmer.
     */
    public function startCycle(
        string $cycleNumber,
        int $pondId,
        int $farmerId,
        string $species,
        int $stockedCount,
        int $initialWeightGramsPerFish,
        int $advanceAmount = 0
    ): MarineCycle {
        return DB::transaction(function () use (
            $cycleNumber,
            $pondId,
            $farmerId,
            $species,
            $stockedCount,
            $initialWeightGramsPerFish,
            $advanceAmount
        ) {
            $cycle = MarineCycle::create([
                'cycle_number' => $cycleNumber,
                'pond_id' => $pondId,
                'farmer_id' => $farmerId,
                'species' => $species,
                'stocked_count' => $stockedCount,
                'initial_weight_grams_per_fish' => $initialWeightGramsPerFish,
                'advance_amount' => $advanceAmount,
                'status' => 'active',
                'started_at' => CarbonImmutable::now(),
            ]);

            if ($advanceAmount > 0 && $this->ledgerService) {
                // Post advance disbursement: Debit farmer advance receivable, Credit payout clearing
                $this->ledgerService->post(new PostingDTO(
                    type: MarineTransactionTypes::MARINE_ADVANCE_DISBURSEMENT->value,
                    description: "Pencairan uang muka budidaya siklus {$cycleNumber}",
                    idempotencyKey: "MAR-ADV-{$cycleNumber}",
                    entries: Posting::lines()
                        ->debit(MarineAccounts::ADVANCE_RECEIVABLE, $advanceAmount)
                        ->credit(MarineAccounts::SETTLEMENT_PAYOUT_CLEARING, $advanceAmount)
                        ->toEntries('IDR'),
                    referenceType: 'MARINE_CYCLE',
                    referenceId: (string) $cycle->id,
                ));
            }

            return $cycle;
        });
    }

    /**
     * D22-3 & D22-4: Record feed log and disburse input loan.
     */
    public function logFeed(
        int $cycleId,
        string $feedDate,
        int $quantityKg,
        int $costPerKg
    ): MarineFeedLog {
        return DB::transaction(function () use ($cycleId, $feedDate, $quantityKg, $costPerKg) {
            $cycle = MarineCycle::findOrFail($cycleId);
            $totalCost = $quantityKg * $costPerKg;

            $feedLog = MarineFeedLog::create([
                'cycle_id' => $cycle->id,
                'feed_date' => $feedDate,
                'quantity_kg' => $quantityKg,
                'cost_per_kg' => $costPerKg,
                'total_cost' => $totalCost,
                'loan_disbursed' => true,
            ]);

            if ($totalCost > 0 && $this->ledgerService) {
                // Post input loan: Debit farmer input loan receivable, Credit feed clearing
                $this->ledgerService->post(new PostingDTO(
                    type: MarineTransactionTypes::MARINE_FEED_LOAN->value,
                    description: "Pinjaman pakan input siklus {$cycle->cycle_number} log #{$feedLog->id}",
                    idempotencyKey: "MAR-FEED-{$cycle->cycle_number}-{$feedLog->id}",
                    entries: Posting::lines()
                        ->debit(MarineAccounts::INPUT_LOAN_RECEIVABLE, $totalCost)
                        ->credit(MarineAccounts::FEED_ISSUED_CLEARING, $totalCost)
                        ->toEntries('IDR'),
                    referenceType: 'MARINE_FEED_LOG',
                    referenceId: (string) $feedLog->id,
                ));
            }

            return $feedLog;
        });
    }

    /**
     * D22-3: Record harvest with exact rational FCR and graded pricing.
     */
    public function recordHarvest(
        int $cycleId,
        string $harvestNumber,
        string $harvestDate,
        int $harvestedCount,
        int $avgWeightGrams,
        int $gradeAKg,
        int $gradeAPrice,
        int $gradeBKg,
        int $gradeBPrice,
        int $gradeCKg,
        int $gradeCPrice
    ): MarineHarvest {
        return DB::transaction(function () use (
            $cycleId,
            $harvestNumber,
            $harvestDate,
            $harvestedCount,
            $avgWeightGrams,
            $gradeAKg,
            $gradeAPrice,
            $gradeBKg,
            $gradeBPrice,
            $gradeCKg,
            $gradeCPrice
        ) {
            $cycle = MarineCycle::where('id', $cycleId)->lockForUpdate()->firstOrFail();

            if ($cycle->status === 'harvested' || $cycle->status === 'settled') {
                throw new RuntimeException("Siklus {$cycle->cycle_number} sudah dipanen.");
            }

            $totalWeightKg = intdiv($harvestedCount * $avgWeightGrams, 1000);
            $initialBiomassKg = intdiv($cycle->stocked_count * $cycle->initial_weight_grams_per_fish, 1000);
            $totalFeedKg = (int) $cycle->feedLogs()->sum('quantity_kg');

            // FCR = Total Feed Kg / (Total Harvest Kg - Initial Biomass Kg)
            $netBiomassGainKg = $totalWeightKg - $initialBiomassKg;
            if ($netBiomassGainKg <= 0) {
                $fcrScaled = 0;
            } else {
                // Skala 1000 untuk 3 tempat desimal eksak tanpa float: (Feed * 1000) / Gain
                $fcrScaled = (int) round(($totalFeedKg * 1000) / $netBiomassGainKg);
            }

            $grossAmount = ($gradeAKg * $gradeAPrice) + ($gradeBKg * $gradeBPrice) + ($gradeCKg * $gradeCPrice);

            $harvest = MarineHarvest::create([
                'harvest_number' => $harvestNumber,
                'cycle_id' => $cycle->id,
                'harvest_date' => $harvestDate,
                'harvested_count' => $harvestedCount,
                'avg_weight_grams' => $avgWeightGrams,
                'total_weight_kg' => $totalWeightKg,
                'grade_a_kg' => $gradeAKg,
                'grade_a_price' => $gradeAPrice,
                'grade_b_kg' => $gradeBKg,
                'grade_b_price' => $gradeBPrice,
                'grade_c_kg' => $gradeCKg,
                'grade_c_price' => $gradeCPrice,
                'gross_amount' => $grossAmount,
                'fcr_scaled' => $fcrScaled,
            ]);

            // Post inventory reception: Debit harvest inventory, Credit AP farmer payable
            if ($grossAmount > 0 && $this->ledgerService) {
                $this->ledgerService->post(new PostingDTO(
                    type: MarineTransactionTypes::MARINE_HARVEST_RECEPTION->value,
                    description: "Penerimaan hasil panen bruto siklus {$cycle->cycle_number}",
                    idempotencyKey: "MAR-HARV-{$harvestNumber}",
                    entries: Posting::lines()
                        ->debit(MarineAccounts::HARVEST_INVENTORY, $grossAmount)
                        ->credit(MarineAccounts::HARVEST_PAYABLE, $grossAmount)
                        ->toEntries('IDR'),
                    referenceType: 'MARINE_HARVEST',
                    referenceId: (string) $harvest->id,
                ));
            }

            $cycle->update(['status' => 'harvested']);

            return $harvest;
        });
    }

    /**
     * D22-4: Settle farmer payout with double-entry net settlement.
     */
    public function settlePayout(int $cycleId, string $idempotencyKey): MarinePayout
    {
        return DB::transaction(function () use ($cycleId, $idempotencyKey) {
            $existing = MarinePayout::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $cycle = MarineCycle::where('id', $cycleId)->lockForUpdate()->firstOrFail();
            $harvest = $cycle->harvest;
            if (! $harvest) {
                throw new RuntimeException("Siklus {$cycle->cycle_number} belum memiliki catatan panen.");
            }

            if ($cycle->status === 'settled') {
                $payout = $cycle->payout;
                if ($payout) {
                    return $payout;
                }
            }

            $grossAmount = $harvest->gross_amount;
            $advanceDeduction = $cycle->advance_amount;
            $feedLoanDeduction = (int) $cycle->feedLogs()->sum('total_cost');

            $netPayout = $grossAmount - $advanceDeduction - $feedLoanDeduction;
            if ($netPayout < 0) {
                throw new RuntimeException('Nilai panen tidak mencukupi untuk melunasi uang muka dan pakan.');
            }

            $payout = MarinePayout::create([
                'cycle_id' => $cycle->id,
                'farmer_id' => $cycle->farmer_id,
                'gross_amount' => $grossAmount,
                'advance_deduction' => $advanceDeduction,
                'feed_loan_deduction' => $feedLoanDeduction,
                'net_payout' => $netPayout,
                'settled_at' => CarbonImmutable::now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            // Double entry ledger settlement:
            // Debit: ap:mar:farmer_payable:IDR (grossAmount)
            // Kredit: mar:farmer_advance_receivable:IDR (advanceDeduction)
            // Kredit: mar:farmer_input_loan_receivable:IDR (feedLoanDeduction)
            // Kredit: mar:payout_clearing:IDR (netPayout)
            // Sum(Debit) == Sum(Credit) == grossAmount
            if ($this->ledgerService) {
                $lines = Posting::lines()->debit(MarineAccounts::HARVEST_PAYABLE, $grossAmount);

                if ($advanceDeduction > 0) {
                    $lines->credit(MarineAccounts::ADVANCE_RECEIVABLE, $advanceDeduction);
                }

                if ($feedLoanDeduction > 0) {
                    $lines->credit(MarineAccounts::INPUT_LOAN_RECEIVABLE, $feedLoanDeduction);
                }

                if ($netPayout > 0) {
                    $lines->credit(MarineAccounts::SETTLEMENT_PAYOUT_CLEARING, $netPayout);
                }

                $this->ledgerService->post(new PostingDTO(
                    type: MarineTransactionTypes::MARINE_PAYOUT_SETTLEMENT->value,
                    description: "Penyelesaian net payout panen pembudidaya siklus {$cycle->cycle_number}",
                    idempotencyKey: "MAR-SETTLE-{$cycle->cycle_number}",
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'MARINE_PAYOUT',
                    referenceId: (string) $payout->id,
                ));
            }

            $cycle->update(['status' => 'settled']);

            return $payout;
        });
    }

    /**
     * D22-6: Audit reconciles subledger payouts against ledger records.
     *
     * @return array{status: string, discrepancy_count: int, total_payouts: int, total_net_amount: int}
     */
    public function auditMarine(): array
    {
        $payouts = MarinePayout::all();
        $discrepancyCount = 0;
        $totalNet = 0;

        foreach ($payouts as $payout) {
            $totalNet += $payout->net_payout;

            // Cross check with harvest gross
            $cycle = $payout->cycle;
            if (! $cycle || ! $cycle->harvest) {
                $discrepancyCount++;

                continue;
            }

            $expectedNet = $cycle->harvest->gross_amount - $payout->advance_deduction - $payout->feed_loan_deduction;
            if ($payout->net_payout !== $expectedNet) {
                $discrepancyCount++;
            }
        }

        // Fase 171: Verifikasi integritas kriptografis ledger rantai pasok akuakultur
        if (class_exists(MarineAquacultureLedgerEntry::class)) {
            $ledgerIntegrity = app(MarineAquacultureLedgerService::class)->verifyLedgerChainIntegrity();
            if (! $ledgerIntegrity['is_intact']) {
                $discrepancyCount++;
            }
        }

        return [
            'status' => $discrepancyCount === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'discrepancy_count' => $discrepancyCount,
            'total_payouts' => $payouts->count(),
            'total_net_amount' => $totalNet,
        ];
    }
}

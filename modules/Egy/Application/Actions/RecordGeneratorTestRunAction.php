<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Application\Services\CarbonAccountingAndGhgReportService;
use Modules\Egy\Domain\Models\GeneratorLog;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;

/**
 * Aksi Pencatatan Pengujian Periodik Genset Darurat dan Emisi Scope 1 BBM Solar.
 */
final class RecordGeneratorTestRunAction
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    public function execute(
        string $generatorAssetCode,
        string $siteName,
        string $runType,
        Carbon $startTime,
        int $durationMinutes,
        int $fuelConsumedLiters,
        int $loadPercentageBps,
        int $fuelPriceIdrPerLiter = 15000,
        ?string $notes = null
    ): GeneratorLog {
        if ($durationMinutes <= 0 || $fuelConsumedLiters < 0) {
            throw new InvalidArgumentException('Durasi harus > 0 menit dan konsumsi BBM tidak boleh negatif.');
        }

        // Kriteria kelulusan pengujian (load test harus mencapai minimal 30% / 3000 bps)
        $isPassed = true;
        if ($runType === 'weekly_load_test' && $loadPercentageBps < 3000) {
            $isPassed = false;
        }

        // Hitung emisi Scope 1: 2680 gram CO2e / liter solar -> kg = (liters * 2680) / 1000
        $emissionsKgCo2e = (int) intdiv(
            $fuelConsumedLiters * CarbonAccountingAndGhgReportService::EMISSION_FACTOR_DIESEL_GRAM_PER_LITER,
            1000
        );

        $endTime = (clone $startTime)->addMinutes($durationMinutes);

        // Jika ada BBM solar yang dikonsumsi, bukukan biaya bahan bakar genset ke ledger
        if ($fuelConsumedLiters > 0 && $fuelPriceIdrPerLiter > 0) {
            $totalFuelCostIdr = $fuelConsumedLiters * $fuelPriceIdrPerLiter;
            $fuelExpenseAccount = 'egy:genset_fuel_expense:IDR';
            $fuelSupplierAccount = EgyAccounts::getOrCreateFuelSupplierAp('PERTAMINA');

            $lines = Posting::lines()
                ->debit($fuelExpenseAccount, $totalFuelCostIdr)
                ->credit($fuelSupplierAccount->code, $totalFuelCostIdr);

            $idempotencyKey = sprintf('GENSET-RUN:%s:%s', $generatorAssetCode, $startTime->format('YmdHi'));

            $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                description: sprintf('Biaya BBM Solar Genset %s (%d Liter)', $generatorAssetCode, $fuelConsumedLiters),
                idempotencyKey: $idempotencyKey,
                entries: $lines->toEntries('IDR'),
                referenceType: 'GENSET_RUN',
                referenceId: $generatorAssetCode,
            ));
        }

        return GeneratorLog::create([
            'generator_asset_code' => $generatorAssetCode,
            'site_name' => $siteName,
            'run_type' => $runType,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'fuel_consumed_liters' => $fuelConsumedLiters,
            'load_percentage_bps' => $loadPercentageBps,
            'emissions_kg_co2e' => $emissionsKgCo2e,
            'is_passed' => $isPassed,
            'notes' => $notes,
        ]);
    }
}

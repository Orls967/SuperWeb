<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningFuelDispenseRecord;
use Modules\Mining\Domain\Models\MiningFuelStorage;
use Modules\Mining\Ledger\MiningAccounts;
use Modules\Mining\Ledger\MiningTransactionTypes;

final class DispenseFuelToEquipmentAction
{
    public function __construct(
        private readonly ?LedgerService $ledgerService = null,
    ) {
    }

    /**
     * Pengisian bahan bakar solar ke alat berat dari tangki timbun / fuel truck.
     *
     * @param array{
     *     storage_id: string,
     *     equipment_id: string,
     *     volume_liters: int,
     *     engine_hours: int,
     *     flowmeter_start: string,
     *     flowmeter_end: string,
     *     operator_ref?: string,
     *     fuel_price_per_liter_idr?: int,
     *     idempotency_key: string,
     * } $payload
     * @return MiningFuelDispenseRecord
     */
    public function execute(array $payload): MiningFuelDispenseRecord
    {
        return DB::transaction(function () use ($payload) {
            $storage = MiningFuelStorage::lockForUpdate()->findOrFail($payload['storage_id']);
            $equipment = MiningEquipment::findOrFail($payload['equipment_id']);

            $volumeMl = $payload['volume_liters'] * 1000;

            if ($storage->current_stock_ml < $volumeMl) {
                throw new InvalidArgumentException(
                    "Stok solar tangki {$storage->storage_code} (".($storage->current_stock_ml / 1000)." L) tidak mencukupi untuk pengisian {$payload['volume_liters']} L"
                );
            }

            // Kurangi stok tangki timbun
            $storage->current_stock_ml -= $volumeMl;
            $storage->save();

            $dispenseCode = 'DSP-'.substr(md5($payload['idempotency_key']), 0, 10);

            $record = MiningFuelDispenseRecord::firstOrCreate(
                ['dispense_code' => $dispenseCode],
                [
                    'storage_id' => $storage->id,
                    'equipment_id' => $equipment->id,
                    'volume_ml' => $volumeMl,
                    'equipment_engine_hours' => $payload['engine_hours'],
                    'operator_ref' => $payload['operator_ref'] ?? 'dispatcher',
                    'flowmeter_start_reading' => $payload['flowmeter_start'],
                    'flowmeter_end_reading' => $payload['flowmeter_end'],
                    'dispensed_at' => CarbonImmutable::now(),
                ]
            );

            // Posting ledger pemakaian bahan bakar site bila ledgerService tersedia
            $fuelPriceIdr = $payload['fuel_price_per_liter_idr'] ?? 15000;
            $totalExpenseIdr = $payload['volume_liters'] * $fuelPriceIdr;

            if ($this->ledgerService !== null && $totalExpenseIdr > 0) {
                $posting = Posting::lines()
                    ->debit(MiningAccounts::fuelExpense(), $totalExpenseIdr)
                    ->credit(MiningAccounts::fuelInventory(), $totalExpenseIdr)
                    ->toEntries('IDR');

                $this->ledgerService->post(new PostingDTO(
                    idempotencyKey: "min:fuel:dsp:{$record->id}",
                    transactionType: MiningTransactionTypes::FUEL_EXPENSE,
                    description: "Pemakaian BBM Solar {$payload['volume_liters']} L alat {$equipment->equipment_code}",
                    referenceId: $record->id,
                    entries: $posting,
                ));
            }

            return $record;
        });
    }
}

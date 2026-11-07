<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningFuelDispenseRecord;
use Modules\Mining\Domain\Models\MiningFuelStorage;
use Modules\Mining\Ledger\MiningFase93LedgerPostingService;

/**
 * Pengisian solar dari tangki site ke alat (D93-7): kurangi stok tangki (fakta) dan posting
 * debit min:fuel_expense / kredit min:fuel_inventory. Idempoten: kunci = tangki + alat + pembacaan flowmeter akhir.
 */
final class DispenseFuelToEquipmentAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ClockInterface $clock,
        private readonly ?MiningFase93LedgerPostingService $postingService = null,
    ) {}

    /**
     * @param  array{storage_id: string, equipment_id: string, volume_ml: int, flowmeter_start: string, flowmeter_end: string, unit_price_idr_per_liter: int, operator_ref?: string}  $p
     */
    public function execute(array $p): MiningFuelDispenseRecord
    {
        if ($p['volume_ml'] < 1 || $p['unit_price_idr_per_liter'] < 1) {
            throw new InvalidArgumentException('Volume dan harga solar harus positif.');
        }

        return DB::transaction(function () use ($p) {
            $storage = MiningFuelStorage::lockForUpdate()->findOrFail($p['storage_id']);
            $equipment = MiningEquipment::findOrFail($p['equipment_id']);
            $code = 'DSP-'.hash('sha256', "{$storage->id}|{$equipment->id}|{$p['flowmeter_end']}");

            $existing = MiningFuelDispenseRecord::where('dispense_code', substr($code, 0, 32))->first();
            if ($existing !== null) {
                if ($existing->volume_ml !== $p['volume_ml']) {
                    throw new InvalidArgumentException('Kunci idempotensi sama dengan volume berbeda.');
                }

                return $existing;
            }

            if ($storage->current_stock_ml < $p['volume_ml']) {
                throw new InvalidArgumentException("Stok tangki {$storage->storage_code} ({$storage->current_stock_ml} ml) kurang dari {$p['volume_ml']} ml.");
            }

            $storage->decrement('current_stock_ml', $p['volume_ml']);

            $record = MiningFuelDispenseRecord::create([
                'dispense_code' => substr($code, 0, 32),
                'storage_id' => $storage->id,
                'equipment_id' => $equipment->id,
                'volume_ml' => $p['volume_ml'],
                'equipment_engine_hours' => (int) $equipment->engine_hours,
                'operator_ref' => $p['operator_ref'] ?? 'dispatcher',
                'flowmeter_start_reading' => $p['flowmeter_start'],
                'flowmeter_end_reading' => $p['flowmeter_end'],
                'dispensed_at' => $this->clock->now(),
            ]);

            $amountIdr = intdiv($p['volume_ml'] * $p['unit_price_idr_per_liter'], 1000);
            $poster = $this->postingService ?? new MiningFase93LedgerPostingService($this->ledger);
            $poster->postFuelDispense(
                dispenseRecordId: (string) $record->id,
                dispenseCode: $record->dispense_code,
                equipmentCode: (string) $equipment->equipment_code,
                storageCode: (string) $storage->storage_code,
                amountIdr: $amountIdr
            );

            return $record;
        });
    }
}

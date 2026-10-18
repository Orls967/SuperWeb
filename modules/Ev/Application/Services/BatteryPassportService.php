<?php

declare(strict_types=1);

namespace Modules\Ev\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Ev\Domain\Events\BatteryDegradedThresholdReached;
use Modules\Ev\Domain\Models\EvBatteryPassport;
use Modules\Ev\Domain\Models\EvSession;

class BatteryPassportService
{
    public const GENESIS_HASH_PREFIX = 'GENESIS:BATTERY:PASSPORT:';

    public const CRITICAL_SOH_THRESHOLD = 70.0;

    public function __construct(
        protected SimClockInterface $simClock,
        protected DigitalTwinInterface $digitalTwin
    ) {}

    public function recordSessionBlock(
        EvSession $session,
        int $energyWh,
        float $maxCellTempC,
        ?float $overrideFinalSoh = null
    ): EvBatteryPassport {
        return DB::transaction(function () use ($session, $energyWh, $maxCellTempC, $overrideFinalSoh) {
            $vehicleId = $session->vehicle_id;

            $latestBlock = EvBatteryPassport::where('vehicle_id', $vehicleId)
                ->orderByDesc('cycle_number')
                ->lockForUpdate()
                ->first();

            $cycleNumber = $latestBlock ? $latestBlock->cycle_number + 1 : 1;
            $initialSoh = $latestBlock ? (float) $latestBlock->final_soh_pct : (float) $session->initial_soh_pct;
            $prevHash = $latestBlock ? $latestBlock->block_hash : hash('sha256', self::GENESIS_HASH_PREFIX.$vehicleId);

            $deltaSoh = $this->calculateElectroDegradation($energyWh, $maxCellTempC);
            $finalSoh = $overrideFinalSoh !== null
                ? (float) max(0.0, round($overrideFinalSoh, 2))
                : (float) max(0.0, round($initialSoh - $deltaSoh, 2));

            $recordedAt = $this->simClock->now();

            $payload = implode('|', [
                $prevHash,
                $vehicleId,
                $session->session_code,
                $cycleNumber,
                $energyWh,
                number_format($finalSoh, 2, '.', ''),
                number_format($maxCellTempC, 2, '.', ''),
                $recordedAt->toIso8601String(),
            ]);
            $blockHash = hash('sha256', $payload);

            $block = EvBatteryPassport::create([
                'vehicle_id' => $vehicleId,
                'session_code' => $session->session_code,
                'cycle_number' => $cycleNumber,
                'initial_soh_pct' => $initialSoh,
                'final_soh_pct' => $finalSoh,
                'delta_soh' => $deltaSoh,
                'max_cell_temp_c' => $maxCellTempC,
                'energy_wh' => $energyWh,
                'prev_hash' => $prevHash,
                'block_hash' => $blockHash,
                'recorded_at' => $recordedAt,
            ]);

            $this->digitalTwin->recordState('vehicle_battery', (string) $vehicleId, [
                'session_code' => $session->session_code,
                'cycle_number' => $cycleNumber,
                'soh_pct' => $finalSoh,
                'block_hash' => $blockHash,
                'recorded_at' => $recordedAt->toIso8601String(),
            ]);

            if ($finalSoh < self::CRITICAL_SOH_THRESHOLD) {
                event(new BatteryDegradedThresholdReached(
                    vehicleId: $vehicleId,
                    currentSohPct: $finalSoh,
                    recommendation: 'trade_in_autodex_hodl_to_drive',
                    metadata: [
                        'session_code' => $session->session_code,
                        'cycle_number' => $cycleNumber,
                        'energy_wh' => $energyWh,
                    ]
                ));
            }

            return $block;
        });
    }

    public function calculateElectroDegradation(int $energyWh, float $maxCellTempC): float
    {
        $kwh = $energyWh / 1000.0;
        $deltaSoc = min(100.0, ($kwh / 60.0) * 100.0);
        $tempKelvin = $maxCellTempC + 273.15;
        $thermalStress = exp(($tempKelvin - 298.15) / 30.0);
        $decay = 0.00015 * pow($deltaSoc / 100.0, 1.3) * $thermalStress;

        return round($decay, 4);
    }

    public function verifyChain(int $vehicleId): array
    {
        $blocks = EvBatteryPassport::where('vehicle_id', $vehicleId)
            ->orderBy('cycle_number')
            ->get();

        if ($blocks->isEmpty()) {
            return ['valid' => true, 'blocks_checked' => 0];
        }

        $expectedPrev = hash('sha256', self::GENESIS_HASH_PREFIX.$vehicleId);

        foreach ($blocks as $block) {
            if ($block->prev_hash !== $expectedPrev) {
                return [
                    'valid' => false,
                    'corrupted_cycle' => $block->cycle_number,
                    'reason' => 'Prev hash mismatch',
                ];
            }

            $payload = implode('|', [
                $block->prev_hash,
                $block->vehicle_id,
                $block->session_code,
                $block->cycle_number,
                $block->energy_wh,
                number_format((float) $block->final_soh_pct, 2, '.', ''),
                number_format((float) $block->max_cell_temp_c, 2, '.', ''),
                $block->recorded_at->toIso8601String(),
            ]);

            if (hash('sha256', $payload) !== $block->block_hash) {
                return [
                    'valid' => false,
                    'corrupted_cycle' => $block->cycle_number,
                    'reason' => 'Block hash corrupted',
                ];
            }

            $expectedPrev = $block->block_hash;
        }

        return ['valid' => true, 'blocks_checked' => $blocks->count()];
    }
}

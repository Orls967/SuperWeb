<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Manufacturing\Contracts\ProcessOutputRecorder;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;

class ExecuteSmelterRunAction
{
    public function __construct(
        private readonly ProcessOutputRecorder $outputRecorder
    ) {}

    /**
     * Eksekusi kampanye peleburan smelter RKEF (Rotary Kiln Electric Furnace) (D94-8, G7-K12).
     *
     * Mentransformasikan bijih laterit saprolite menjadi Ferronickel (FeNi) atau Nickel Matte,
     * memperhitungkan konsumsi kokas reduktor, fluks silika/kapur, dan emisi terak (slag).
     *
     * @param array{
     *     site_id: string,
     *     stockpile_id: string,
     *     feed_tonnage_kg: int,
     *     target_product: string,
     *     metallurgical_recovery_bps: int,
     *     electric_power_kwh_per_ton: int
     * } $data
     * @return array{
     *     batch_code: string,
     *     site_id: string,
     *     feed_tonnage_kg: int,
     *     metal_output_kg: int,
     *     slag_tonnage_kg: int,
     *     recovery_bps: int,
     *     power_consumed_kwh: int,
     *     manufacturing_batch_id: string,
     *     status: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $stockpileId = (string) $data['stockpile_id'];
            $feedKg = (int) $data['feed_tonnage_kg'];
            $targetProduct = strtoupper((string) ($data['target_product'] ?? 'FERRONICKEL'));
            $recoveryBps = (int) ($data['metallurgical_recovery_bps'] ?? 9200); // 92.00%
            $powerPerTon = (int) ($data['electric_power_kwh_per_ton'] ?? 420);

            if ($feedKg <= 0) {
                throw new InvalidArgumentException('Tonase umpan smelter harus lebih besar dari nol.');
            }

            $site = MiningSite::findOrFail($siteId);
            $stockpile = MiningStockpile::findOrFail($stockpileId);

            if ($stockpile->total_kg < $feedKg) {
                throw new DomainException("Saldo stockpile {$stockpile->stockpile_code} ({$stockpile->total_kg} kg) tidak mencukupi kebutuhan umpan ({$feedKg} kg).");
            }

            // Kurangi stok saprolite di stockpile
            $stockpile->total_kg -= $feedKg;
            $stockpile->save();

            // Hitung output berdasarkan rasio metalurgi
            // Saprolite ~1.8% Ni -> FeNi 20% Ni -> yield massa ~8.5% dari feed bijih
            $metalYieldBps = ($targetProduct === 'FERRONICKEL') ? 850 : 350; // 8.5% FeNi atau 3.5% Matte
            $metalOutputKg = intdiv($feedKg * $metalYieldBps * $recoveryBps, 10000 * 10000);
            $slagOutputKg = max(0, $feedKg - $metalOutputKg - intdiv($feedKg * 1200, 10000)); // Gas buang ~12%
            $totalPowerKwh = intdiv($feedKg, 1000) * $powerPerTon;

            $batchCode = 'SML-'.strtoupper($site->commodity ?? 'NI').'-'.date('Ymd').'-'.strtoupper(Str::random(5));
            $idempotencyKey = "min:smelter-batch:{$batchCode}";

            // Integrasi ke modul Manufaktur (G7-K12)
            $mfgOutput = $this->outputRecorder->recordProcessOutput([
                'batch_code' => $batchCode,
                'input_commodity' => $site->commodity ?? 'NICKEL',
                'input_tonnage_kg' => $feedKg,
                'output_product_code' => $targetProduct,
                'output_tonnage_kg' => $metalOutputKg,
                'metal_recovery_bps' => $recoveryBps,
                'idempotency_key' => $idempotencyKey,
            ]);

            return [
                'batch_code' => $batchCode,
                'site_id' => $siteId,
                'feed_tonnage_kg' => $feedKg,
                'metal_output_kg' => $metalOutputKg,
                'slag_tonnage_kg' => $slagOutputKg,
                'recovery_bps' => $recoveryBps,
                'power_consumed_kwh' => $totalPowerKwh,
                'manufacturing_batch_id' => $mfgOutput['output_batch_id'] ?? 'MFG-SMELTER-'.$batchCode,
                'status' => 'COMPLETED',
            ];
        });
    }
}

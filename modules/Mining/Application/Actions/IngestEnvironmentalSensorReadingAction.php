<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningEnvironmentalReading;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Ledger\MiningFase94LedgerPostingService;

class IngestEnvironmentalSensorReadingAction
{
    public function __construct(
        private readonly MiningFase94LedgerPostingService $ledgerPosting
    ) {}

    /**
     * Catat dan analisis pembacaan telemetri sensor kualitas lingkungan konsesi tambang (D94-6).
     *
     * Parameter baku mutu air limbah tambang batubara/mineral (Permen LHK 113/2003 & Kepmen ESDM 1827/2018):
     * - pH: 6.0 – 9.0 (skala x100: 600 - 900)
     * - TSS (Total Suspended Solids): <= 300 mg/L (skala x100: 30000)
     * - Besi terlarut (Fe): <= 7.0 mg/L (skala x100: 700)
     * - Mangan terlarut (Mn): <= 4.0 mg/L (skala x100: 400)
     *
     * @param array{
     *     site_id: string,
     *     sensor_point_code: string,
     *     reading_type: string,
     *     ph_scaled: int,
     *     tss_mg_l_scaled: int,
     *     fe_mg_l_scaled?: int,
     *     mn_mg_l_scaled?: int,
     *     pm10_ug_m3?: int,
     *     vibration_ppv_mm_s?: float,
     *     read_at?: string
     * } $data
     * @return array{
     *     reading_id: string,
     *     sensor_point: string,
     *     is_compliant: bool,
     *     breach_parameters: list<string>,
     *     penalty_applied_idr: int,
     *     status: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $pointCode = (string) $data['sensor_point_code'];
            $readingType = strtoupper((string) ($data['reading_type'] ?? 'WATER_SEDIMENT_POND'));
            $ph = (int) $data['ph_scaled'];
            $tss = (int) $data['tss_mg_l_scaled'];
            $fe = (int) ($data['fe_mg_l_scaled'] ?? 0);
            $mn = (int) ($data['mn_mg_l_scaled'] ?? 0);
            $readAt = $data['read_at'] ? CarbonImmutable::parse($data['read_at']) : CarbonImmutable::now();

            $site = MiningSite::findOrFail($siteId);

            $breaches = [];

            // Evaluasi Baku Mutu Lingkungan
            if ($ph < 600 || $ph > 900) {
                $breaches[] = 'PH_OUT_OF_RANGE ('.round($ph / 100, 2).' di luar batas 6.00 - 9.00)';
            }
            if ($tss > 30000) {
                $breaches[] = 'TSS_EXCEEDED ('.round($tss / 100, 1).' mg/L melebihi batas 300.0 mg/L)';
            }
            if ($fe > 700) {
                $breaches[] = 'FE_DISSOLVED_EXCEEDED ('.round($fe / 100, 2).' mg/L melebihi batas 7.00 mg/L)';
            }
            if ($mn > 400) {
                $breaches[] = 'MN_DISSOLVED_EXCEEDED ('.round($mn / 100, 2).' mg/L melebihi batas 4.00 mg/L)';
            }

            $isCompliant = empty($breaches);
            $penaltyIdr = 0;

            // Simpan record telemetri lingkungan
            $reading = MiningEnvironmentalReading::create([
                'id' => (string) Str::uuid(),
                'site_id' => $site->id,
                'location_code' => $pointCode,
                'sensor_type' => 'water_ph',
                'reading_value' => $ph,
                'unit_of_measure' => 'pH*100',
                'threshold_limit' => 900,
                'is_breached' => ! $isCompliant,
                'area_shutdown_triggered' => ! $isCompliant,
                'recorded_at' => $readAt,
            ]);

            // Jika terdapat pelanggaran baku mutu air asam tambang, posting denda pencemaran ke buku besar
            if (! $isCompliant) {
                // Tarif denda sanksi administratif KLHK (misal Rp 25.000.000 per kejadian anomali)
                $penaltyIdr = 25000000 * count($breaches);

                $this->ledgerPosting->postEnvironmentalPenalty(
                    readingId: (string) $reading->id,
                    locationCode: $pointCode,
                    amountIdr: $penaltyIdr
                );
            }

            return [
                'reading_id' => (string) $reading->id,
                'sensor_point' => $pointCode,
                'is_compliant' => $isCompliant,
                'breach_parameters' => $breaches,
                'penalty_applied_idr' => $penaltyIdr,
                'status' => $isCompliant ? 'CLEAN_NORMAL' : 'ENVIRONMENTAL_BREACH_ALERT',
            ];
        });
    }
}

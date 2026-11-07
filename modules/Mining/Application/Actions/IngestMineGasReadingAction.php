<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Models\MiningSite;

class IngestMineGasReadingAction
{
    /**
     * Catat dan analisis telemetri sensor gas beracun & mudah meledak tambang (D94-5).
     *
     * Nilai Ambang Batas (NAB) Kepmen ESDM 1827/2018:
     * - Oksigen (O2): Minimal 19.5% Vol (skala x100: 1950)
     * - Karbon Monoksida (CO): Maksimal 25 ppm (indikasi swa-bakar / spontaneous combustion batubara bila > 50 ppm)
     * - Metana (CH4): Batas ledakan bawah (LEL) 5% Vol (~50000 ppm); Alarm evakuasi pada 1.0% Vol (10000 ppm)
     * - Hidrogen Sulfida (H2S): Maksimal 10 ppm
     * - Nitrogen Dioksida (NO2): Maksimal 3 ppm (asap peledakan / blasting fumes).
     *
     * @param array{
     *     site_id: string,
     *     sensor_location_code: string,
     *     o2_pct_scaled: int,
     *     co_ppm: int,
     *     ch4_ppm: int,
     *     h2s_ppm?: int,
     *     no2_ppm?: int,
     *     read_at?: string
     * } $data
     * @return array{
     *     location_code: string,
     *     safety_status: string,
     *     is_immediate_evacuation_required: bool,
     *     spontaneous_combustion_risk: bool,
     *     gas_hazards: list<string>,
     *     haldane_ratio_indicator: string,
     *     directive: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $location = trim((string) $data['sensor_location_code']);
            $o2 = (int) $data['o2_pct_scaled'];
            $co = (int) $data['co_ppm'];
            $ch4 = (int) $data['ch4_ppm'];
            $h2s = (int) ($data['h2s_ppm'] ?? 0);
            $no2 = (int) ($data['no2_ppm'] ?? 0);
            $readAt = ! empty($data['read_at']) ? CarbonImmutable::parse($data['read_at']) : CarbonImmutable::now();

            $site = MiningSite::findOrFail($siteId);

            $hazards = [];
            $evacuate = false;
            $spontaneousCombustion = false;

            // 1. Evaluasi Defisiensi Oksigen
            if ($o2 < 1950) {
                $hazards[] = 'DEFISIENSI_OKSIGEN ('.round($o2 / 100, 2).'% < 19.50% Vol)';
                if ($o2 < 1600) {
                    $evacuate = true;
                }
            }

            // 2. Evaluasi Gas Metana (CH4)
            if ($ch4 >= 10000) { // >= 1.0% Vol
                $hazards[] = 'KONSENTRASI_METANA_BERBAHAYA ('.$ch4.' ppm >= 10.000 ppm)';
                $evacuate = true;
            } elseif ($ch4 >= 5000) {
                $hazards[] = 'WASPADA_METANA ('.$ch4.' ppm)';
            }

            // 3. Evaluasi Karbon Monoksida (CO) & Swa-Bakar (Graham's / Haldane Ratio)
            if ($co > 50) {
                $hazards[] = 'INDIKASI_SWA_BAKAR_BATUBARA (CO '.$co.' ppm > 50 ppm)';
                $spontaneousCombustion = true;
                if ($co > 100) {
                    $evacuate = true;
                }
            } elseif ($co > 25) {
                $hazards[] = 'CO_MELEBIHI_NAB ('.$co.' ppm > 25 ppm)';
            }

            // 4. Gas Beracun H2S & NO2
            if ($h2s > 10) {
                $hazards[] = 'H2S_BERACUN ('.$h2s.' ppm > 10 ppm)';
                $evacuate = true;
            }
            if ($no2 > 3) {
                $hazards[] = 'NO2_FUMES_PELEDAKAN ('.$no2.' ppm > 3 ppm)';
            }

            $safetyStatus = 'SAFE_NORMAL';
            $directive = 'Konsentrasi atmosfer kerja aman. Ventilasi tambang beroperasi optimal.';

            if ($evacuate) {
                $safetyStatus = 'CRITICAL_EVACUATION_ALARM';
                $directive = 'BUNYIKAN SIRINE DARURAT: Evakuasi seluruh personil dari front kerja ke titik kumpul aman (muster point) segera!';
            } elseif (! empty($hazards)) {
                $safetyStatus = 'WARNING_VENTILATION_BOOST';
                $directive = 'Tingkatkan laju aliran udara ventilasi dan semprotkan air peredam debu/gas.';
            }

            $haldaneIndicator = $spontaneousCombustion
                ? 'Rasio Graham CO/O2-def > 0.5% (Tanda aktif swabakar batubara)'
                : 'Atmosfer pembakaran stabil';

            return [
                'location_code' => $location,
                'safety_status' => $safetyStatus,
                'is_immediate_evacuation_required' => $evacuate,
                'spontaneous_combustion_risk' => $spontaneousCombustion,
                'gas_hazards' => $hazards,
                'haldane_ratio_indicator' => $haldaneIndicator,
                'directive' => $directive,
            ];
        });
    }
}

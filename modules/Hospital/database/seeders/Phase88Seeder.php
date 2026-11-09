<?php

declare(strict_types=1);

namespace Modules\Hospital\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Hospital\Application\DTOs\FolioItemDTO;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\ColdChainTelemetry;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\GuaranteeLetter;
use Modules\Hospital\Domain\Models\InaCbgTariff;
use Modules\Hospital\Domain\Models\MedicalWasteManifest;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Services\HospitalBillingCalculationService;
use Modules\Hospital\Domain\Services\MedicalWasteManifestService;

class Phase88Seeder extends Seeder
{
    public function run(): void
    {
        $this->seedInaCbgTariffs();
        $this->seedBillingEpisodesAndFolio();
        $this->seedColdChainTelemetry();
        $this->seedMedicalWasteManifests();
    }

    private function seedInaCbgTariffs(): void
    {
        $tariffs = [
            [
                'cbg_code' => 'I-4-10-I',
                'description' => 'Infark Miokard Akut Ringan',
                'care_type' => 'INPATIENT',
                'severity_level' => 'I',
                'hospital_class' => 'B',
                'base_tariff_idr' => 12_500_000,
                'class_3_tariff_idr' => 11_250_000,
                'class_2_tariff_idr' => 13_750_000,
                'class_1_tariff_idr' => 16_875_000,
                'vip_max_topup_idr' => 12_656_250,
                'is_active' => true,
            ],
            [
                'cbg_code' => 'I-4-10-II',
                'description' => 'Infark Miokard Akut Sedang dengan Komplikasi',
                'care_type' => 'INPATIENT',
                'severity_level' => 'II',
                'hospital_class' => 'B',
                'base_tariff_idr' => 16_875_000,
                'class_3_tariff_idr' => 15_187_500,
                'class_2_tariff_idr' => 18_562_500,
                'class_1_tariff_idr' => 22_781_250,
                'vip_max_topup_idr' => 17_085_937,
                'is_active' => true,
            ],
            [
                'cbg_code' => 'I-4-10-III',
                'description' => 'Infark Miokard Akut Berat / Syok Kardiogenik',
                'care_type' => 'INPATIENT',
                'severity_level' => 'III',
                'hospital_class' => 'B',
                'base_tariff_idr' => 23_125_000,
                'class_3_tariff_idr' => 20_812_500,
                'class_2_tariff_idr' => 25_437_500,
                'class_1_tariff_idr' => 31_218_750,
                'vip_max_topup_idr' => 23_414_062,
                'is_active' => true,
            ],
            [
                'cbg_code' => 'O-6-12-I',
                'description' => 'Seksio Sesarea Tanpa Penyulit',
                'care_type' => 'INPATIENT',
                'severity_level' => 'I',
                'hospital_class' => 'B',
                'base_tariff_idr' => 8_500_000,
                'class_3_tariff_idr' => 7_650_000,
                'class_2_tariff_idr' => 9_350_000,
                'class_1_tariff_idr' => 11_475_000,
                'vip_max_topup_idr' => 8_606_250,
                'is_active' => true,
            ],
            [
                'cbg_code' => 'J-4-16-I',
                'description' => 'Pneumonia Ringan Dewasa',
                'care_type' => 'INPATIENT',
                'severity_level' => 'I',
                'hospital_class' => 'B',
                'base_tariff_idr' => 6_500_000,
                'class_3_tariff_idr' => 5_850_000,
                'class_2_tariff_idr' => 7_150_000,
                'class_1_tariff_idr' => 8_775_000,
                'vip_max_topup_idr' => 6_581_250,
                'is_active' => true,
            ],
        ];

        foreach ($tariffs as $t) {
            InaCbgTariff::updateOrCreate(['cbg_code' => $t['cbg_code']], $t);
        }
    }

    private function seedBillingEpisodesAndFolio(): void
    {
        $encounters = Encounter::limit(5)->get();
        $calc = app(HospitalBillingCalculationService::class);

        foreach ($encounters as $idx => $enc) {
            $episode = BillingEpisode::firstOrCreate(
                ['encounter_id' => $enc->id],
                [
                    'episode_code' => 'EP-DEMO-'.str_pad((string) $enc->id, 5, '0', STR_PAD_LEFT),
                    'escrow_deposit_idr' => 3_000_000,
                    'total_charges_idr' => 0,
                    'status' => 'OPEN',
                ]
            );

            // Tambahkan baris-baris folio
            $calc->addItemToEpisode($episode, new FolioItemDTO(
                category: 'BED_DAY',
                description: 'Akomodasi Perawatan Rawat Inap Kelas 1 (3 hari)',
                quantity: 3,
                unitPriceIdr: 750_000,
                subtotalIdr: 2_250_000
            ));

            $calc->addItemToEpisode($episode, new FolioItemDTO(
                category: 'PROCEDURE',
                description: 'Visite Dokter Spesialis Jantung (3 kali)',
                quantity: 3,
                unitPriceIdr: 350_000,
                subtotalIdr: 1_050_000
            ));

            $calc->addItemToEpisode($episode, new FolioItemDTO(
                category: 'PHARMACY',
                description: 'Injeksi Enoxaparin Sodium 40mg/0.4mL Cold-Chain',
                quantity: 2,
                unitPriceIdr: 450_000,
                subtotalIdr: 900_000
            ));

            $calc->addItemToEpisode($episode, new FolioItemDTO(
                category: 'LAB',
                description: 'Pemeriksaan Troponin I Kuantitatif & EKG 12 Lead',
                quantity: 1,
                unitPriceIdr: 650_000,
                subtotalIdr: 650_000
            ));

            // Buat Surat Jaminan Pelayanan Asuransi Swasta jika ada pasien
            $patient = Patient::find($enc->patient_id);
            if ($patient && $idx % 2 === 0) {
                GuaranteeLetter::firstOrCreate(
                    ['guarantee_number' => "GL-DEMO-{$enc->id}"],
                    [
                        'patient_id' => $patient->id,
                        'encounter_id' => $enc->id,
                        'payer_name' => 'PRUDENTIAL_INDONESIA',
                        'policy_number' => 'POL-PRU-987654321',
                        'approved_coverage_limit_idr' => 50_000_000,
                        'utilized_amount_idr' => 0,
                        'status' => 'ISSUED',
                        'valid_until' => now()->addDays(14),
                        'auth_code' => 'AUTH-PRU-OK',
                    ]
                );
            }
        }
    }

    private function seedColdChainTelemetry(): void
    {
        $fridges = ['FRIDGE-CHILLER-01', 'FRIDGE-CHILLER-02', 'FRIDGE-FREEZER-01'];

        foreach ($fridges as $fridge) {
            $isFreezer = str_contains($fridge, 'FREEZER');
            $zone = $isFreezer ? 'FREEZER_MINUS_20' : 'CHILLER_2_TO_8';
            $normalTemp = $isFreezer ? -19.5 : 4.2;

            for ($i = 0; $i < 6; $i++) {
                ColdChainTelemetry::create([
                    'sensor_device_code' => "SENSOR-{$fridge}-A",
                    'fridge_unit_code' => $fridge,
                    'storage_zone' => $zone,
                    'temperature_c' => $normalTemp + (mt_rand(-5, 5) / 10),
                    'humidity_pct' => 55.0 + mt_rand(-3, 3),
                    'is_excursion' => false,
                    'excursion_severity' => 'NORMAL',
                    'recorded_at' => now()->subMinutes($i * 10),
                ]);
            }
        }
    }

    private function seedMedicalWasteManifests(): void
    {
        $wasteService = app(MedicalWasteManifestService::class);

        $categories = ['SHARPS', 'INFECTIOUS', 'PHARMACEUTICAL'];
        foreach ($categories as $cat) {
            $record = $wasteService->registerWasteFromWard(
                wasteCategory: $cat,
                weightKg: 4.75,
                sourceWardCode: 'WARD-ICU-01',
                handlerStaffName: 'Ns. Siti Rahmawati, S.Kep'
            );

            $manifest = MedicalWasteManifest::where('manifest_number', $record->manifestNumber)->first();
            if ($manifest) {
                $wasteService->receiveAtInternalTps(
                    manifest: $manifest,
                    reweighedKg: 4.80,
                    tpsOfficerName: 'Bambang Sudirjo (Sanitarian TPS)',
                    tpsBinCode: 'BIN-TPS-B3-YELLOW'
                );

                $wasteService->handoverToTransporter(
                    manifest: $manifest,
                    transporterCompanyName: 'PT Prasadha Pamunah Limbah Industri (PPLI)',
                    vehiclePlateNo: 'B 9123 PPL',
                    driverName: 'Agus Salim (Driver Berizin KLHK)',
                    handoverWeightKg: 4.80
                );
            }
        }
    }
}

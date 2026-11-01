<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mobility\Ev\Application\Services\FlowCutCertificateVerifier;
use Modules\Mobility\Ev\Application\Services\SmartChargingDinicService;
use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvStation;
use Tests\TestCase;

class MobilityEvSmartChargingDinicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createStationWithChargers(int $sitePowerKw = 100): EvStation
    {
        $station = EvStation::create([
            'station_code' => 'SPKLU-POW-01',
            'name' => 'SPKLU Central Power',
            'address' => 'Jl. Pembangkit No 1',
            'timezone' => 'Asia/Jakarta',
            'transformer_capacity_kw' => $sitePowerKw,
            'max_grid_power_kw' => $sitePowerKw,
            'location_type' => 'hub',
        ]);

        // 3 Chargers: C1 (50 kW), C2 (50 kW), C3 (20 kW)
        EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-01',
            'charger_type' => 'DC_FAST',
            'max_power_kw' => 50,
            'status' => 'OCCUPIED',
        ]);
        EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-02',
            'charger_type' => 'DC_FAST',
            'max_power_kw' => 50,
            'status' => 'OCCUPIED',
        ]);
        EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-03',
            'charger_type' => 'AC',
            'max_power_kw' => 20,
            'status' => 'OCCUPIED',
        ]);

        return $station;
    }

    public function test_kasus_layak_seluruh_permintaan_daya_dapat_dipenuhi(): void
    {
        // Trafo 100 kW -> 100 * 250 Wh = 25.000 Wh per slot 15 menit
        $station = $this->createStationWithChargers(sitePowerKw: 100);
        $service = app(SmartChargingDinicService::class);
        $verifier = app(FlowCutCertificateVerifier::class);

        // Kebutuhan 3 sesi: S1 = 8.000 Wh, S2 = 7.000 Wh, S3 = 3.000 Wh (Total = 18.000 Wh <= 25.000 Wh)
        $requests = [
            'SES-01' => ['charger_code' => 'CHG-01', 'requested_wh' => 8000],
            'SES-02' => ['charger_code' => 'CHG-02', 'requested_wh' => 7000],
            'SES-03' => ['charger_code' => 'CHG-03', 'requested_wh' => 3000],
        ];

        $plan = $service->allocateSlotPower($station, $requests);

        $this->assertTrue($plan->isFeasible());
        $this->assertSame(18000, $plan->totalAllocatedWh());
        $this->assertSame(8000, $plan->getAllocatedWh('SES-01'));
        $this->assertSame(7000, $plan->getAllocatedWh('SES-02'));
        $this->assertSame(3000, $plan->getAllocatedWh('SES-03'));

        // Verifikasi sertifikat dual min-cut (PR2)
        $this->assertTrue($verifier->verify($plan));
    }

    public function test_kasus_overload_alokasi_dibatasi_daya_maksimal_trafo(): void
    {
        // Trafo 40 kW -> 40 * 250 Wh = 10.000 Wh per slot 15 menit
        $station = $this->createStationWithChargers(sitePowerKw: 40);
        $service = app(SmartChargingDinicService::class);
        $verifier = app(FlowCutCertificateVerifier::class);

        // Kebutuhan 3 sesi: S1 = 6.000 Wh, S2 = 6.000 Wh, S3 = 4.000 Wh (Total = 16.000 Wh > 10.000 Wh)
        $requests = [
            'SES-01' => ['charger_code' => 'CHG-01', 'requested_wh' => 6000],
            'SES-02' => ['charger_code' => 'CHG-02', 'requested_wh' => 6000],
            'SES-03' => ['charger_code' => 'CHG-03', 'requested_wh' => 4000],
        ];

        $plan = $service->allocateSlotPower($station, $requests);

        $this->assertFalse($plan->isFeasible());
        $this->assertSame(10000, $plan->totalAllocatedWh(), 'Total alokasi tidak boleh melebihi 10.000 Wh kapasitas site');
        $this->assertSame(6000, $plan->totalDeficitWh(), 'Total defisit kekurangan = 16.000 - 10.000 = 6.000 Wh');

        // Sertifikat min-cut tetap sah dan terbukti membatasi di irisan trafo (PR2)
        $this->assertTrue($verifier->verify($plan));
    }

    public function test_kekurangan_alokasi_dilaporkan_secara_eksplisit_per_sesi(): void
    {
        $station = $this->createStationWithChargers(sitePowerKw: 40);
        $service = app(SmartChargingDinicService::class);

        $requests = [
            'SES-01' => ['charger_code' => 'CHG-01', 'requested_wh' => 6000],
            'SES-02' => ['charger_code' => 'CHG-02', 'requested_wh' => 6000],
        ];

        $plan = $service->allocateSlotPower($station, $requests);

        $deficits = $plan->getDeficits();
        $this->assertNotEmpty($deficits);
        $this->assertSame(2000, $plan->totalDeficitWh());
    }

    public function test_sertifikat_palsu_atau_manipulasi_min_cut_ditolak_verifier(): void
    {
        $station = $this->createStationWithChargers(sitePowerKw: 100);
        $service = app(SmartChargingDinicService::class);
        $verifier = app(FlowCutCertificateVerifier::class);

        $requests = [
            'SES-01' => ['charger_code' => 'CHG-01', 'requested_wh' => 5000],
        ];

        $plan = $service->allocateSlotPower($station, $requests);

        // Manipulasi sertifikat (mengubah nilai cut capacity secara ilegal)
        $tamperedCertificate = $plan->certificate;
        $tamperedCertificate['cut_capacity_wh'] = 999999;
        $plan->setCertificate($tamperedCertificate);

        $this->expectException(\LogicException::class);
        $verifier->verifyOrThrow($plan);
    }

    public function test_alokasi_daya_dibatasi_oleh_kapasitas_masing_masing_charger(): void
    {
        // Trafo besar 200 kW (50.000 Wh), tapi charger CHG-03 hanya 20 kW (5.000 Wh/slot)
        $station = $this->createStationWithChargers(sitePowerKw: 200);
        $service = app(SmartChargingDinicService::class);

        $requests = [
            'SES-03' => ['charger_code' => 'CHG-03', 'requested_wh' => 9000], // Minta 9000 Wh pada charger 5000 Wh
        ];

        $plan = $service->allocateSlotPower($station, $requests);

        $this->assertSame(5000, $plan->getAllocatedWh('SES-03'));
        $this->assertSame(4000, $plan->getDeficitWh('SES-03'));
    }

    public function test_determinisme_alokasi_urutan_input_acak_menghasilkan_output_identik(): void
    {
        $station = $this->createStationWithChargers(sitePowerKw: 60);
        $service = app(SmartChargingDinicService::class);

        $req1 = [
            'SES-A' => ['charger_code' => 'CHG-01', 'requested_wh' => 7000],
            'SES-B' => ['charger_code' => 'CHG-02', 'requested_wh' => 7000],
        ];

        $req2 = [
            'SES-B' => ['charger_code' => 'CHG-02', 'requested_wh' => 7000],
            'SES-A' => ['charger_code' => 'CHG-01', 'requested_wh' => 7000],
        ];

        $plan1 = $service->allocateSlotPower($station, $req1);
        $plan2 = $service->allocateSlotPower($station, $req2);

        $this->assertSame($plan1->getAllocatedWh('SES-A'), $plan2->getAllocatedWh('SES-A'));
        $this->assertSame($plan1->getAllocatedWh('SES-B'), $plan2->getAllocatedWh('SES-B'));
    }

    public function test_penyimpanan_rencana_smart_charging_ke_database(): void
    {
        $station = $this->createStationWithChargers(sitePowerKw: 100);
        $service = app(SmartChargingDinicService::class);

        $requests = [
            'SES-01' => ['charger_code' => 'CHG-01', 'requested_wh' => 4000],
        ];

        $plan = $service->allocateSlotPower($station, $requests);
        $record = $service->persistPlan($station, '2026-10-30 14:00', $plan);

        $this->assertDatabaseHas('oto_ev_smart_charging_plans', [
            'id' => $record->id,
            'station_id' => $station->id,
            'time_slot' => '2026-10-30 14:00',
            'status' => 'VALID',
        ]);
    }

    public function test_multi_charger_bersaing_pada_kapasitas_trafo_sempit(): void
    {
        $station = $this->createStationWithChargers(sitePowerKw: 20); // 5.000 Wh total slot
        $service = app(SmartChargingDinicService::class);

        $requests = [
            'SES-01' => ['charger_code' => 'CHG-01', 'requested_wh' => 4000],
            'SES-02' => ['charger_code' => 'CHG-02', 'requested_wh' => 4000],
            'SES-03' => ['charger_code' => 'CHG-03', 'requested_wh' => 2000],
        ];

        $plan = $service->allocateSlotPower($station, $requests);

        $this->assertSame(5000, $plan->totalAllocatedWh());
        $this->assertSame(5000, $plan->totalDeficitWh());
    }
}

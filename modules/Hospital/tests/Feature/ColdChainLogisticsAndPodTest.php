<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\MedicineCourierDelivery;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Services\ColdChainMedicineDeliveryTracker;
use Modules\Hospital\Domain\Services\ThermosensitiveMedicineStabilityEngine;
use RuntimeException;
use Tests\TestCase;

class ColdChainLogisticsAndPodTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;

    private EPharmacyOrder $order;

    private ColdChainMedicineDeliveryTracker $tracker;

    private ThermosensitiveMedicineStabilityEngine $stabilityEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['role' => 'doctor']);
        $poly = Polyclinic::create([
            'code' => 'POLI-PED-TEST',
            'name' => 'Poli Anak & Vaksinasi',
        ]);

        Doctor::create([
            'user_id' => $user->id,
            'polyclinic_id' => $poly->id,
            'doctor_code' => 'DOC-PED-001',
            'name' => 'dr. Sarah Spesialis Anak Sp.A',
            'sip_number' => 'SIP/777/PEDI/2026',
            'specialization' => 'PEDIATRICS',
            'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'mrn' => 'MRN-COLD-001',
            'name' => 'Adinda Balita Ceria',
            'date_of_birth' => '2024-06-15',
            'blood_type' => 'B_POSITIVE',
            'passport_hash' => hash('sha256', 'PASSPORT-MRN-COLD-001'),
        ]);

        $branch = PharmacyBranch::create([
            'branch_code' => 'PHARM-PUSAT-01',
            'name' => 'Apotek Sentral Rumah Sakit',
            'type' => 'INTERNAL',
            'city' => 'Jakarta Pusat',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'full_address' => 'Jl. Salemba Raya No. 10 Jakarta Pusat',
            'has_cold_chain_storage' => true,
            'is_active' => true,
        ]);

        $this->order = EPharmacyOrder::create([
            'order_code' => 'EPO-COLD-9901',
            'patient_id' => $this->patient->id,
            'pharmacy_branch_id' => $branch->id,
            'drug_code' => 'INSULIN_GLARGINE',
            'drug_name' => 'Lantus SoloStar Insulin 100 IU/mL',
            'quantity' => 5,
            'total_price_idr' => 750000,
            'drug_classification' => 'COLD_CHAIN',
            'doctor_signature_hash' => hash('sha256', 'SIG-DOC-PED-001'),
            'status' => 'PROCESSING',
            'delivery_status' => 'PREPARING',
        ]);

        $this->tracker = app(ColdChainMedicineDeliveryTracker::class);
        $this->stabilityEngine = app(ThermosensitiveMedicineStabilityEngine::class);
    }

    public function test_initiate_cold_chain_delivery_with_valid_baseline_temperature(): void
    {
        $delivery = $this->tracker->initiateDelivery($this->order, [
            'requires_cold_chain' => true,
            'initial_temp_c' => 4.5,
            'min_temp_c' => 2.0,
            'max_temp_c' => 8.0,
            'recipient_name' => 'Ny. Ratna (Ibu Pasien)',
            'recipient_phone' => '081198765432',
            'destination_address' => 'Jl. Pegangsaan Timur No. 56 Jakarta',
            'destination_latitude' => -6.2000,
            'destination_longitude' => 106.8400,
        ]);

        $this->assertInstanceOf(MedicineCourierDelivery::class, $delivery);
        $this->assertSame('IN_TRANSIT', $delivery->delivery_status);
        $this->assertTrue($delivery->requires_cold_chain);
        $this->assertSame(4.5, (float) $delivery->last_recorded_temp_c);
        $this->assertFalse($delivery->temperature_breached);
    }

    public function test_temperature_telemetry_within_cold_chain_keeps_in_transit(): void
    {
        $delivery = $this->tracker->initiateDelivery($this->order, [
            'requires_cold_chain' => true,
            'initial_temp_c' => 4.0,
            'recipient_name' => 'Ny. Ratna',
            'recipient_phone' => '081198765432',
            'destination_address' => 'Jl. Pegangsaan Timur No. 56 Jakarta',
        ]);

        $updated = $this->tracker->recordTemperatureTelemetry($delivery, 5.2);
        $this->assertSame('IN_TRANSIT', $updated->delivery_status);
        $this->assertFalse($updated->temperature_breached);
        $this->assertSame(5.2, (float) $updated->last_recorded_temp_c);
    }

    public function test_temperature_breach_quarantines_package_instantly(): void
    {
        $delivery = $this->tracker->initiateDelivery($this->order, [
            'requires_cold_chain' => true,
            'initial_temp_c' => 4.0,
            'min_temp_c' => 2.0,
            'max_temp_c' => 8.0,
            'recipient_name' => 'Ny. Ratna',
            'recipient_phone' => '081198765432',
            'destination_address' => 'Jl. Pegangsaan Timur No. 56 Jakarta',
        ]);

        // Suhu melonjak ke 12.8°C (kegagalan ice pack pendingin)
        $quarantined = $this->tracker->recordTemperatureTelemetry($delivery, 12.8);

        $this->assertSame('QUARANTINED_BREACH', $quarantined->delivery_status);
        $this->assertTrue($quarantined->temperature_breached);
        $this->assertSame(12.8, (float) $quarantined->last_recorded_temp_c);

        // Order terkait juga dikarantina
        $this->order->refresh();
        $this->assertSame('QUARANTINED_BREACH', $this->order->delivery_status);
    }

    public function test_quarantined_package_cannot_be_handed_over_with_epod(): void
    {
        $delivery = $this->tracker->initiateDelivery($this->order, [
            'requires_cold_chain' => true,
            'initial_temp_c' => 4.0,
            'recipient_name' => 'Ny. Ratna',
            'recipient_phone' => '081198765432',
            'destination_address' => 'Jl. Pegangsaan Timur No. 56 Jakarta',
        ]);

        $this->tracker->recordTemperatureTelemetry($delivery, 11.5);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('kegagalan rantai dingin');

        $this->tracker->completeProofOfDelivery(
            delivery: $delivery,
            recipientNik: '3171012345678901',
            rawSignatureOrToken: 'SIG-TOKEN-12345'
        );
    }

    public function test_valid_delivery_completes_with_electronic_pod_and_hash(): void
    {
        $delivery = $this->tracker->initiateDelivery($this->order, [
            'requires_cold_chain' => true,
            'initial_temp_c' => 3.8,
            'recipient_name' => 'Ny. Ratna',
            'recipient_phone' => '081198765432',
            'destination_address' => 'Jl. Pegangsaan Timur No. 56 Jakarta',
        ]);

        $delivered = $this->tracker->completeProofOfDelivery(
            delivery: $delivery,
            recipientNik: '3171012345678901',
            rawSignatureOrToken: 'TOKEN-BIOMETRIC-PASIEN-2026'
        );

        $this->assertSame('DELIVERED', $delivered->delivery_status);
        $this->assertSame('3171012345678901', $delivered->pod_recipient_nik);
        $this->assertNotNull($delivered->pod_recipient_signature_hash);
        $this->assertNotNull($delivered->delivered_at);

        $this->order->refresh();
        $this->assertSame('DELIVERED', $this->order->delivery_status);
    }

    public function test_mean_kinetic_temperature_mkt_arrhenius_calculation(): void
    {
        // Rangkaian pembacaan suhu normal dingin 3.0°C - 5.5°C
        $series = [4.0, 4.2, 4.5, 4.8, 5.0, 5.2, 4.9, 4.3, 3.8, 4.0];
        $mkt = $this->stabilityEngine->calculateMktCelsius($series);

        $this->assertGreaterThanOrEqual(3.5, $mkt);
        $this->assertLessThanOrEqual(6.0, $mkt);
    }
}

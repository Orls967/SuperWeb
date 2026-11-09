<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Domain\Models\ChronicSubscription;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\Services\ChronicMedicationAdherenceAnalyzer;
use Modules\Hospital\Domain\Services\ChronicMedicationSupplyChainForecaster;
use Modules\Hospital\Domain\Services\ChronicRefillPredictiveScheduler;
use Tests\TestCase;

class ChronicRefillAndAdherenceTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;

    private PharmacyBranch $branch;

    private ChronicRefillPredictiveScheduler $scheduler;

    private ChronicMedicationAdherenceAnalyzer $adherenceAnalyzer;

    private ChronicMedicationSupplyChainForecaster $forecaster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patient = Patient::create([
            'mrn' => 'MRN-CHR-TEST-01',
            'name' => 'Bapak Hartono Diabetes',
            'date_of_birth' => '1962-09-18',
            'blood_type' => 'A_POSITIVE',
            'encrypted_chronic_diagnoses' => ['TYPE_2_DIABETES_MELLITUS', 'HYPERTENSION'],
            'passport_hash' => hash('sha256', 'PASSPORT-MRN-CHR-TEST-01'),
        ]);

        $this->branch = PharmacyBranch::create([
            'branch_code' => 'BR-CHR-01',
            'name' => 'Apotek Jaringan Senayan',
            'type' => 'INTERNAL',
            'city' => 'Jakarta Pusat',
            'latitude' => -6.2200,
            'longitude' => 106.8000,
            'full_address' => 'Jl. Asia Afrika No. 19 Jakarta Pusat',
            'has_cold_chain_storage' => true,
            'is_active' => true,
        ]);

        $this->scheduler = app(ChronicRefillPredictiveScheduler::class);
        $this->adherenceAnalyzer = app(ChronicMedicationAdherenceAnalyzer::class);
        $this->forecaster = app(ChronicMedicationSupplyChainForecaster::class);
    }

    public function test_predictive_scheduler_identifies_due_subscriptions(): void
    {
        // Langganan 1: Jatuh tempo besok (aktif)
        $sub1 = ChronicSubscription::create([
            'subscription_code' => 'SUB-CHR-001',
            'patient_id' => $this->patient->id,
            'pharmacy_branch_id' => $this->branch->id,
            'drug_code' => 'METFORMIN_500',
            'drug_name' => 'Metformin HCl 500mg',
            'monthly_quantity' => 60,
            'unit_price_idr' => 1500,
            'next_refill_date' => Carbon::today()->addDay()->toDateString(),
            'status' => 'ACTIVE',
        ]);

        // Langganan 2: Masih lama (20 hari lagi)
        ChronicSubscription::create([
            'subscription_code' => 'SUB-CHR-002',
            'patient_id' => $this->patient->id,
            'pharmacy_branch_id' => $this->branch->id,
            'drug_code' => 'GLIMEPIRIDE_2',
            'drug_name' => 'Glimepiride 2mg',
            'monthly_quantity' => 30,
            'unit_price_idr' => 2500,
            'next_refill_date' => Carbon::today()->addDays(20)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $dueList = $this->scheduler->getDueSubscriptions(advanceNoticeDays: 3);

        $this->assertCount(1, $dueList);
        $this->assertSame('SUB-CHR-001', $dueList->first()->subscription_code);
    }

    public function test_batch_refill_processing_creates_orders_and_advances_date(): void
    {
        $sub = ChronicSubscription::create([
            'subscription_code' => 'SUB-CHR-AUTO-01',
            'patient_id' => $this->patient->id,
            'pharmacy_branch_id' => $this->branch->id,
            'drug_code' => 'AMLODIPINE_10',
            'drug_name' => 'Amlodipine 10mg',
            'monthly_quantity' => 30,
            'unit_price_idr' => 2000,
            'next_refill_date' => Carbon::today()->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $result = $this->scheduler->processBatchRefills(advanceNoticeDays: 1);

        $this->assertSame(1, $result['processed_count']);
        $this->assertCount(1, $result['orders_created']);

        $sub->refresh();
        $this->assertSame(
            Carbon::today()->addDays(30)->toDateString(),
            $sub->next_refill_date->toDateString()
        );

        // Pastikan order e-pharmacy tercatat
        $createdOrder = EPharmacyOrder::where('order_code', $result['orders_created'][0]['order_code'])->first();
        $this->assertNotNull($createdOrder);
        $this->assertSame('AMLODIPINE_10', $createdOrder->drug_code);
        $this->assertSame(60000, (int) $createdOrder->total_price_idr);
        $this->assertTrue($createdOrder->is_chronic_subscription);
    }

    public function test_medication_possession_ratio_and_adherence_analysis(): void
    {
        $sub = ChronicSubscription::create([
            'subscription_code' => 'SUB-CHR-ADH-01',
            'patient_id' => $this->patient->id,
            'pharmacy_branch_id' => $this->branch->id,
            'drug_code' => 'METFORMIN_500',
            'drug_name' => 'Metformin HCl 500mg',
            'monthly_quantity' => 60,
            'unit_price_idr' => 1500,
            'next_refill_date' => Carbon::today()->addDays(15)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        // Simulasikan 3 bulan penebusan obat teratur (30 hari x 3 = 90 hari) dalam 90 hari observasi
        for ($i = 0; $i < 3; $i++) {
            EPharmacyOrder::create([
                'order_code' => 'EPO-ADH-'.$i,
                'patient_id' => $this->patient->id,
                'pharmacy_branch_id' => $this->branch->id,
                'drug_code' => 'METFORMIN_500',
                'drug_name' => 'Metformin HCl 500mg',
                'quantity' => 60,
                'total_price_idr' => 90000,
                'drug_classification' => 'REGULAR',
                'doctor_signature_hash' => hash('sha256', 'SIG-ADH-'.$i),
                'status' => 'DELIVERED',
                'delivery_status' => 'DELIVERED',
                'created_at' => Carbon::now()->subDays(25 * $i),
            ]);
        }

        $adherence = $this->adherenceAnalyzer->analyzeSubscriptionAdherence($sub, observationPeriodDays: 90);

        $this->assertSame(100.0, (float) $adherence['mpr_percentage']);
        $this->assertSame('OPTIMAL', $adherence['adherence_tier']);
        $this->assertFalse($adherence['requires_clinical_outreach']);
    }

    public function test_supply_chain_demand_forecasting_with_safety_buffer(): void
    {
        // 2 pasien berlangganan obat Metformin
        ChronicSubscription::create([
            'subscription_code' => 'SUB-FCS-01',
            'patient_id' => $this->patient->id,
            'pharmacy_branch_id' => $this->branch->id,
            'drug_code' => 'METFORMIN_500',
            'drug_name' => 'Metformin HCl 500mg',
            'monthly_quantity' => 60,
            'unit_price_idr' => 1000,
            'next_refill_date' => Carbon::today()->addDays(10)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        ChronicSubscription::create([
            'subscription_code' => 'SUB-FCS-02',
            'patient_id' => $this->patient->id,
            'pharmacy_branch_id' => $this->branch->id,
            'drug_code' => 'METFORMIN_500',
            'drug_name' => 'Metformin HCl 500mg',
            'monthly_quantity' => 40,
            'unit_price_idr' => 1000,
            'next_refill_date' => Carbon::today()->addDays(12)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $forecast = $this->forecaster->forecastDemand(horizonMonths: 3);

        $this->assertArrayHasKey('METFORMIN_500', $forecast);
        $item = $forecast['METFORMIN_500'];
        $this->assertSame(2, $item['active_subscribers']);
        $this->assertSame(100, $item['base_monthly_quantity']);
        // 100 * 3 bulan * 1.20 buffer = 360 unit, estimasi HPP pengadaan 70% = 252.000 IDR
        $this->assertSame(360, $item['forecast_total_with_safety_buffer']);
        $this->assertSame(252000, $item['estimated_procurement_cost_idr']);
    }
}

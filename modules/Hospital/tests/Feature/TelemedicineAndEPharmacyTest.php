<?php

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hospital\Application\Services\TelemedicineAndEPharmacyService;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use RuntimeException;
use Tests\TestCase;

class TelemedicineAndEPharmacyTest extends TestCase
{
    use RefreshDatabase;

    protected TelemedicineAndEPharmacyService $service;

    protected Patient $patient;

    protected PharmacyBranch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TelemedicineAndEPharmacyService::class);

        $this->patient = Patient::create([
            'mrn' => 'MRN-TELE-01',
            'name' => 'John Telemed Patient',
            'date_of_birth' => '1990-01-15',
            'blood_type' => 'O+',
            'encrypted_allergies' => ['PENICILLIN', 'ASPIRIN'],
            'encrypted_chronic_diagnoses' => ['HYPERTENSION'],
            'passport_hash' => hash('sha256', 'PASSPORT-MRN-TELE-01'),
        ]);

        $this->branch = PharmacyBranch::create([
            'branch_code' => 'PHARM-JKT-01',
            'name' => 'Apotek Pusat Jakarta',
            'type' => 'INTERNAL',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);

        // Register hospital ledger accounts
        $accounts = [
            'hsp:epharmacy_receivable:IDR' => 'asset',
            'hsp:epharmacy_revenue:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Hospital {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_104_1_book_tele_consultation(): void
    {
        $consult = $this->service->bookTeleConsult(
            patient: $this->patient,
            doctorId: 101,
            channelType: 'VIDEO',
            triageCategory: 'GREEN',
            chiefComplaint: 'Mild headache and sore throat for 2 days'
        );

        $this->assertNotNull($consult->consult_code);
        $this->assertEquals('SCHEDULED', $consult->status);
        $this->assertEquals('GREEN', $consult->triage_category);
    }

    public function test_104_6_a_prescription_without_doctor_signature_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing doctor digital signature hash');

        $this->service->issueEPrescriptionOrder(
            patient: $this->patient,
            branch: $this->branch,
            drugCode: 'PARACETAMOL',
            drugName: 'Paracetamol 500mg',
            quantity: 10,
            unitPriceIdr: 15_000,
            doctorSignatureHash: ''
        );
    }

    public function test_104_6_b_critical_drug_allergy_blocks_prescription(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Critical drug allergy detected');

        $this->service->issueEPrescriptionOrder(
            patient: $this->patient,
            branch: $this->branch,
            drugCode: 'PENICILLIN',
            drugName: 'Amoxicillin Trihydrate 500mg',
            quantity: 10,
            unitPriceIdr: 25_000,
            doctorSignatureHash: hash('sha256', 'DOC-SIG-101')
        );
    }

    public function test_104_6_c_and_e_valid_prescription_posts_balanced_ledger(): void
    {
        $order = $this->service->issueEPrescriptionOrder(
            patient: $this->patient,
            branch: $this->branch,
            drugCode: 'AMLODIPINE',
            drugName: 'Amlodipine 5mg',
            quantity: 30,
            unitPriceIdr: 5_000, // Total 150,000 IDR
            doctorSignatureHash: hash('sha256', 'DOC-SIG-101'),
            isChronicSubscription: true
        );

        $this->assertEquals('CONFIRMED', $order->status);
        $this->assertEquals(150_000, $order->total_price_idr);
        $this->assertTrue($order->is_chronic_subscription);

        $ar = LedgerAccount::where('code', 'hsp:epharmacy_receivable:IDR')->first();
        $rev = LedgerAccount::where('code', 'hsp:epharmacy_revenue:IDR')->first();

        $this->assertEquals('150000', (string) $ar->cached_balance);
        $this->assertEquals('-150000', (string) $rev->cached_balance);
    }

    public function test_104_6_d_narcotic_prescription_requires_second_doctor_and_signed_pod(): void
    {
        // 1. Narcotics without second doctor approval must fail
        try {
            $this->service->issueEPrescriptionOrder(
                patient: $this->patient,
                branch: $this->branch,
                drugCode: 'FENTANYL',
                drugName: 'Fentanyl Transdermal Patch 25mcg',
                quantity: 2,
                unitPriceIdr: 250_000,
                doctorSignatureHash: hash('sha256', 'DOC-SIG-101'),
                drugClassification: 'NARCOTIC_PSYCHOTROPIC'
            );
            $this->fail('Expected exception for missing second doctor approval');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('requires second doctor approval hash', $e->getMessage());
        }

        // 2. Issue with valid second doctor approval
        $order = $this->service->issueEPrescriptionOrder(
            patient: $this->patient,
            branch: $this->branch,
            drugCode: 'FENTANYL',
            drugName: 'Fentanyl Transdermal Patch 25mcg',
            quantity: 2,
            unitPriceIdr: 250_000,
            doctorSignatureHash: hash('sha256', 'DOC-SIG-101'),
            drugClassification: 'NARCOTIC_PSYCHOTROPIC',
            secondDoctorApprovalHash: hash('sha256', 'DOC-CHIEF-SIG-001')
        );

        $this->assertEquals('CONFIRMED', $order->status);

        // 3. Delivery without POD signature must fail for narcotics
        try {
            $this->service->completeDelivery($order, '');
            $this->fail('Expected exception for missing signed POD');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('requires signed proof of delivery', $e->getMessage());
        }

        // 4. Delivery with signed POD succeeds
        $delivered = $this->service->completeDelivery($order, hash('sha256', 'RECIPIENT-SIGNATURE-ID'));
        $this->assertEquals('DELIVERED', $delivered->status);
        $this->assertNotNull($delivered->pod_signature_hash);
    }
}

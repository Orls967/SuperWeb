<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Services\HospitalLabAndImagingService;
use Modules\Hospital\Application\Services\SpecimenCustodyChainService;
use Modules\Hospital\Domain\Models\LabSpecimen;
use Modules\Hospital\Domain\Models\Patient;
use Tests\TestCase;

class SpecimenCustodyAndOutsourcingTest extends TestCase
{
    use RefreshDatabase;

    private SpecimenCustodyChainService $custodyService;

    private HospitalLabAndImagingService $labService;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->custodyService = app(SpecimenCustodyChainService::class);
        $this->labService = app(HospitalLabAndImagingService::class);

        $this->patient = Patient::create([
            'mrn' => 'MRN-SPEC-01',
            'name' => 'Michael Specimen Patient',
            'date_of_birth' => '1988-11-05',
            'blood_type' => 'O+',
            'passport_hash' => hash('sha256', 'PASSPORT-MRN-SPEC-01'),
        ]);
    }

    public function test_three_hop_custody_chain_integrity_verification(): void
    {
        $specimen = $this->labService->collectSpecimen($this->patient, 'BLOOD');

        // Hop 1: Phlebotomist to Cold-Chain Courier
        $hop1 = $this->custodyService->recordHop(
            specimen: $specimen,
            hopNumber: 1,
            fromHandler: 'PHLEB-NURSE-01',
            toHandler: 'COURIER-LOG-04',
            notes: 'Sealed inside vacuum cold box at 4.0C'
        );

        $this->assertSame($specimen->fresh()->custody_hash, $hop1->custody_hash);

        // Hop 2: Cold-Chain Courier to Central Lab Intake
        $hop2 = $this->custodyService->recordHop(
            specimen: $specimen->fresh(),
            hopNumber: 2,
            fromHandler: 'COURIER-LOG-04',
            toHandler: 'LAB-INTAKE-02',
            notes: 'Intact vacuum seal verified'
        );

        // Hop 3: Central Lab Intake to Certifying Pathologist
        $hop3 = $this->custodyService->recordHop(
            specimen: $specimen->fresh(),
            hopNumber: 3,
            fromHandler: 'LAB-INTAKE-02',
            toHandler: 'PATHOLOGIST-DR-99',
            notes: 'Centrifuged and transferred for analysis'
        );

        // Verify cryptographic integrity of unbroken 3-hop chain
        $isValid = $this->custodyService->verifyChainIntegrity($specimen->fresh());
        $this->assertTrue($isValid);
    }

    public function test_tampered_custody_record_breaks_chain_verification(): void
    {
        $specimen = $this->labService->collectSpecimen($this->patient, 'SWAB');

        $hop1 = $this->custodyService->recordHop(
            specimen: $specimen,
            hopNumber: 1,
            fromHandler: 'CLINIC-OFFICER-01',
            toHandler: 'COURIER-LOG-01',
            notes: 'Viral transport media intact'
        );

        $hop2 = $this->custodyService->recordHop(
            specimen: $specimen->fresh(),
            hopNumber: 2,
            fromHandler: 'COURIER-LOG-01',
            toHandler: 'LAB-RECEIVER-01',
            notes: 'Received at reference lab'
        );

        // Simulate illegal tampering of intermediate hash in database
        $hop1->update([
            'custody_hash' => hash('sha256', 'MALICIOUS_TAMPERED_RECORD'),
        ]);

        $isValid = $this->custodyService->verifyChainIntegrity($specimen->fresh());
        $this->assertFalse($isValid);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CircularityWasteProgramService;
use Tests\TestCase;

class CircularityWasteProgramTest extends TestCase
{
    use RefreshDatabase;

    protected CircularityWasteProgramService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CircularityWasteProgramService::class);
    }

    public function test_waste_disposal_request_and_vendor_payment_flow(): void
    {
        // 443.1 Submit recycling waste request
        $req = $this->service->submitDisposalRequest(
            requestCode: 'WST-RECYCLE-PLASTIC-01',
            wasteStream: 'packaging_plastic',
            hierarchyLevel: 'recycle',
            quantityTonnes: 15.50,
            costPerTonne: 1200000.00
        );

        $this->assertEquals('WST-RECYCLE-PLASTIC-01', $req->request_code);
        $this->assertEquals('recycle', $req->hierarchy_level);

        // 443.2 Record vendor payment with treatment certificate
        $pay = $this->service->recordVendorPayment(
            paymentCode: 'PAY-ENV-VENDOR-01',
            vendorCode: 'VND-WASTE-MANAGEMENT-INDONESIA',
            treatmentCertNo: 'KLHK-CERT-2026-889912',
            amount: 18600000.00
        );

        $this->assertFalse((bool) $pay->payment_released);

        // Release payment with verified certificate
        $released = $this->service->releaseVendorPayment('PAY-ENV-VENDOR-01', true);
        $this->assertTrue((bool) $released->payment_released);
        $this->assertTrue((bool) $released->treatment_certificate_verified);

        // 443.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_unjustified_disposal_and_unverified_payment_blocked_edge_cases(): void
    {
        // 443.1 & 443.4 Direct disposal without justification or emergency approval is blocked
        try {
            $this->service->submitDisposalRequest(
                requestCode: 'WST-LANDFILL-DIRECT',
                wasteStream: 'industrial_scrap',
                hierarchyLevel: 'direct_dispose',
                quantityTonnes: 5.0,
                costPerTonne: 800000,
                justification: null, // Missing!
                emergencyApproval: false
            );
            $this->fail('Expected exception for unjustified direct disposal');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Direct disposal requires formal waste hierarchy non-viability justification', $e->getMessage());
        }

        // 443.5 Emergency disposal with emergency approval succeeds
        $emergencyReq = $this->service->submitDisposalRequest(
            requestCode: 'WST-EMERGENCY-LANDFILL',
            wasteStream: 'hazardous_spill',
            hierarchyLevel: 'direct_dispose',
            quantityTonnes: 2.0,
            costPerTonne: 2500000,
            justification: 'Immediate biological hazard containment',
            emergencyApproval: true
        );
        $this->assertTrue((bool) $emergencyReq->emergency_approval_granted);

        // 443.2 Unverified certificate blocks vendor payment
        $this->service->recordVendorPayment('PAY-NO-CERT', 'VND-BAD', 'DUMMY-CERT', 5000000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vendor payment requires verified environmental treatment/manifest certificate');

        $this->service->releaseVendorPayment('PAY-NO-CERT', false);
    }
}

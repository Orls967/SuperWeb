<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use DateTimeImmutable;
use Modules\Hospital\Domain\Services\HospitalSurgicalImplantTraceabilityService;
use PHPUnit\Framework\TestCase;

class HospitalSurgicalImplantTraceabilityServiceTest extends TestCase
{
    private HospitalSurgicalImplantTraceabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HospitalSurgicalImplantTraceabilityService;
    }

    public function test_verifies_valid_sterile_surgical_implant(): void
    {
        $opDate = new DateTimeImmutable('2026-11-10');
        $expiryDate = new DateTimeImmutable('2028-11-10');

        $result = $this->service->verifyImplantForSurgery(
            '01088812345678901728111010LOT9981',
            'KEMENKES RI AKL 30501918234',
            HospitalSurgicalImplantTraceabilityService::IMPLANT_CATEGORY_ORTHOPEDIC,
            $expiryDate,
            $opDate,
            [],
            'LOT9981'
        );

        $this->assertSame(HospitalSurgicalImplantTraceabilityService::STATUS_VERIFIED_STERILE, $result['status']);
        $this->assertTrue($result['is_approved_for_implantation']);
        $this->assertGreaterThan(700, $result['days_remaining_sterility']);
        $this->assertStringStartsWith('IMP-TRACE-', $result['traceability_audit_token']);
        $this->assertNull($result['rejection_reason']);
    }

    public function test_rejects_recalled_batch_implant(): void
    {
        $opDate = new DateTimeImmutable('2026-11-10');
        $expiryDate = new DateTimeImmutable('2028-11-10');

        $result = $this->service->verifyImplantForSurgery(
            '01088812345678901728111010RECALL88',
            'KEMENKES RI AKL 30501918234',
            HospitalSurgicalImplantTraceabilityService::IMPLANT_CATEGORY_CARDIAC_STENT,
            $expiryDate,
            $opDate,
            ['RECALL88', 'BADLOT01'],
            'RECALL88'
        );

        $this->assertSame(HospitalSurgicalImplantTraceabilityService::STATUS_RECALLED_BATCH, $result['status']);
        $this->assertFalse($result['is_approved_for_implantation']);
        $this->assertStringContainsString('penarikan produk', $result['rejection_reason']);
    }

    public function test_rejects_expired_sterility_implant(): void
    {
        $opDate = new DateTimeImmutable('2026-11-10');
        $expiryDate = new DateTimeImmutable('2026-10-01'); // sudah expired

        $result = $this->service->verifyImplantForSurgery(
            '01088812345678901726100110EXPIRED01',
            'KEMENKES RI AKD 20501918234',
            HospitalSurgicalImplantTraceabilityService::IMPLANT_CATEGORY_OPHTHALMIC_LENS,
            $expiryDate,
            $opDate,
            [],
            'EXPIRED01'
        );

        $this->assertSame(HospitalSurgicalImplantTraceabilityService::STATUS_EXPIRED_STERILITY, $result['status']);
        $this->assertFalse($result['is_approved_for_implantation']);
        $this->assertLessThan(0, $result['days_remaining_sterility']);
    }
}

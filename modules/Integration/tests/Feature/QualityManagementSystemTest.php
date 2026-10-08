<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\QualityManagementSystemService;
use Tests\TestCase;

/**
 * Fase 213 — Operasi: Quality Management System 30 Lini Tests
 *
 * Covers:
 *  (a) CAPA tracking resolves cleanly on effective action
 *  (b) CAPA overdue auto-escalates when target date passed without resolution
 *  (c) customer complaint requires ledger credit reference if monetary compensation is given
 *  (d) quality:audit = 0 discrepancy
 */
class QualityManagementSystemTest extends TestCase
{
    use RefreshDatabase;

    protected QualityManagementSystemService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QualityManagementSystemService::class);
    }

    /**
     * (a) & (b) CAPA creation, verification, and escalation.
     */
    public function test_capa_lifecycle_and_escalation(): void
    {
        // 1. Effective verification -> VERIFIED_CLOSED
        $capa1 = $this->service->createCapa(
            'L07_FOOD_PROCESSING',
            'HACCP',
            'Deviasi suhu cold storage unit #3',
            'Kompresor sekunder mengalami penurunan tekanan freon',
            'Pergantian sensor tekanan dan servis kompresor berkala',
            Carbon::now()->addDays(7)
        );
        $this->assertSame('OPEN', $capa1->status);

        $closed = $this->service->verifyCapaCompletion($capa1->capa_code, true);
        $this->assertSame('VERIFIED_CLOSED', $closed->status);

        // 2. Overdue without effective action -> OVERDUE_ESCALATED
        $capa2 = $this->service->createCapa(
            'L11_HOSPITALITY',
            'HOTEL_STAR_STD',
            'Water heater room 401 tidak mencapai suhu standar',
            'Pemanas kerak kalsium tinggi',
            'Descaling unit',
            Carbon::now()->subDays(2) // Overdue date
        );

        $escalated = $this->service->verifyCapaCompletion($capa2->capa_code, false);
        $this->assertSame('OVERDUE_ESCALATED', $escalated->status);
    }

    /**
     * (c) Customer complaint compensation ledger linkage.
     */
    public function test_complaint_compensation_ledger_linkage(): void
    {
        // 1. Complaint with compensation but no ledger ref -> Exception
        try {
            $this->service->resolveComplaint('MOBILE', 'Keterlambatan pengiriman makanan', 150000.0, null);
            $this->fail('Expected exception for unlinked compensation.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Monetary compensation requires a valid ledger credit reference', $e->getMessage());
        }

        // 2. Complaint with valid ledger credit voucher -> RESOLVED
        $cmp = $this->service->resolveComplaint('MOBILE', 'Keterlambatan pengiriman makanan', 150000.0, 'VOUCHER-CREDIT-7718');
        $this->assertSame('RESOLVED', $cmp->resolution_status);
        $this->assertSame('VOUCHER-CREDIT-7718', $cmp->ledger_credit_reference);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_quality_management_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}

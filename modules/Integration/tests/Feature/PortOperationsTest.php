<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\PortOperationsService;
use Tests\TestCase;

/**
 * Fase 179 — Ports, Marine Terminals & Trade Facilitation Tests
 *
 * Covers:
 *  (a) berth schedule overlap rejected
 *  (b) yard block capacity enforced
 *  (c) reefer temperature excursion detection (>4°C)
 *  (d) terminal dues invoice calculation
 *  (e) port:audit = 0 discrepancy
 */
class PortOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected PortOperationsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PortOperationsService::class);
    }

    /**
     * (a) Overlapping berth reservation is rejected.
     */
    public function test_berth_overlap_rejection(): void
    {
        $now = Carbon::now();

        // Reserve Berth 01 from now until now + 24 hours
        $res1 = $this->service->reserveBerth('BERTH-01', 'MV Evergreen Hope', $now, $now->copy()->addHours(24));
        $this->assertSame('BERTH-01', $res1->berth_number);

        // Overlapping reservation for Berth 01 from now + 12h until now + 36h -> Exception
        $this->expectException(\RuntimeException::class);
        $this->service->reserveBerth('BERTH-01', 'MV Maersk Pearl', $now->copy()->addHours(12), $now->copy()->addHours(36));
    }

    /**
     * (b) Yard block capacity limit enforced.
     */
    public function test_yard_block_capacity_limit(): void
    {
        // Yard Block A with capacity of 2 containers
        $this->service->allocateYardSlot('BLOCK-A', 'CONT-001', 2);
        $this->service->allocateYardSlot('BLOCK-A', 'CONT-002', 2);

        // Third container in Block A -> Exception
        $this->expectException(\RuntimeException::class);
        $this->service->allocateYardSlot('BLOCK-A', 'CONT-003', 2);
    }

    /**
     * (c) Reefer container temperature excursion detected.
     */
    public function test_reefer_temperature_excursion(): void
    {
        // 1. Safe frozen reefer (-18°C)
        $goodReefer = $this->service->allocateYardSlot('BLOCK-REEFER', 'REEF-001', 50, true, -18.0);
        $this->assertFalse((bool) $goodReefer->reefer_temp_excursion);

        // 2. High temp excursion (9.5°C > 4.0°C)
        $badReefer = $this->service->allocateYardSlot('BLOCK-REEFER', 'REEF-002', 50, true, 9.5);
        $this->assertTrue((bool) $badReefer->reefer_temp_excursion);
    }

    /**
     * (d) Terminal moves and port dues invoice calculation.
     */
    public function test_terminal_invoice_calculation(): void
    {
        // 50 crane moves @ Rp 1,200,000 + Rp 15,000,000 port dues = Rp 75,000,000
        $invoice = $this->service->generateTerminalInvoice('RES-001', 50, 1200000.0, 15000000.0);

        $this->assertEquals(75000000.00, (float) $invoice->total_amount_idr);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_port_operations_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}

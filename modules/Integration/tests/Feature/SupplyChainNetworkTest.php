<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\SupplyChainNetworkService;
use Tests\TestCase;

/**
 * Fase 215 — Operasi: Supply Chain Execution & Warehouse Network 30 Lini Tests
 *
 * Covers:
 *  (a) multi-echelon allocation validates allocated quantity does not exceed supply
 *  (b) excessive allocation throws exception
 *  (c) dock scheduling rejects conflicting overlapping slot appointment
 *  (d) wms:audit = 0 discrepancy
 */
class SupplyChainNetworkTest extends TestCase
{
    use RefreshDatabase;

    protected SupplyChainNetworkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplyChainNetworkService::class);
    }

    /**
     * (a) & (b) Multi-echelon stock deployment allocation limit.
     */
    public function test_echelon_allocation_supply_conservation(): void
    {
        // 1. Valid allocation: Request 350 <= Available 500 -> SUCCESS
        $a1 = $this->service->allocateStock('SKU-TIRE-17INCH', 'CENTRAL_DC_JKT', 500, 350);
        $this->assertTrue((bool) $a1->allocation_valid);
        $this->assertSame(350, (int) $a1->allocated_quantity);

        // 2. Excess allocation: Request 600 > Available 500 -> Exception
        try {
            $this->service->allocateStock('SKU-TIRE-17INCH', 'CENTRAL_DC_JKT', 500, 600);
            $this->fail('Expected exception for allocation exceeding supply.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('exceeds available stock supply', $e->getMessage());
        }
    }

    /**
     * (c) Dock appointment conflict rejection.
     */
    public function test_dock_appointment_conflict_rejection(): void
    {
        // 1. Initial appointment -> SUCCESS
        $app1 = $this->service->scheduleDockAppointment('DC-CIKARANG', 'DOOR-04', '2026-10-15 08:00-10:00', 'CARRIER-LOGISTIK-01');
        $this->assertSame('SCHEDULED', $app1->status);

        // 2. Conflicting same slot -> Exception
        try {
            $this->service->scheduleDockAppointment('DC-CIKARANG', 'DOOR-04', '2026-10-15 08:00-10:00', 'CARRIER-CARGO-02');
            $this->fail('Expected exception for conflicting dock slot booking.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Dock scheduling conflict', $e->getMessage());
        }
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_supply_chain_network_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}

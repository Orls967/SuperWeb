<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ConcurrencyLockingService;
use Tests\TestCase;

/**
 * Fase 193 — Skala: Concurrency, Locking & Contention Management Tests
 *
 * Covers:
 *  (a) contention resource capacity conservation (zero over-allocation)
 *  (b) optimistic concurrency control conflict detection & rejection on version mismatch
 *  (c) admission control rate shed blocks excess requests
 *  (d) concurrency:audit = 0 discrepancy
 */
class ConcurrencyLockingTest extends TestCase
{
    use RefreshDatabase;

    protected ConcurrencyLockingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConcurrencyLockingService::class);
    }

    /**
     * (a) Resource contention capacity allocation safety.
     */
    public function test_contention_resource_capacity_conservation(): void
    {
        // 1. Initial allocation: 40 out of 100 seats
        $res = $this->service->allocateResource('FLIGHT_SEAT', 'GA-102-ECONOMY', 100, 40);
        $this->assertSame(40, (int) $res->allocated_units);

        // 2. Second allocation: 50 additional units -> 90 total <= 100
        $res2 = $this->service->allocateResource('FLIGHT_SEAT', 'GA-102-ECONOMY', 100, 50);
        $this->assertSame(90, (int) $res2->allocated_units);

        // 3. Excess allocation: 20 units (90 + 20 = 110 > 100) -> Exception
        try {
            $this->service->allocateResource('FLIGHT_SEAT', 'GA-102-ECONOMY', 100, 20);
            $this->fail('Expected exception for capacity over-allocation.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Resource capacity exceeded', $e->getMessage());
        }
    }

    /**
     * (b) Optimistic concurrency control version conflict detection.
     */
    public function test_optimistic_concurrency_version_conflict(): void
    {
        $doc = $this->service->createOptimisticDocument('CTR-DRAFT-001', 'Initial draft terms');
        $this->assertSame(1, (int) $doc->version);

        // 1. Concurrent update with correct version 1 -> SUCCESS (advances to version 2)
        $updated = $this->service->updateOptimisticDocument('CTR-DRAFT-001', 'Reviewed draft terms', 1);
        $this->assertSame(2, (int) $updated->version);

        // 2. Stale update with version 1 -> CONFLICT EXCEPTION
        try {
            $this->service->updateOptimisticDocument('CTR-DRAFT-001', 'Stale edit terms', 1);
            $this->fail('Expected exception for optimistic concurrency version conflict.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Optimistic concurrency conflict', $e->getMessage());
        }
    }

    /**
     * (c) Admission control rate shedding on peak traffic.
     */
    public function test_admission_control_rate_shedding(): void
    {
        $endpoint = 'FLASHSALE-TICKETS';

        // 1. Initial requests within threshold (90 <= 100) -> Admitted
        $this->assertTrue($this->service->admitRequest($endpoint, 100, 90));

        // 2. Excess requests that surpass max limit (90 + 20 = 110 > 100) -> Shed (rejected)
        $this->assertFalse($this->service->admitRequest($endpoint, 100, 20));
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_concurrency_locking_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EventDrivenCqrsSagaService;
use Tests\TestCase;

class EventDrivenCqrsSagaTest extends TestCase
{
    use RefreshDatabase;

    protected EventDrivenCqrsSagaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EventDrivenCqrsSagaService::class);
    }

    public function test_cqrs_event_journal_and_idempotent_projection_rebuild(): void
    {
        // 1. Append events to journal (257.1)
        $this->service->appendEventToJournal('BOOKING-7001', 'BOOKING_CREATED', ['amount' => 500.0]);
        $this->service->appendEventToJournal('BOOKING-7001', 'ITEM_RESERVED', ['amount' => 250.0]);
        $this->service->appendEventToJournal('BOOKING-7001', 'ITEM_RESERVED', ['amount' => 150.0]);

        // 2. Rebuild projection (257.1 & 257.4)
        $projection = $this->service->rebuildProjectionFromEvents('BOOKING-7001', 900.0);

        $this->assertEquals(3, (int) $projection->total_booked_count);
        $this->assertEquals(900.0, (float) $projection->total_amount_usd);
        $this->assertFalse((bool) $projection->has_divergence);
        $this->assertNull($projection->divergence_notes);
    }

    public function test_projection_rebuild_divergence_detection_and_reporting(): void
    {
        // Append 2 items totaling $300
        $this->service->appendEventToJournal('BOOKING-DIV-01', 'ITEM_RESERVED', ['amount' => 200.0]);
        $this->service->appendEventToJournal('BOOKING-DIV-01', 'ITEM_RESERVED', ['amount' => 100.0]);

        // Expected amount is $500, but events only total $300 -> flags divergence (257.5 Edge Case)
        $projection = $this->service->rebuildProjectionFromEvents('BOOKING-DIV-01', 500.0);

        $this->assertTrue((bool) $projection->has_divergence);
        $this->assertNotNull($projection->divergence_notes);
        $this->assertStringContainsString('DIVERGENCE DETECTED', $projection->divergence_notes);
    }

    public function test_saga_orchestration_step_progression_and_clean_compensation(): void
    {
        // 1. Step 1: Flight Reservation
        $saga = $this->service->executeSagaStep('SAGA-HOLIDAY-01', 'BUNDLE_TRAVEL', 'STEP_1_FLIGHT_BOOKED');
        $this->assertEquals('RUNNING', $saga->status);

        // 2. Step 2: Hotel Reservation
        $saga = $this->service->executeSagaStep('SAGA-HOLIDAY-01', 'BUNDLE_TRAVEL', 'STEP_2_HOTEL_BOOKED');
        $this->assertEquals('RUNNING', $saga->status);

        // 3. Step 3: Payment fails -> triggers clean compensation rollback (257.2 & 257.4)
        $compensatedSaga = $this->service->executeSagaStep('SAGA-HOLIDAY-01', 'BUNDLE_TRAVEL', 'STEP_3_PAYMENT', true);
        $this->assertEquals('COMPENSATED', $compensatedSaga->status);

        $log = json_decode($compensatedSaga->compensation_log_json, true);
        $this->assertContains('RELEASE_HOTEL_INVENTORY', $log['compensated_actions']);
        $this->assertContains('RELEASE_FLIGHT_SEAT', $log['compensated_actions']);
    }

    public function test_saga_timeout_policy_versioning_and_owner_approval(): void
    {
        $this->service->executeSagaStep('SAGA-POLICY-TEST', 'SUPPLY_CHAIN', 'STEP_1');

        // 1. Changing policy without approval fails (257.7)
        try {
            $this->service->updateSagaTimeoutPolicy('SAGA-POLICY-TEST', 600, 2, false);
            $this->fail('Expected exception for unapproved timeout change');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires domain owner approval', $e->getMessage());
        }

        // 2. Succeeds with domain owner approval
        $updated = $this->service->updateSagaTimeoutPolicy('SAGA-POLICY-TEST', 600, 2, true);
        $this->assertEquals(600, (int) $updated->timeout_seconds);
        $this->assertEquals(2, (int) $updated->timeout_policy_version);
    }

    public function test_event_schema_compatibility_gate_blocks_short_deprecation_windows(): void
    {
        // 1. Breaking change with < 90 days deprecation rejected (257.3 & 257.4)
        try {
            $this->service->validateSchemaCompatibility('PaymentEventV2', 2, true, 30);
            $this->fail('Expected exception for short deprecation window');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires at least 90-day deprecation window', $e->getMessage());
        }

        // 2. Breaking change with 90 days passes
        $gate = $this->service->validateSchemaCompatibility('PaymentEventV2', 2, true, 90);
        $this->assertTrue((bool) $gate->gate_approved);
    }

    public function test_eda_cqrs_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->appendEventToJournal('AGG-AUD', 'ITEM_RESERVED', ['amount' => 10.0]);
        $this->service->rebuildProjectionFromEvents('AGG-AUD', 10.0);
        $this->service->executeSagaStep('SAGA-AUD', 'TX', 'STEP_1');
        $this->service->validateSchemaCompatibility('SchemaA', 1, false, 90);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: divergent projection
        DB::table('eda_cqrs_projections')->insert([
            'aggregate_id' => 'AGG-DRIFTED',
            'total_booked_count' => 1,
            'total_amount_usd' => 100.0,
            'rebuilt_count' => 1,
            'has_divergence' => true, // Discrepancy!
            'divergence_notes' => 'Silent drift detected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}

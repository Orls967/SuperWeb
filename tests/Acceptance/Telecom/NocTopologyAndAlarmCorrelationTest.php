<?php

declare(strict_types=1);

namespace Tests\Acceptance\Telecom;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\TopologySuppressorVerifier;
use Modules\Tlx\Application\Services\NocCorrelationService;
use Modules\Tlx\Domain\Models\NocAlarm;
use Modules\Tlx\Domain\Models\NocTicket;
use RuntimeException;
use Tests\TestCase;

class NocTopologyAndAlarmCorrelationTest extends TestCase
{
    use RefreshDatabase;

    protected NocCorrelationService $nocService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->nocService = app(NocCorrelationService::class);
    }

    public function test_network_elements_form_a_directed_acyclic_graph(): void
    {
        $root = $this->nocService->createElement([
            'element_code' => 'RTR-CORE-01',
            'element_type' => 'CORE_ROUTER',
            'parent_element_id' => null,
            'status' => 'OPERATIONAL',
        ]);

        $child = $this->nocService->createElement([
            'element_code' => 'SW-AGG-01',
            'element_type' => 'AGGREGATION_SWITCH',
            'parent_element_id' => $root->id,
            'status' => 'OPERATIONAL',
        ]);

        expect($child->parent_element_id)->toBe($root->id)
            ->and($root->parent_element_id)->toBeNull();
    }

    public function test_cycle_detection_in_network_topology_is_rejected(): void
    {
        $nodeA = $this->nocService->createElement([
            'element_code' => 'NODE-A',
            'element_type' => 'CORE_ROUTER',
            'parent_element_id' => null,
            'status' => 'OPERATIONAL',
        ]);

        $nodeB = $this->nocService->createElement([
            'element_code' => 'NODE-B',
            'element_type' => 'AGGREGATION_SWITCH',
            'parent_element_id' => $nodeA->id,
            'status' => 'OPERATIONAL',
        ]);

        // Attempting to make nodeA's parent nodeB creates a cycle (A -> B -> A)
        $this->expectException(InvalidArgumentException::class);
        $this->nocService->setElementParent($nodeA->id, $nodeB->id);
    }

    public function test_noc_alarms_are_append_only_and_immutable(): void
    {
        $node = $this->nocService->createElement([
            'element_code' => 'NODE-IMM',
            'element_type' => 'SITE',
            'parent_element_id' => null,
            'status' => 'OPERATIONAL',
        ]);

        $alarm = $this->nocService->ingestAlarm([
            'alarm_code' => 'ALM-IMM-01',
            'element_id' => $node->id,
            'alarm_type' => 'POWER_FAIL',
            'severity' => 'CRITICAL',
            'raised_at' => '2026-10-01 10:00:00',
        ]);

        expect($alarm->alarm_code)->toBe('ALM-IMM-01');

        $this->expectException(RuntimeException::class);
        $alarm->update(['severity' => 'MINOR']);
    }

    public function test_noc_alarm_deletion_is_strictly_forbidden(): void
    {
        $node = $this->nocService->createElement([
            'element_code' => 'NODE-NODEL',
            'element_type' => 'SITE',
            'parent_element_id' => null,
            'status' => 'OPERATIONAL',
        ]);

        $alarm = $this->nocService->ingestAlarm([
            'alarm_code' => 'ALM-NODEL-01',
            'element_id' => $node->id,
            'alarm_type' => 'LINK_DOWN',
            'severity' => 'MAJOR',
            'raised_at' => '2026-10-01 10:05:00',
        ]);

        $this->expectException(RuntimeException::class);
        $alarm->delete();
    }

    public function test_topological_suppression_suppresses_child_alarms_when_parent_is_down(): void
    {
        // Hierarchy: Root Router -> Agg Switch -> Cell Tower
        $root = $this->nocService->createElement([
            'element_code' => 'RTR-ROOT',
            'element_type' => 'CORE_ROUTER',
            'status' => 'DOWN',
        ]);

        $agg = $this->nocService->createElement([
            'element_code' => 'SW-CHILD',
            'element_type' => 'AGGREGATION_SWITCH',
            'parent_element_id' => $root->id,
            'status' => 'DOWN',
        ]);

        $tower = $this->nocService->createElement([
            'element_code' => 'TWR-LEAF',
            'element_type' => 'CELL_TOWER',
            'parent_element_id' => $agg->id,
            'status' => 'DOWN',
        ]);

        // Raise alarms for all 3
        $this->nocService->ingestAlarm([
            'alarm_code' => 'ALM-ROOT',
            'element_id' => $root->id,
            'alarm_type' => 'POWER_FAIL',
            'severity' => 'CRITICAL',
            'raised_at' => '2026-10-01 11:00:00',
        ]);

        $this->nocService->ingestAlarm([
            'alarm_code' => 'ALM-CHILD',
            'element_id' => $agg->id,
            'alarm_type' => 'BGP_DOWN',
            'severity' => 'CRITICAL',
            'raised_at' => '2026-10-01 11:01:00',
        ]);

        $this->nocService->ingestAlarm([
            'alarm_code' => 'ALM-LEAF',
            'element_id' => $tower->id,
            'alarm_type' => 'LINK_DOWN',
            'severity' => 'MAJOR',
            'raised_at' => '2026-10-01 11:02:00',
        ]);

        $correlationResult = $this->nocService->correlateActiveAlarms();

        $rootAlarm = NocAlarm::where('alarm_code', 'ALM-ROOT')->first();
        $childAlarm = NocAlarm::where('alarm_code', 'ALM-CHILD')->first();
        $leafAlarm = NocAlarm::where('alarm_code', 'ALM-LEAF')->first();

        expect($rootAlarm->is_suppressed)->toBeFalse()
            ->and($childAlarm->is_suppressed)->toBeTrue()
            ->and($childAlarm->suppressed_by_element_id)->toBe($root->id)
            ->and($leafAlarm->is_suppressed)->toBeTrue()
            ->and($leafAlarm->suppressed_by_element_id)->toBe($root->id);
    }

    public function test_incident_tickets_created_only_for_unsuppressed_root_cause_alarms(): void
    {
        $root = $this->nocService->createElement([
            'element_code' => 'RTR-TICKET-ROOT',
            'element_type' => 'CORE_ROUTER',
            'status' => 'DOWN',
        ]);

        $child = $this->nocService->createElement([
            'element_code' => 'SW-TICKET-CHILD',
            'element_type' => 'AGGREGATION_SWITCH',
            'parent_element_id' => $root->id,
            'status' => 'DOWN',
        ]);

        $this->nocService->ingestAlarm([
            'alarm_code' => 'ALM-TKT-ROOT',
            'element_id' => $root->id,
            'alarm_type' => 'HARDWARE_FAIL',
            'severity' => 'CRITICAL',
            'raised_at' => '2026-10-01 12:00:00',
        ]);

        $this->nocService->ingestAlarm([
            'alarm_code' => 'ALM-TKT-CHILD',
            'element_id' => $child->id,
            'alarm_type' => 'LINK_DOWN',
            'severity' => 'MAJOR',
            'raised_at' => '2026-10-01 12:01:00',
        ]);

        $tickets = $this->nocService->correlateActiveAlarms();

        // Only 1 ticket created (for the root element), child alarm is suppressed without ticket
        expect($tickets)->toHaveCount(1)
            ->and($tickets[0]->root_element_id)->toBe($root->id);
    }

    public function test_mttr_calculation_and_sla_breach_tracking(): void
    {
        $root = $this->nocService->createElement([
            'element_code' => 'RTR-MTTR',
            'element_type' => 'CORE_ROUTER',
            'status' => 'OPERATIONAL',
        ]);

        $ticket = NocTicket::create([
            'id' => (string) Str::uuid(),
            'ticket_number' => 'TCK-202610-001',
            'root_element_id' => $root->id,
            'severity' => 'CRITICAL',
            'status' => 'OPEN',
            'sla_target_minutes' => 60, // 1 hour SLA target
            'is_sla_breached' => false,
            'opened_at' => '2026-10-01 08:00:00',
        ]);

        // Resolved at 09:30:00 (90 minutes -> breaches 60 min SLA)
        $resolvedTicket = $this->nocService->resolveTicket(
            $ticket->id,
            '2026-10-01 09:30:00',
            'Power supply module replaced'
        );

        expect($resolvedTicket->status)->toBe('RESOLVED')
            ->and($resolvedTicket->mttr_minutes)->toBe(90)
            ->and($resolvedTicket->is_sla_breached)->toBeTrue();
    }

    public function test_mttr_compliant_when_resolved_within_sla(): void
    {
        $root = $this->nocService->createElement([
            'element_code' => 'RTR-COMPLIANT',
            'element_type' => 'CORE_ROUTER',
            'status' => 'OPERATIONAL',
        ]);

        $ticket = NocTicket::create([
            'id' => (string) Str::uuid(),
            'ticket_number' => 'TCK-202610-002',
            'root_element_id' => $root->id,
            'severity' => 'MAJOR',
            'status' => 'OPEN',
            'sla_target_minutes' => 120, // 2 hours SLA
            'is_sla_breached' => false,
            'opened_at' => '2026-10-01 10:00:00',
        ]);

        // Resolved in 45 minutes
        $resolvedTicket = $this->nocService->resolveTicket(
            $ticket->id,
            '2026-10-01 10:45:00',
            'BGP session flapped and restored'
        );

        expect($resolvedTicket->mttr_minutes)->toBe(45)
            ->and($resolvedTicket->is_sla_breached)->toBeFalse();
    }

    public function test_topology_suppression_produces_verifiable_certificate(): void
    {
        $root = $this->nocService->createElement([
            'element_code' => 'RTR-CERT-ROOT',
            'element_type' => 'CORE_ROUTER',
            'status' => 'DOWN',
        ]);

        $child = $this->nocService->createElement([
            'element_code' => 'SW-CERT-CHILD',
            'element_type' => 'AGGREGATION_SWITCH',
            'parent_element_id' => $root->id,
            'status' => 'DOWN',
        ]);

        $this->nocService->ingestAlarm([
            'alarm_code' => 'ALM-CERT-ROOT',
            'element_id' => $root->id,
            'alarm_type' => 'HARDWARE_FAIL',
            'severity' => 'CRITICAL',
            'raised_at' => '2026-10-01 14:00:00',
        ]);

        $this->nocService->ingestAlarm([
            'alarm_code' => 'ALM-CERT-CHILD',
            'element_id' => $child->id,
            'alarm_type' => 'LINK_DOWN',
            'severity' => 'MAJOR',
            'raised_at' => '2026-10-01 14:01:00',
        ]);

        $this->nocService->correlateActiveAlarms();

        $certificate = $this->nocService->generateSuppressionCertificate();
        $isValid = TopologySuppressorVerifier::verify($certificate);

        expect($isValid)->toBeTrue();
    }

    public function test_correlate_alarms_artisan_command_executes(): void
    {
        $exitCode = Artisan::call('tlx:correlate-alarms');
        expect($exitCode)->toBe(0);
    }
}

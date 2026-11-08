<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use DomainException;
use Modules\Marine\Domain\StateMachines\AquacultureBatchStateMachine;
use Modules\Marine\Domain\StateMachines\ExportShipmentStateMachine;
use Tests\TestCase;

class AquacultureStateMachineTest extends TestCase
{
    public function test_batch_lifecycle_valid_transitions(): void
    {
        $sm = new AquacultureBatchStateMachine;

        $this->assertTrue($sm->canTransition(
            AquacultureBatchStateMachine::STATUS_HATCHERY_STOCKED,
            AquacultureBatchStateMachine::STATUS_GROWOUT_PHASE
        ));

        $next = $sm->transition(
            AquacultureBatchStateMachine::STATUS_GROWOUT_PHASE,
            AquacultureBatchStateMachine::STATUS_PRE_HARVEST_TESTING
        );
        $this->assertEquals(AquacultureBatchStateMachine::STATUS_PRE_HARVEST_TESTING, $next);
    }

    public function test_batch_lifecycle_illegal_transition_throws_domain_exception(): void
    {
        $sm = new AquacultureBatchStateMachine;

        $this->expectException(DomainException::class);
        $sm->transition(
            AquacultureBatchStateMachine::STATUS_HATCHERY_STOCKED,
            AquacultureBatchStateMachine::STATUS_HARVESTED
        );
    }

    public function test_export_shipment_valid_and_illegal_transitions(): void
    {
        $sm = new ExportShipmentStateMachine;

        $this->assertTrue($sm->canTransition(
            ExportShipmentStateMachine::STATUS_ESCROW_LOCKED,
            ExportShipmentStateMachine::STATUS_INSPECTION_PASSED
        ));

        $this->expectException(DomainException::class);
        $sm->transition(
            ExportShipmentStateMachine::STATUS_DRAFT_BOOKED,
            ExportShipmentStateMachine::STATUS_PORT_DELIVERED
        );
    }
}

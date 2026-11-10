<?php

declare(strict_types=1);

namespace Tests\Unit\StateMachines;

use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Domain\Models\CoreSearchIndexJob;
use Modules\Core\Domain\StateMachines\IndexJobStateMachine;
use Tests\TestCase;

class IndexJobStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_8_statuses_can_be_transitioned_validly(): void
    {
        $job = CoreSearchIndexJob::create([
            'id' => 'job-test-state-machine',
            'job_type' => 'full_reindex',
            'module' => 'Contract',
            'status' => IndexJobStateMachine::STATUS_PENDING,
        ]);

        $sm = new IndexJobStateMachine($job);

        // pending -> queued
        $sm->transitionTo(IndexJobStateMachine::STATUS_QUEUED);
        $this->assertEquals(IndexJobStateMachine::STATUS_QUEUED, $sm->getCurrentStatus());

        // queued -> processing
        $sm->transitionTo(IndexJobStateMachine::STATUS_PROCESSING);
        $this->assertEquals(IndexJobStateMachine::STATUS_PROCESSING, $sm->getCurrentStatus());
        $this->assertNotNull($job->started_at);

        // processing -> reindexing
        $sm->transitionTo(IndexJobStateMachine::STATUS_REINDEXING);
        $this->assertEquals(IndexJobStateMachine::STATUS_REINDEXING, $sm->getCurrentStatus());

        // reindexing -> indexed
        $sm->transitionTo(IndexJobStateMachine::STATUS_INDEXED);
        $this->assertEquals(IndexJobStateMachine::STATUS_INDEXED, $sm->getCurrentStatus());
        $this->assertNotNull($job->completed_at);

        // indexed -> degraded
        $sm->transitionTo(IndexJobStateMachine::STATUS_DEGRADED, 'Degraded notice');
        $this->assertEquals(IndexJobStateMachine::STATUS_DEGRADED, $sm->getCurrentStatus());
        $this->assertEquals('Degraded notice', $job->error_message);

        // degraded -> failed
        $sm->transitionTo(IndexJobStateMachine::STATUS_FAILED, 'Catastrophic error');
        $this->assertEquals(IndexJobStateMachine::STATUS_FAILED, $sm->getCurrentStatus());

        // failed -> archived
        $sm->transitionTo(IndexJobStateMachine::STATUS_ARCHIVED);
        $this->assertEquals(IndexJobStateMachine::STATUS_ARCHIVED, $sm->getCurrentStatus());
    }

    public function test_invalid_transition_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        $job = CoreSearchIndexJob::create([
            'id' => 'job-test-invalid',
            'job_type' => 'full_reindex',
            'status' => IndexJobStateMachine::STATUS_ARCHIVED,
        ]);

        $sm = new IndexJobStateMachine($job);
        // Archived tidak boleh transisi kembali ke processing
        $sm->transitionTo(IndexJobStateMachine::STATUS_PROCESSING);
    }

    public function test_idempotent_transition(): void
    {
        $job = CoreSearchIndexJob::create([
            'id' => 'job-test-idem',
            'job_type' => 'full_reindex',
            'status' => IndexJobStateMachine::STATUS_PROCESSING,
        ]);

        $sm = new IndexJobStateMachine($job);
        $sm->transitionTo(IndexJobStateMachine::STATUS_PROCESSING);
        $this->assertEquals(IndexJobStateMachine::STATUS_PROCESSING, $sm->getCurrentStatus());
    }
}

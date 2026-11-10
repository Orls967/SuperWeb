<?php

declare(strict_types=1);

namespace Tests\Acceptance\SearchNav;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Application\Actions\Search\SearchOrchestratorService;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Domain\Models\CoreSearchIndex;
use Modules\Core\Domain\Models\CoreSearchIndexJob;
use Modules\Core\Domain\Models\CoreTask;
use Modules\Core\Domain\Models\CoreTaskHistory;
use Modules\Core\Domain\StateMachines\IndexJobStateMachine;
use Modules\Shared\Domain\Algorithms\Ui\JaccardCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Ui\TrigramJaccardMatcher;
use Tests\TestCase;

class SearchNavAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 01: Global Search BM25 ranking and Policy-First RBAC filtering zero leak.
     */
    public function test_01_global_search_bm25_ranking_and_rbac_policy_first_zero_leak(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mechanic = User::factory()->create(['role' => 'mechanic']);

        CoreSearchIndex::create([
            'id' => 'idx-test-confidential-01',
            'module' => 'Contract',
            'entity_type' => 'ContractAgreement',
            'entity_id' => 'CA-999',
            'title' => 'Confidential Executive Service Agreement for Fleet Overhaul',
            'content' => 'High level executive confidential compensation terms and conditions for fleet maintenance.',
            'metadata' => ['url' => '/contracts/CA-999', 'confidential' => true],
            'required_permission' => 'contract.view.confidential',
            'document_length' => 12,
            'indexed_at' => now(),
        ]);

        CoreSearchIndex::create([
            'id' => 'idx-test-public-02',
            'module' => 'AutoServe',
            'entity_type' => 'WorkOrder',
            'entity_id' => 'WO-101',
            'title' => 'Standard Fleet Overhaul Inspection Work Order',
            'content' => 'Public workshop mechanical inspection and standard overhaul for vehicle service.',
            'metadata' => ['url' => '/auto-serve/work-orders/WO-101'],
            'required_permission' => null,
            'document_length' => 10,
            'indexed_at' => now(),
        ]);

        $orchestrator = app(SearchOrchestratorService::class);

        // Pencarian oleh mechanic yang tidak memiliki izin confidential
        $mechanicResults = $orchestrator->search('fleet overhaul', $mechanic);
        $this->assertCount(1, $mechanicResults['data']);
        $this->assertEquals('WO-101', $mechanicResults['data'][0]['entity_id']);
        $this->assertStringNotContainsString('Confidential', json_encode($mechanicResults));

        // Pencarian oleh admin
        $adminResults = $orchestrator->search('fleet overhaul', $admin);
        $this->assertCount(2, $adminResults['data']);
        $this->assertGreaterThan(0.0, $adminResults['data'][0]['score']);
    }

    /**
     * Test 02: Fuzzy Trigram Jaccard spelling correction and auto-suggestion.
     */
    public function test_02_fuzzy_trigram_jaccard_spelling_suggestion(): void
    {
        $matcher = app(TrigramJaccardMatcher::class);
        $corpus = ['transmission', 'carburetor', 'alternator', 'suspension', 'differential', 'maintenance'];

        // Pengguna salah ketik "transmisi" atau "transmision"
        $suggestion = $matcher->findBestMatch('transmision', $corpus, 0.45);
        $this->assertNotNull($suggestion);
        $this->assertEquals('transmission', $suggestion['match']);
        $this->assertGreaterThanOrEqual(0.6, $suggestion['similarity']);

        // Verifikasi sertifikat deterministik
        $verifier = app(JaccardCertificateVerifier::class);
        $this->assertTrue($verifier->verify($suggestion['certificate']));
    }

    /**
     * Test 03: Index Job State Machine with 8 valid statuses and idempotency.
     */
    public function test_03_index_job_state_machine_8_statuses_and_idempotency(): void
    {
        $job = CoreSearchIndexJob::create([
            'id' => 'job-test-f194-01',
            'job_type' => 'full_reindex',
            'module' => 'AutoServe',
            'status' => 'pending',
            'records_total' => 100,
            'records_processed' => 0,
        ]);

        $sm = new IndexJobStateMachine($job);
        $this->assertEquals('pending', $sm->getCurrentStatus());

        $sm->transitionTo('queued');
        $this->assertEquals('queued', $sm->getCurrentStatus());

        $sm->transitionTo('processing');
        $this->assertEquals('processing', $sm->getCurrentStatus());

        $sm->transitionTo('reindexing');
        $this->assertEquals('reindexing', $sm->getCurrentStatus());

        $sm->transitionTo('indexed');
        $this->assertEquals('indexed', $sm->getCurrentStatus());

        // Idempotency: transisi ke status yang sama tidak melempar error dan tidak merusak state
        $sm->transitionTo('indexed');
        $this->assertEquals('indexed', $sm->getCurrentStatus());

        $sm->transitionTo('degraded', 'Partial index warning');
        $this->assertEquals('degraded', $sm->getCurrentStatus());

        $sm->transitionTo('failed', 'Critical indexing error');
        $this->assertEquals('failed', $sm->getCurrentStatus());

        $sm->transitionTo('archived');
        $this->assertEquals('archived', $sm->getCurrentStatus());
    }

    /**
     * Test 04: Task Inbox lifecycle, approval action, delegation, and escalation.
     */
    public function test_04_task_inbox_lifecycle_approval_and_escalation(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $manager = User::factory()->create(['role' => 'manager']);

        $service = app(TaskInboxService::class);

        $task = $service->createTask([
            'title' => 'Review Overtime Budget Approval #881',
            'description' => 'Overtime authorization for mechanical overhaul team',
            'module' => 'Operations',
            'source_entity_type' => 'OvertimeRequest',
            'source_entity_id' => 'OT-881',
            'assignee_user_id' => $supervisor->id,
            'assignee_role' => 'supervisor',
            'priority' => 'high',
            'due_at' => now()->addHours(4),
            'action_url' => '/operations/overtime/OT-881',
            'payload' => ['budget_cents' => 4500000],
        ]);

        $this->assertEquals('pending', $task->status);
        $this->assertEquals(0, $task->escalation_level);

        // Delegasikan ke manager
        $service->delegateTask($task, $manager->id, 'Delegated due to executive budget ceiling');
        $task->refresh();
        $this->assertEquals('delegated', $task->status);
        $this->assertEquals($manager->id, $task->assignee_user_id);

        // Manager menyetujui tugas
        $service->approveTask($task, $manager->id, 'Approved for critical workshop project delivery');
        $task->refresh();
        $this->assertEquals('approved', $task->status);

        // Riwayat audit mutasi tercatat lengkap
        $this->assertDatabaseHas('core_task_histories', [
            'task_id' => $task->id,
            'to_status' => 'approved',
            'actor_id' => $manager->id,
        ]);
    }

    /**
     * Test 05: Search & Inbox Two-Source Audit integrity verification (CLEAN vs CORRUPTED).
     */
    public function test_05_search_audit_two_source_integrity_clean_vs_corruption(): void
    {
        // Kondisi Bersih: Jalankan audit awal
        $cleanExit = Artisan::call('search:audit', ['--verify-dual-source' => true]);
        $this->assertEquals(0, $cleanExit);
        $this->assertStringContainsString('INTEGRITY STATUS: CLEAN', Artisan::output());

        // Injeksi korupsi data buatan: task status tidak konsisten dengan riwayat
        $corruptTask = CoreTask::create([
            'id' => 'task-corrupt-001',
            'title' => 'Corrupted Ghost Task',
            'module' => 'Finance',
            'source_entity_type' => 'Invoice',
            'source_entity_id' => 'INV-999',
            'status' => 'approved',
            'priority' => 'normal',
        ]);
        CoreTaskHistory::create([
            'task_id' => $corruptTask->id,
            'actor_id' => null,
            'action' => 'created',
            'from_status' => null,
            'to_status' => 'pending', // Inkonsisten: task berstatus approved tapi history terakhir pending
            'notes' => 'Initial state',
            'created_at' => now(),
        ]);

        // Audit kedua harus mendeteksi korupsi integritas
        $corruptExit = Artisan::call('search:audit', ['--verify-dual-source' => true]);
        $this->assertEquals(1, $corruptExit);
        $this->assertStringContainsString('INTEGRITY STATUS: CORRUPTED', Artisan::output());
    }
}

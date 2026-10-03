<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Domain\Models\ApprovalHistory;
use Modules\Core\Domain\Models\ApprovalRequest;

uses(RefreshDatabase::class);

test('submit creates multi-step approval request with history', function () {
    $creator = User::factory()->create(['role' => 'customer']);

    $engine = app(ApprovalEngineInterface::class);

    $steps = [
        ['role' => 'supervisor'],
        ['role' => 'finance_manager'],
    ];

    $approval = $engine->submit(
        approvalType: 'CONTRACT',
        title: 'Pengadaan Kontrak Armada 2026',
        creator: $creator,
        amount: 250_000_000,
        steps: $steps,
        slaHours: 24,
        metadata: ['vendor' => 'PT Sumber Rezeki']
    );

    expect($approval)->toBeInstanceOf(ApprovalRequest::class)
        ->and($approval->status)->toBe('pending')
        ->and($approval->current_step)->toBe(1)
        ->and($approval->total_steps)->toBe(2)
        ->and($approval->steps)->toHaveCount(2)
        ->and($approval->sla_due_at)->not->toBeNull();

    $history = ApprovalHistory::where('approval_id', $approval->id)->first();
    expect($history->action)->toBe('SUBMITTED')
        ->and($history->user_id)->toBe($creator->id);
});

test('four-eyes principle strictly blocks creator from approving own request', function () {
    $creator = User::factory()->create(['role' => 'admin']);

    $engine = app(ApprovalEngineInterface::class);

    $approval = $engine->submit(
        approvalType: 'PO',
        title: 'PO Sparepart Urgent',
        creator: $creator,
        amount: 15_000_000,
        steps: [['role' => 'admin']]
    );

    // Creator attempts to approve -> Exception thrown
    expect(fn () => $engine->approve($approval, $creator, 'Setuju oleh pembuat'))
        ->toThrow(RuntimeException::class, 'Prinsip Four-Eyes: Penyetuju tidak boleh orang yang sama dengan pembuat permohonan.');
});

test('multi-level approval progresses step by step until final approval', function () {
    $creator = User::factory()->create(['role' => 'staff']);
    $approver1 = User::factory()->create(['role' => 'supervisor']);
    $approver2 = User::factory()->create(['role' => 'admin']);

    $engine = app(ApprovalEngineInterface::class);

    $approval = $engine->submit(
        approvalType: 'CLAIM',
        title: 'Klaim Kerusakan Kontainer',
        creator: $creator,
        amount: 50_000_000,
        steps: [
            ['role' => 'supervisor'],
            ['role' => 'admin'],
        ]
    );

    // Level 1 Approval by supervisor
    $afterStep1 = $engine->approve($approval, $approver1, 'Dokumen lengkap, disetujui supervisor');
    expect($afterStep1->status)->toBe('pending')
        ->and($afterStep1->current_step)->toBe(2);

    // Level 2 Final Approval by admin
    $finalApproval = $engine->approve($afterStep1, $approver2, 'Persetujuan akhir disetujui');
    expect($finalApproval->status)->toBe('approved')
        ->and($finalApproval->decided_at)->not->toBeNull()
        ->and($finalApproval->histories)->toHaveCount(3); // SUBMITTED, APPROVED 1, APPROVED 2
});

test('rejection terminates the approval request immediately', function () {
    $creator = User::factory()->create(['role' => 'staff']);
    $approver = User::factory()->create(['role' => 'admin']);

    $engine = app(ApprovalEngineInterface::class);

    $approval = $engine->submit(
        approvalType: 'ASSET_WRITE_OFF',
        title: 'Penghapusan Aset Truk Rusak',
        creator: $creator,
        amount: 80_000_000
    );

    $rejected = $engine->reject($approval, $approver, 'Bukti inspeksi teknis belum memadai');

    expect($rejected->status)->toBe('rejected')
        ->and($rejected->isRejected())->toBeTrue()
        ->and($rejected->decided_at)->not->toBeNull();
});

test('delegation and escalation function properly', function () {
    $creator = User::factory()->create(['role' => 'staff']);
    $manager = User::factory()->create(['role' => 'manager']);
    $delegatee = User::factory()->create(['role' => 'deputy_manager']);

    $engine = app(ApprovalEngineInterface::class);

    $approval = $engine->submit(
        approvalType: 'CONTRACT',
        title: 'Perpanjangan Sewa Gedung',
        creator: $creator,
        steps: [['role' => 'manager', 'user_id' => $manager->id]]
    );

    // Delegation to deputy
    $delegated = $engine->delegate($approval, $manager, $delegatee, 'Sedang dinas luar kota');
    $step = $delegated->currentStepModel();

    expect($step->assigned_user_id)->toBe($delegatee->id)
        ->and($step->delegated_to)->toBe($delegatee->id);

    // Escalation test
    $escalated = $engine->escalate($delegated, 'SLA telah terlampaui 48 jam');
    expect($escalated->status)->toBe('escalated');
});

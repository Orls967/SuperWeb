<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\AuditTrailInterface;
use Modules\Core\Domain\Models\AuditLog;

uses(RefreshDatabase::class);

test('audit log cannot be modified due to append-only constraint', function () {
    $log = AuditLog::record(
        action: 'test.action',
        context: ['foo' => 'bar'],
        correlationId: 'corr_123',
        impactType: 'financial'
    );

    expect($log->id)->not->toBeNull()
        ->and($log->action)->toBe('test.action')
        ->and($log->correlation_id)->toBe('corr_123')
        ->and($log->impact_type)->toBe('financial');

    expect(fn () => $log->update(['action' => 'tampered']))
        ->toThrow(RuntimeException::class, 'AuditLog is append-only and cannot be modified.');
});

test('audit log cannot be deleted due to append-only constraint', function () {
    $log = AuditLog::record(
        action: 'test.action.delete',
        context: ['status' => 'pending']
    );

    expect(fn () => $log->delete())
        ->toThrow(RuntimeException::class, 'AuditLog is append-only and cannot be deleted.');
});

test('audit trail interface is bound in container and records logs', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $service = app(AuditTrailInterface::class);

    $log = $service->record(
        action: 'financial.payout.approved',
        auditable: null,
        oldValues: ['status' => 'draft'],
        newValues: ['status' => 'approved'],
        context: ['amount' => 5000000],
        correlationId: 'payout_tx_999',
        impactType: 'financial',
        user: $user
    );

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->action)->toBe('financial.payout.approved')
        ->and($log->old_values)->toBe(['status' => 'draft'])
        ->and($log->new_values)->toBe(['status' => 'approved'])
        ->and($log->correlation_id)->toBe('payout_tx_999')
        ->and($log->impact_type)->toBe('financial');
});

test('admin can view and filter audit logs list', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'customer']);

    AuditLog::record(
        action: 'banking.wallet.transfer',
        context: ['amount' => 100000],
        correlationId: 'corr_bank_01',
        impactType: 'financial',
        user: $user
    );

    AuditLog::record(
        action: 'core.rbac.role_assigned',
        context: ['role' => 'mekanik'],
        correlationId: 'corr_rbac_01',
        impactType: 'security',
        user: $admin
    );

    $res1 = $this->actingAs($admin)
        ->get(route('admin.audit-logs.index'));
    $res1->assertOk()
        ->assertSee('Audit Trail Generik')
        ->assertSee('banking.wallet.transfer')
        ->assertSee('core.rbac.role_assigned');

    // Filter by action
    $resAction = $this->actingAs($admin)
        ->get(route('admin.audit-logs.index', ['action' => 'banking.wallet.transfer']));
    $resAction->assertOk();
    $logs = $resAction->viewData('logs');
    expect($logs->pluck('action')->all())->toContain('banking.wallet.transfer')
        ->and($logs->pluck('action')->all())->not->toContain('core.rbac.role_assigned');

    // Filter by impact_type
    $resImpact = $this->actingAs($admin)
        ->get(route('admin.audit-logs.index', ['impact_type' => 'security']));
    $resImpact->assertOk();
    $impactLogs = $resImpact->viewData('logs');
    expect($impactLogs->pluck('action')->all())->toContain('core.rbac.role_assigned')
        ->and($impactLogs->pluck('action')->all())->not->toContain('banking.wallet.transfer');
});

test('admin can view audit log detail', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $log = AuditLog::record(
        action: 'store.order.shipped',
        oldValues: ['status' => 'processing'],
        newValues: ['status' => 'shipped'],
        context: ['tracking_number' => 'SRX123456'],
        correlationId: 'ship_corr_777',
        impactType: 'state',
        user: $admin
    );

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.show', $log->id))
        ->assertOk()
        ->assertSee('Log Audit #'.$log->id)
        ->assertSee('store.order.shipped')
        ->assertSee('ship_corr_777')
        ->assertSee('SRX123456')
        ->assertSee('processing')
        ->assertSee('shipped');
});

test('non-admin cannot access audit logs', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();
});

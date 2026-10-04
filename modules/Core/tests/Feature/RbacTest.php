<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Application\Services\RbacService;
use Modules\Core\database\seeders\RbacSeeder;
use Modules\Core\Domain\Models\Permission;
use Modules\Core\Domain\Models\Role;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// (a) Happy path — basic RBAC operations
// ──────────────────────────────────────────────

test('can create roles and permissions', function () {
    $svc = app(RbacService::class);

    $role = $svc->ensureRole('tester', 'Tester', 'QA role');
    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->name)->toBe('tester')
        ->and($role->label)->toBe('Tester');

    $perm = $svc->ensurePermission('test.run', 'Jalankan Test', 'testing');
    expect($perm)->toBeInstanceOf(Permission::class)
        ->and($perm->name)->toBe('test.run')
        ->and($perm->module)->toBe('testing');
});

test('can assign and check RBAC role on user', function () {
    $svc = app(RbacService::class);

    $role = $svc->ensureRole('editor', 'Editor');
    $user = User::factory()->create(['role' => 'customer']);

    expect($user->hasRbacRole('editor'))->toBeFalse();

    $user->assignRbacRole('editor');
    expect($user->hasRbacRole('editor'))->toBeTrue();
    expect($user->rbacRoleNames())->toContain('editor');
});

test('can assign scoped RBAC role', function () {
    $svc = app(RbacService::class);
    $svc->ensureRole('cashier', 'Kasir');

    $user = User::factory()->create(['role' => 'customer']);
    $user->assignRbacRole('cashier', 'outlet', 5);

    expect($user->hasRbacRole('cashier'))->toBeTrue();
    expect($user->hasRbacRole('cashier', 'outlet', 5))->toBeTrue();
    expect($user->hasRbacRole('cashier', 'outlet', 99))->toBeFalse();
});

test('user has permission through role', function () {
    $svc = app(RbacService::class);

    $role = $svc->ensureRole('manager', 'Manager');
    $svc->ensurePermission('report.view', 'Lihat Laporan', 'reports');
    $svc->ensurePermission('report.export', 'Ekspor Laporan', 'reports');
    $svc->grantPermissionsToRole($role, ['report.view', 'report.export']);

    $user = User::factory()->create(['role' => 'customer']);
    $user->assignRbacRole('manager');

    expect($user->hasRbacPermission('report.view'))->toBeTrue();
    expect($user->hasRbacPermission('report.export'))->toBeTrue();
    expect($user->hasRbacPermission('report.delete'))->toBeFalse();
    expect($user->allRbacPermissions())->toContain('report.view', 'report.export');
});

test('hasAnyRbacRole checks multiple roles', function () {
    $svc = app(RbacService::class);
    $svc->ensureRole('admin', 'Admin');
    $svc->ensureRole('editor', 'Editor');

    $user = User::factory()->create(['role' => 'customer']);
    $user->assignRbacRole('editor');

    expect($user->hasAnyRbacRole(['admin', 'editor']))->toBeTrue();
    expect($user->hasAnyRbacRole(['admin', 'superuser']))->toBeFalse();
});

test('legacy users.role still works as fallback', function () {
    $svc = app(RbacService::class);
    $svc->ensureRole('admin', 'Admin');

    // User has legacy role 'admin' but no RBAC assignment
    $user = User::factory()->create(['role' => 'admin']);

    expect($user->hasRbacRole('admin'))->toBeTrue(); // falls back to users.role
    expect($user->hasAnyRbacRole(['admin']))->toBeTrue();
});

// ──────────────────────────────────────────────
// (b) Validation & authorization
// ──────────────────────────────────────────────

test('RBAC admin pages are admin-only', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($customer)->get(route('admin.rbac.index'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.rbac.index'))->assertOk();
});

test('non-admin cannot toggle permissions', function () {
    $svc = app(RbacService::class);
    $role = $svc->ensureRole('tester', 'Tester');
    $perm = $svc->ensurePermission('test.run', 'Run Test');

    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->post(route('admin.rbac.toggle-permission', $role), ['permission_id' => $perm->id])
        ->assertStatus(403);
});

test('non-admin cannot assign roles', function () {
    $svc = app(RbacService::class);
    $role = $svc->ensureRole('tester', 'Tester');
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->post(route('admin.rbac.assign'), [
            'user_id' => $user->id,
            'role_id' => $role->id,
        ])
        ->assertStatus(403);
});

// ──────────────────────────────────────────────
// (c) Idempotency — safe to run multiple times
// ──────────────────────────────────────────────

test('assigning same role twice is idempotent', function () {
    $svc = app(RbacService::class);
    $svc->ensureRole('editor', 'Editor');

    $user = User::factory()->create(['role' => 'customer']);
    $user->assignRbacRole('editor');
    $user->assignRbacRole('editor'); // duplicate — should not throw

    expect($user->rbacRoles()->where('roles.name', 'editor')->count())->toBe(1);
});

test('revoking non-existent role is no-op', function () {
    $svc = app(RbacService::class);
    $svc->ensureRole('editor', 'Editor');

    $user = User::factory()->create(['role' => 'customer']);
    $user->revokeRbacRole('editor'); // not assigned — should not throw

    expect($user->rbacRoles()->count())->toBe(0);
});

test('ensureRole is idempotent', function () {
    $svc = app(RbacService::class);

    $r1 = $svc->ensureRole('admin', 'Admin');
    $r2 = $svc->ensureRole('admin', 'Admin 2');

    expect($r1->id)->toBe($r2->id);
    expect(Role::where('name', 'admin')->count())->toBe(1);
});

test('ensurePermission is idempotent', function () {
    $svc = app(RbacService::class);

    $p1 = $svc->ensurePermission('test.run', 'Run', 'test');
    $p2 = $svc->ensurePermission('test.run', 'Run 2', 'test');

    expect($p1->id)->toBe($p2->id);
    expect(Permission::where('name', 'test.run')->count())->toBe(1);
});

test('grantPermissionsToRole is idempotent', function () {
    $svc = app(RbacService::class);

    $role = $svc->ensureRole('editor', 'Editor');
    $svc->ensurePermission('a.b', 'AB');
    $svc->grantPermissionsToRole($role, ['a.b']);
    $svc->grantPermissionsToRole($role, ['a.b']); // duplicate

    expect($role->permissions()->where('permissions.name', 'a.b')->count())->toBe(1);
});

// ──────────────────────────────────────────────
// (d) Backfill from legacy
// ──────────────────────────────────────────────

test('backfillAllFromLegacy syncs users to RBAC', function () {
    $svc = app(RbacService::class);
    $svc->ensureRole('admin', 'Admin');
    $svc->ensureRole('customer', 'Customer');

    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'customer']);
    User::factory()->create(['role' => 'customer']);

    $count = $svc->backfillAllFromLegacy();

    expect($count)->toBe(3);
    expect(Role::where('name', 'admin')->first()->users()->count())->toBe(1);
    expect(Role::where('name', 'customer')->first()->users()->count())->toBe(2);
});

test('backfill is idempotent', function () {
    $svc = app(RbacService::class);
    $svc->ensureRole('admin', 'Admin');

    User::factory()->create(['role' => 'admin']);

    $svc->backfillAllFromLegacy();
    $svc->backfillAllFromLegacy(); // second run — should not duplicate

    expect(Role::where('name', 'admin')->first()->users()->count())->toBe(1);
});

// ──────────────────────────────────────────────
// (e) Edge cases & CheckRole middleware
// ──────────────────────────────────────────────

test('user with only RBAC role (not legacy) passes middleware', function () {
    $svc = app(RbacService::class);
    $svc->ensureRole('admin', 'Admin');

    // User has legacy role 'customer' but RBAC role 'admin'
    $user = User::factory()->create(['role' => 'customer']);
    $user->assignRbacRole('admin');

    // The admin/rbac page requires role:admin — should pass
    $this->actingAs($user)->get(route('admin.rbac.index'))->assertOk();
});

test('revoke RBAC role removes access', function () {
    $svc = app(RbacService::class);
    $svc->ensureRole('admin', 'Admin');

    // User has ONLY RBAC admin, not legacy
    $user = User::factory()->create(['role' => 'customer']);
    $user->assignRbacRole('admin');

    $this->actingAs($user)->get(route('admin.rbac.index'))->assertOk();

    $user->revokeRbacRole('admin');
    $this->actingAs($user)->get(route('admin.rbac.index'))->assertStatus(403);
});

test('permission matrix returns correct structure', function () {
    $svc = app(RbacService::class);

    $role = $svc->ensureRole('editor', 'Editor');
    $svc->ensurePermission('doc.read', 'Read Docs');
    $svc->ensurePermission('doc.write', 'Write Docs');
    $svc->grantPermissionsToRole($role, ['doc.read']);

    $matrix = $svc->getPermissionMatrix();

    expect($matrix)->toHaveKey('editor');
    expect($matrix['editor'])->toContain('doc.read');
    expect($matrix['editor'])->not->toContain('doc.write');
});

test('getUserAuthorization returns complete profile', function () {
    $svc = app(RbacService::class);

    $role = $svc->ensureRole('editor', 'Editor');
    $svc->ensurePermission('doc.read', 'Read Docs');
    $svc->grantPermissionsToRole($role, ['doc.read']);

    $user = User::factory()->create(['role' => 'customer']);
    $user->assignRbacRole('editor');

    $auth = $svc->getUserAuthorization($user);

    expect($auth['legacy_role'])->toBe('customer');
    expect($auth['roles'])->toContain('editor');
    expect($auth['permissions'])->toContain('doc.read');
});

test('Gate::before allows admin to bypass all gates', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    // Admin should pass any arbitrary gate check
    expect(Gate::allows('some.random.ability'))->toBeTrue();
});

test('Gate::before checks RBAC permissions', function () {
    $svc = app(RbacService::class);
    $role = $svc->ensureRole('editor', 'Editor');
    $svc->ensurePermission('doc.read', 'Read Docs');
    $svc->grantPermissionsToRole($role, ['doc.read']);

    $user = User::factory()->create(['role' => 'customer']);
    $user->assignRbacRole('editor');

    $this->actingAs($user);

    // Should pass because the user has the RBAC permission
    expect(Gate::allows('doc.read'))->toBeTrue();
    // Should not pass for a permission they don't have
    expect(Gate::allows('doc.write'))->toBeFalse();
});

test('RbacSeeder produces complete role and permission data', function () {
    $svc = app(RbacService::class);

    // Run the seeder
    $this->seed(RbacSeeder::class);

    // 17 roles: 12 lama + party_manager, contract_manager, legal, asset_manager, auditor
    expect(Role::count())->toBe(17);

    // Permissions should be > 0
    expect(Permission::count())->toBeGreaterThan(30);

    // Admin role should have ALL permissions
    $adminRole = Role::where('name', 'admin')->first();
    expect($adminRole->permissions()->count())->toBe(Permission::count());

    // Customer should have limited permissions
    $customerRole = Role::where('name', 'customer')->first();
    expect($customerRole->permissions()->count())->toBeGreaterThan(5);
    expect($customerRole->permissions()->count())->toBeLessThan(Permission::count());
});

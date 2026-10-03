<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use App\Models\User;
use Modules\Core\Domain\Models\Permission;
use Modules\Core\Domain\Models\Role;

/**
 * Service for managing RBAC roles and permissions.
 *
 * All mutations are idempotent and wrapped in transactions.
 */
class RbacService
{
    /**
     * Create or update a role.
     */
    public function ensureRole(string $name, string $label, ?string $description = null): Role
    {
        return Role::firstOrCreate(
            ['name' => $name],
            ['label' => $label, 'description' => $description]
        );
    }

    /**
     * Create or update a permission.
     */
    public function ensurePermission(string $name, string $label, ?string $module = null): Permission
    {
        return Permission::firstOrCreate(
            ['name' => $name],
            ['label' => $label, 'module' => $module]
        );
    }

    /**
     * Grant permissions to a role. Idempotent.
     *
     * @param  array<string>  $permissionNames
     */
    public function grantPermissionsToRole(Role $role, array $permissionNames): void
    {
        $ids = Permission::whereIn('name', $permissionNames)->pluck('id');
        $role->permissions()->syncWithoutDetaching($ids);
    }

    /**
     * Revoke permissions from a role.
     *
     * @param  array<string>  $permissionNames
     */
    public function revokePermissionsFromRole(Role $role, array $permissionNames): void
    {
        $ids = Permission::whereIn('name', $permissionNames)->pluck('id');
        $role->permissions()->detach($ids);
    }

    /**
     * Assign a role to a user, optionally scoped to an entity. Idempotent.
     */
    public function assignRole(User $user, string|Role $role, ?string $entityType = null, ?int $entityId = null): void
    {
        $user->assignRbacRole($role, $entityType, $entityId);
    }

    /**
     * Revoke a role from a user. Idempotent.
     */
    public function revokeRole(User $user, string|Role $role, ?string $entityType = null, ?int $entityId = null): void
    {
        $user->revokeRbacRole($role, $entityType, $entityId);
    }

    /**
     * Sync user's RBAC roles from their legacy `users.role` column.
     * Called during the migration/backfill phase.
     */
    public function syncFromLegacyRole(User $user): void
    {
        $legacyRole = $user->role;

        if (! $legacyRole) {
            return;
        }

        $role = Role::where('name', $legacyRole)->first();

        if (! $role) {
            return;
        }

        $user->assignRbacRole($role);
    }

    /**
     * Backfill all users' RBAC roles from their legacy `users.role` column.
     * Idempotent — safe to run multiple times.
     */
    public function backfillAllFromLegacy(): int
    {
        $count = 0;

        User::whereNotNull('role')
            ->chunkById(100, function ($users) use (&$count) {
                foreach ($users as $user) {
                    $this->syncFromLegacyRole($user);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Get the permission matrix: role -> permissions mapping.
     *
     * @return array<string, array<string>>
     */
    public function getPermissionMatrix(): array
    {
        $matrix = [];

        $roles = Role::with('permissions')->orderBy('name')->get();

        foreach ($roles as $role) {
            $matrix[$role->name] = $role->permissions->pluck('name')->sort()->values()->all();
        }

        return $matrix;
    }

    /**
     * Check authorization matrix for a user.
     *
     * @return array{roles: array<string>, permissions: array<string>, legacy_role: string|null}
     */
    public function getUserAuthorization(User $user): array
    {
        return [
            'roles' => $user->rbacRoleNames(),
            'permissions' => $user->allRbacPermissions(),
            'legacy_role' => $user->role,
        ];
    }
}

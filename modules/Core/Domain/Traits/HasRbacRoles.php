<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Domain\Models\Permission;
use Modules\Core\Domain\Models\Role;

/**
 * Granular RBAC trait for User model.
 *
 * Supports multi-role per user with optional entity scoping (e.g. a cashier scoped to outlet #3).
 * Legacy `users.role` column is kept and mirrored for backward compatibility.
 */
trait HasRbacRoles
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function rbacRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')
            ->withPivot(['entity_type', 'entity_id'])
            ->withTimestamps();
    }

    /**
     * Assign an RBAC role, optionally scoped to an entity.
     * Idempotent — re-assigning the same role+scope is a no-op.
     */
    public function assignRbacRole(string|Role $role, ?string $entityType = null, ?int $entityId = null): void
    {
        $roleModel = $role instanceof Role
            ? $role
            : Role::where('name', $role)->firstOrFail();

        $exists = $this->rbacRoles()
            ->where('roles.id', $roleModel->id)
            ->wherePivot('entity_type', $entityType)
            ->wherePivot('entity_id', $entityId)
            ->exists();

        if (! $exists) {
            $this->rbacRoles()->attach($roleModel->id, [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }
    }

    /**
     * Revoke an RBAC role, optionally scoped to an entity.
     * Idempotent — revoking a non-existent assignment is a no-op.
     */
    public function revokeRbacRole(string|Role $role, ?string $entityType = null, ?int $entityId = null): void
    {
        $roleModel = $role instanceof Role
            ? $role
            : Role::where('name', $role)->first();

        if (! $roleModel) {
            return;
        }

        $this->rbacRoles()
            ->wherePivot('entity_type', $entityType)
            ->wherePivot('entity_id', $entityId)
            ->detach($roleModel->id);
    }

    /**
     * Check if user has a specific RBAC role (optionally scoped).
     * Falls back to legacy `users.role` column for compatibility.
     */
    public function hasRbacRole(string $roleName, ?string $entityType = null, ?int $entityId = null): bool
    {
        // Check the RBAC table first
        $query = $this->rbacRoles()->where('roles.name', $roleName);

        if ($entityType !== null) {
            $query->wherePivot('entity_type', $entityType);
            if ($entityId !== null) {
                $query->wherePivot('entity_id', $entityId);
            }
        }

        if ($query->exists()) {
            return true;
        }

        // Fallback: legacy users.role column
        return $this->role === $roleName;
    }

    /**
     * Check if user has ANY of the given RBAC roles.
     */
    public function hasAnyRbacRole(array $roleNames): bool
    {
        // Check RBAC table
        if ($this->rbacRoles()->whereIn('roles.name', $roleNames)->exists()) {
            return true;
        }

        // Fallback: legacy column
        return in_array($this->role, $roleNames);
    }

    /**
     * Check if the user has a specific permission (via any of their roles).
     * Optionally scoped to an entity.
     */
    public function hasRbacPermission(string $permissionName, ?string $entityType = null, ?int $entityId = null): bool
    {
        $query = $this->rbacRoles();

        if ($entityType !== null) {
            $query->wherePivot('entity_type', $entityType);
            if ($entityId !== null) {
                $query->wherePivot('entity_id', $entityId);
            }
        }

        return $query->whereHas('permissions', function ($q) use ($permissionName) {
            $q->where('permissions.name', $permissionName);
        })->exists();
    }

    /**
     * Get all permission names this user has (from all RBAC roles).
     *
     * @return array<string>
     */
    public function allRbacPermissions(): array
    {
        return Permission::whereHas('roles', function ($q) {
            $q->whereIn('roles.id', $this->rbacRoles()->pluck('roles.id'));
        })->pluck('name')->unique()->values()->all();
    }

    /**
     * Get all RBAC role names (without scope filtering).
     *
     * @return array<string>
     */
    public function rbacRoleNames(): array
    {
        return $this->rbacRoles()->pluck('roles.name')->unique()->values()->all();
    }
}

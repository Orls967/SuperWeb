<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Application\Services\RbacService;
use Modules\Core\Domain\Models\Permission;
use Modules\Core\Domain\Models\Role;

class RbacController extends Controller
{
    public function __construct(private readonly RbacService $rbac) {}

    /**
     * Dashboard: roles, permissions, matriks otorisasi.
     */
    public function index()
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('name')->get();
        $permissions = Permission::orderBy('module')->orderBy('name')->get();
        $matrix = $this->rbac->getPermissionMatrix();

        $permissionsByModule = $permissions->groupBy(fn ($p) => $p->module ?? 'other');

        return view('core::rbac.index', compact('roles', 'permissions', 'matrix', 'permissionsByModule'));
    }

    /**
     * Detail role: permissions, users, toggle.
     */
    public function showRole(Role $role)
    {
        $role->load(['permissions', 'users']);
        $allPermissions = Permission::orderBy('module')->orderBy('name')->get()
            ->groupBy(fn ($p) => $p->module ?? 'other');

        return view('core::rbac.role', compact('role', 'allPermissions'));
    }

    /**
     * Toggle permission for a role.
     */
    public function togglePermission(Request $request, Role $role)
    {
        $request->validate(['permission_id' => 'required|exists:permissions,id']);

        $permId = (int) $request->input('permission_id');

        if ($role->permissions()->where('permissions.id', $permId)->exists()) {
            $role->permissions()->detach($permId);
            $action = 'revoked';
        } else {
            $role->permissions()->attach($permId);
            $action = 'granted';
        }

        return redirect()->back()->with('success', "Permission {$action}.");
    }

    /**
     * Assign RBAC role to a user.
     */
    public function assignRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'entity_type' => 'nullable|string|max:100',
            'entity_id' => 'nullable|integer',
        ]);

        $user = User::findOrFail($request->input('user_id'));
        $role = Role::findOrFail($request->input('role_id'));

        $this->rbac->assignRole(
            $user,
            $role,
            $request->input('entity_type'),
            $request->filled('entity_id') ? (int) $request->input('entity_id') : null,
        );

        return redirect()->back()->with('success', "Role '{$role->label}' berhasil diberikan ke {$user->name}.");
    }

    /**
     * Revoke RBAC role from a user.
     */
    public function revokeRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'entity_type' => 'nullable|string|max:100',
            'entity_id' => 'nullable|integer',
        ]);

        $user = User::findOrFail($request->input('user_id'));
        $role = Role::findOrFail($request->input('role_id'));

        $this->rbac->revokeRole(
            $user,
            $role,
            $request->input('entity_type'),
            $request->filled('entity_id') ? (int) $request->input('entity_id') : null,
        );

        return redirect()->back()->with('success', "Role '{$role->label}' berhasil dicabut dari {$user->name}.");
    }

    /**
     * User detail: show their RBAC roles and permissions.
     */
    public function showUser(User $user)
    {
        $auth = $this->rbac->getUserAuthorization($user);
        $allRoles = Role::orderBy('name')->get();

        return view('core::rbac.user', compact('user', 'auth', 'allRoles'));
    }
}

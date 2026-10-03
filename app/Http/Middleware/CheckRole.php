<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Middleware untuk cek role user.
     * Penggunaan: middleware('role:admin,mekanik')
     *
     * Mendukung dua sumber:
     * 1. RBAC tabel (user_role + roles) — multi-role, scoped
     * 2. Legacy kolom users.role — single role, fallback
     *
     * User lolos jika salah satu role cocok dari salah satu sumber.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        $user = auth()->user();

        // Check RBAC roles first (multi-role)
        if (method_exists($user, 'hasAnyRbacRole') && $user->hasAnyRbacRole($roles)) {
            return $next($request);
        }

        // Fallback: legacy single-column role
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
}

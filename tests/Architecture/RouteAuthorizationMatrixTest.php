<?php

declare(strict_types=1);

use App\Models\User;
use App\Quality\Routing\RouteAuthorizationScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/*
| PROGRESS R0.9: RouteAuthorizationMatrixTest (KONSEP.md §A14.3).
| Ensures every route is mapped, protection never weakens, dynamic pending routes
| only shrink, and role restrictions are enforced statically and via HTTP.
*/

uses(TestCase::class, RefreshDatabase::class);

it('maps every registered route in route-roles.php', function (): void {
    $routes = Route::getRoutes()->getRoutes();
    $map = require __DIR__.'/route-roles.php';

    $unmapped = RouteAuthorizationScanner::unmappedRoutes($routes, $map);

    expect($unmapped)->toBe([], 'Rute baru tanpa pemetaan di tests/Architecture/route-roles.php.');
});

it('confirms route middleware matches the declared mapping', function (): void {
    $routes = Route::getRoutes()->getRoutes();
    $map = require __DIR__.'/route-roles.php';

    $mismatches = RouteAuthorizationScanner::staticMismatches($routes, $map);

    expect($mismatches)->toBe([], 'Ketidaksesuaian middleware rute dengan deklarasi di tests/Architecture/route-roles.php.');
});

it('enforces that route authorization never weakens compared to baseline', function (): void {
    $routes = Route::getRoutes()->getRoutes();
    $root = dirname(__DIR__, 2);
    $baselinePath = $root.'/tests/Architecture/baselines/route-authorization.json';

    $baselineData = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);
    $baselineRoutes = $baselineData['routes'] ?? [];

    $routesByKey = [];
    foreach ($routes as $route) {
        $routesByKey[RouteAuthorizationScanner::routeKey($route)] = $route;
    }

    $weakened = [];

    foreach ($baselineRoutes as $key => $baseProt) {
        if (! isset($routesByKey[$key])) {
            continue; // Stale route (removed), tracked in stale check if needed
        }

        $baseNormalized = is_array($baseProt) ? ['roles' => $baseProt] : $baseProt;
        $curProtection = RouteAuthorizationScanner::routeProtection($routesByKey[$key]);

        if (RouteAuthorizationScanner::isWeakened($baseNormalized, $curProtection)) {
            $weakened[] = [
                'route' => $key,
                'baseline' => $baseNormalized,
                'current' => $curProtection,
            ];
        }
    }

    expect($weakened)->toBe([], 'Proteksi rute melemah dari baseline (role bertambah atau auth hilang).');
});

it('keeps pending dynamic routes within the ratchet baseline', function (): void {
    $routes = Route::getRoutes()->getRoutes();
    $map = require __DIR__.'/route-roles.php';

    $pending = RouteAuthorizationScanner::pendingDynamicRoutes($routes, $map);

    assertSetBaseline(
        'tests/Architecture/baselines/route-dynamic-pending.json',
        $pending,
        'Rute berparameter tanpa closure params (PROGRESS R0.9, KONSEP §A14.3). Hanya boleh menyusut saat closure params dilengkapi (ditutup di R3.1).'
    );
});

it('enforces that unauthorized roles receive 403 on role-protected routes', function (): void {
    $routes = Route::getRoutes()->getRoutes();
    $map = require __DIR__.'/route-roles.php';
    $executable = RouteAuthorizationScanner::executableRoleRoutes($routes, $map);

    $unauthorizedUser = User::factory()->create(['role' => 'unauthorized_tester']);

    foreach ($executable as $route) {
        $response = $this->actingAs($unauthorizedUser)->call($route['method'], $route['uri']);
        expect($response->status())->toBe(403, "Rute {$route['name']} ({$route['method']} {$route['uri']}) harus mengembalikan 403 untuk role tidak berhak.");
    }
});

it('confirms authorized roles are not forbidden (not 403) on role-protected routes', function (): void {
    $routes = Route::getRoutes()->getRoutes();
    $map = require __DIR__.'/route-roles.php';
    $executable = RouteAuthorizationScanner::executableRoleRoutes($routes, $map);

    $usersByRole = [];

    foreach ($executable as $route) {
        $allowedRole = $route['roles'][0];
        $user = $usersByRole[$allowedRole] ??= User::factory()->create(['role' => $allowedRole]);

        if (isset($route['setup']) && is_callable($route['setup'])) {
            ($route['setup'])($user);
        }

        $response = $this->actingAs($user)->call($route['method'], $route['uri']);
        expect($response->status())->not->toBe(403, "Rute {$route['name']} ({$route['method']} {$route['uri']}) tidak boleh 403 untuk role berhak '{$allowedRole}'.");
    }
});

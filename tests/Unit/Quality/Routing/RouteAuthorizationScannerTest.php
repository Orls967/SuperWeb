<?php

declare(strict_types=1);

use App\Quality\Routing\RouteAuthorizationScanner;
use Illuminate\Routing\Route;

it('determines when route protection is weakened or strengthened', function (): void {
    // Role -> weakened
    expect(RouteAuthorizationScanner::isWeakened(['roles' => ['admin']], 'auth'))->toBeTrue()
        ->and(RouteAuthorizationScanner::isWeakened(['roles' => ['admin']], 'public'))->toBeTrue()
        ->and(RouteAuthorizationScanner::isWeakened(['roles' => ['admin']], ['roles' => ['admin', 'customer']]))->toBeTrue();

    // Role -> strengthened or unchanged
    expect(RouteAuthorizationScanner::isWeakened(['roles' => ['admin', 'manager']], ['roles' => ['admin']]))->toBeFalse()
        ->and(RouteAuthorizationScanner::isWeakened(['roles' => ['admin']], ['roles' => ['admin']]))->toBeFalse();

    // Auth -> weakened or strengthened
    expect(RouteAuthorizationScanner::isWeakened('auth', 'public'))->toBeTrue()
        ->and(RouteAuthorizationScanner::isWeakened('auth', 'auth'))->toBeFalse()
        ->and(RouteAuthorizationScanner::isWeakened('auth', ['roles' => ['admin']]))->toBeFalse();

    // Public -> never weakened
    expect(RouteAuthorizationScanner::isWeakened('public', 'public'))->toBeFalse()
        ->and(RouteAuthorizationScanner::isWeakened('public', 'auth'))->toBeFalse();
});

it('finds unmapped routes', function (): void {
    $r1 = new Route(['GET'], 'alpha', fn () => null);
    $r1->name('alpha.index');

    $r2 = new Route(['GET'], 'beta', fn () => null);
    $r2->name('beta.index');

    $map = ['alpha.index' => 'public'];

    $unmapped = RouteAuthorizationScanner::unmappedRoutes([$r1, $r2], $map);

    expect($unmapped)->toBe(['beta.index']);
});

it('identifies pending dynamic routes lacking a params closure', function (): void {
    $staticRoute = new Route(['GET'], 'dashboard', fn () => null);
    $staticRoute->name('dashboard');

    $paramWithoutClosure = new Route(['GET'], 'users/{id}', fn () => null);
    $paramWithoutClosure->name('users.show');

    $paramWithClosure = new Route(['GET'], 'posts/{slug}', fn () => null);
    $paramWithClosure->name('posts.show');

    $map = [
        'dashboard' => 'auth',
        'users.show' => ['roles' => ['admin']],
        'posts.show' => ['roles' => ['author'], 'params' => fn () => ['slug' => 'test']],
    ];

    $pending = RouteAuthorizationScanner::pendingDynamicRoutes([$staticRoute, $paramWithoutClosure, $paramWithClosure], $map);

    expect($pending)->toBe(['route:users.show']);
});

it('tests staticMismatches detecting string and role discrepancies', function (): void {
    $r1 = new Route(['GET'], 'pub', fn () => null);
    $r1->name('r1.pub');
    // Actual is public, but map expects auth
    $map1 = ['r1.pub' => 'auth'];
    $mismatches1 = RouteAuthorizationScanner::staticMismatches([$r1], $map1);
    expect($mismatches1)->toHaveCount(1)
        ->and($mismatches1[0])->toContain('mapped as auth, actual is public');

    $r2 = new Route(['GET'], 'adm', fn () => null);
    $r2->name('r2.adm');
    $r2->middleware(['role:admin']);
    // Actual is role:admin, but map expects public
    $map2 = ['r2.adm' => 'public'];
    $mismatches2 = RouteAuthorizationScanner::staticMismatches([$r2], $map2);
    expect($mismatches2)->toHaveCount(1)
        ->and($mismatches2[0])->toContain('mapped as public, actual is role:admin');

    // Expected is role, actual is public (string)
    $r3 = new Route(['GET'], 'str', fn () => null);
    $r3->name('r3.str');
    $map3 = ['r3.str' => ['roles' => ['manager']]];
    $mismatches3 = RouteAuthorizationScanner::staticMismatches([$r3], $map3);
    expect($mismatches3)->toHaveCount(1)
        ->and($mismatches3[0])->toContain('mapped as role:manager, actual is public');

    // Expected roles differ from actual roles
    $r4 = new Route(['GET'], 'roles', fn () => null);
    $r4->name('r4.roles');
    $r4->middleware(['role:admin']);
    $map4 = ['r4.roles' => ['roles' => ['admin', 'supervisor']]];
    $mismatches4 = RouteAuthorizationScanner::staticMismatches([$r4], $map4);
    expect($mismatches4)->toHaveCount(1)
        ->and($mismatches4[0])->toContain('mapped roles [admin,supervisor] != actual roles [admin]');
});

it('prepares sorted executable role routes with parameter replacement and method extraction', function (): void {
    $rZ = new Route(['POST', 'HEAD'], 'zone/{zone_id}/edit', fn () => null);
    $rZ->name('zone.edit');

    $rA = new Route(['GET', 'HEAD'], 'alpha/{item}', fn () => null);
    $rA->name('alpha.show');

    $rUnmapped = new Route(['GET'], 'unmapped', fn () => null);
    $rUnmapped->name('unmapped.route');

    $rNoParamsClosure = new Route(['GET'], 'missing/{id}', fn () => null);
    $rNoParamsClosure->name('missing.closure');

    $map = [
        'zone.edit' => [
            'roles' => ['admin'],
            'params' => fn () => ['zone_id' => 99],
            'setup' => fn () => 'custom_setup',
        ],
        'alpha.show' => [
            'roles' => ['viewer'],
            'params' => fn () => ['item' => 'abc'],
        ],
        'missing.closure' => [
            'roles' => ['editor'],
        ],
        'unmapped.route' => 'public',
    ];

    $executable = RouteAuthorizationScanner::executableRoleRoutes([$rZ, $rA, $rUnmapped, $rNoParamsClosure], $map);

    expect($executable)->toHaveCount(2)
        // Check sorting: alpha.show should come before zone.edit
        ->and($executable[0]['name'])->toBe('alpha.show')
        ->and($executable[0]['method'])->toBe('GET')
        ->and($executable[0]['uri'])->toBe('alpha/abc')
        ->and($executable[0]['roles'])->toBe(['viewer'])
        ->and($executable[0]['setup'])->toBeNull()
        // Check zone.edit
        ->and($executable[1]['name'])->toBe('zone.edit')
        ->and($executable[1]['method'])->toBe('POST')
        ->and($executable[1]['uri'])->toBe('zone/99/edit')
        ->and($executable[1]['roles'])->toBe(['admin'])
        ->and(is_callable($executable[1]['setup']))->toBeTrue();
});

it('resolves unnamed route keys and middleware role intersections', function (): void {
    $unnamed = new Route(['GET', 'POST', 'HEAD'], 'api/v1/ping', fn () => null);
    expect(RouteAuthorizationScanner::routeKey($unnamed))->toBe('GET|POST api/v1/ping');

    // Route with multiple role middleware intersects
    $multiRole = new Route(['GET'], 'secure', fn () => null);
    $multiRole->middleware(['role:admin, manager', 'role:admin, auditor']);
    expect(RouteAuthorizationScanner::routeProtection($multiRole))->toBe(['roles' => ['admin']]);
});


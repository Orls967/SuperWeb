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

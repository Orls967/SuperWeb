<?php

declare(strict_types=1);

namespace App\Quality\Routing;

use Illuminate\Routing\Route;

/**
 * Route authorization matrix scanner (PROGRESS R0.9, KONSEP §A14.3).
 *
 * Verifies that all application routes are mapped, prevents weakening of
 * existing protections, and prepares executable routes for HTTP role tests.
 */
final class RouteAuthorizationScanner
{
    /**
     * Unique identifier for a route: route name, or "METHOD uri" if unnamed.
     */
    public static function routeKey(Route $route): string
    {
        $name = $route->getName();

        if ($name !== null && trim($name) !== '') {
            return $name;
        }

        $methods = implode('|', array_diff($route->methods(), ['HEAD']));

        return "{$methods} {$route->uri()}";
    }

    /**
     * Extracts declared protection from route middleware.
     *
     * @return 'public'|'auth'|array{roles: list<string>}
     */
    public static function routeProtection(Route $route): string|array
    {
        $middleware = $route->gatherMiddleware();
        $roles = null;
        $isAuth = false;

        foreach ($middleware as $m) {
            if (str_starts_with($m, 'role:')) {
                $parsed = array_values(array_filter(array_map('trim', explode(',', substr($m, 5)))));
                $roles = $roles === null
                    ? $parsed
                    : array_values(array_intersect($roles, $parsed));
            }
            if ($m === 'auth' || str_starts_with($m, 'auth:')) {
                $isAuth = true;
            }
        }

        if ($roles !== null && $roles !== []) {
            sort($roles);

            return ['roles' => $roles];
        }

        if ($isAuth) {
            return 'auth';
        }

        return 'public';
    }

    /**
     * Checks if current protection is weaker than baseline protection.
     *
     * A protection is weakened if:
     * - was role, now auth or public
     * - was role, now includes additional roles not originally permitted
     * - was auth, now public
     * Strengthening (public -> auth/role, auth -> role, role -> more restricted roles) is permitted.
     *
     * @param  'public'|'auth'|array{roles: list<string>}  $baseline
     * @param  'public'|'auth'|array{roles: list<string>}  $current
     */
    public static function isWeakened(string|array $baseline, string|array $current): bool
    {
        if ($baseline === 'public') {
            return false;
        }

        if ($baseline === 'auth') {
            return $current === 'public';
        }

        // Baseline is role-restricted
        if (is_string($current)) {
            return true; // Dropped to auth or public
        }

        $baseRoles = $baseline['roles'] ?? [];
        $curRoles = $current['roles'] ?? [];

        // Any role in current that was not in baseline means protection was widened/weakened
        $addedRoles = array_diff($curRoles, $baseRoles);

        return $addedRoles !== [];
    }

    /**
     * Returns list of routes in $routes that have no entry in $map.
     *
     * @param  iterable<Route>  $routes
     * @param  array<string, mixed>  $map
     * @return list<string>
     */
    public static function unmappedRoutes(iterable $routes, array $map): array
    {
        $unmapped = [];

        foreach ($routes as $route) {
            $key = self::routeKey($route);

            if (! array_key_exists($key, $map)) {
                $unmapped[] = $key;
            }
        }

        sort($unmapped);

        return $unmapped;
    }

    /**
     * Returns list of parameterized routes that do not have a callable params closure.
     *
     * @param  iterable<Route>  $routes
     * @param  array<string, mixed>  $map
     * @return list<string> formatted as "route:{key}" for ratchet baseline
     */
    public static function pendingDynamicRoutes(iterable $routes, array $map): array
    {
        $pending = [];

        foreach ($routes as $route) {
            if (! str_contains($route->uri(), '{')) {
                continue;
            }

            $key = self::routeKey($route);
            $mapped = $map[$key] ?? null;

            if (is_array($mapped) && isset($mapped['params']) && is_callable($mapped['params'])) {
                continue;
            }

            $pending[] = "route:{$key}";
        }

        $pending = array_values(array_unique($pending));
        sort($pending);

        return $pending;
    }

    /**
     * Finds discrepancies between route-roles.php declarations and actual middleware.
     *
     * @param  iterable<Route>  $routes
     * @param  array<string, mixed>  $map
     * @return list<string>
     */
    public static function staticMismatches(iterable $routes, array $map): array
    {
        $mismatches = [];

        foreach ($routes as $route) {
            $key = self::routeKey($route);

            if (! array_key_exists($key, $map)) {
                continue;
            }

            $expected = $map[$key];
            $actual = self::routeProtection($route);

            if (is_string($expected)) {
                if ($expected !== $actual) {
                    $mismatches[] = "{$key}: mapped as {$expected}, actual is ".(is_string($actual) ? $actual : 'role:'.implode(',', $actual['roles']));
                }
            } elseif (is_array($expected)) {
                $expectedRoles = $expected['roles'] ?? [];
                sort($expectedRoles);

                if (! is_array($actual)) {
                    $mismatches[] = "{$key}: mapped as role:".implode(',', $expectedRoles).", actual is {$actual}";
                } else {
                    $actualRoles = $actual['roles'];
                    sort($actualRoles);

                    if ($expectedRoles !== $actualRoles) {
                        $mismatches[] = "{$key}: mapped roles [".implode(',', $expectedRoles).'] != actual roles ['.implode(',', $actualRoles).']';
                    }
                }
            }
        }

        sort($mismatches);

        return $mismatches;
    }

    /**
     * Collects role-protected routes ready for HTTP testing.
     *
     * @param  iterable<Route>  $routes
     * @param  array<string, mixed>  $map
     * @return list<array{name: string, method: string, uri: string, roles: list<string>}>
     */
    public static function executableRoleRoutes(iterable $routes, array $map): array
    {
        $executable = [];

        foreach ($routes as $route) {
            $key = self::routeKey($route);
            $mapped = $map[$key] ?? null;

            if (! is_array($mapped) || empty($mapped['roles'])) {
                continue;
            }

            $hasParams = str_contains($route->uri(), '{');
            $hasClosure = isset($mapped['params']) && is_callable($mapped['params']);

            if ($hasParams && ! $hasClosure) {
                continue;
            }

            $uri = $route->uri();

            if ($hasParams && $hasClosure) {
                $params = ($mapped['params'])();
                foreach ($params as $paramKey => $paramVal) {
                    $uri = str_replace('{'.$paramKey.'}', (string) $paramVal, $uri);
                }
            }

            $method = array_values(array_diff($route->methods(), ['HEAD']))[0] ?? 'GET';

            $executable[] = [
                'name' => $key,
                'method' => $method,
                'uri' => $uri,
                'roles' => $mapped['roles'],
                'setup' => $mapped['setup'] ?? null,
            ];
        }

        usort($executable, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $executable;
    }
}

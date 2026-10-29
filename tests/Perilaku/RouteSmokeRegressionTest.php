<?php

declare(strict_types=1);

namespace Tests\Perilaku;

use App\Models\User;
use Modules\Core\Domain\Models\Role;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku')]
class RouteSmokeRegressionTest extends TestCase
{
    /**
     * Rute-rute yang dipulihkan di Bagian D.
     * Format: ['uri' => '...', 'role' => '...', 'method' => 'GET', 'expected_status' => [200, 302]]
     *
     * @var array<int, array{uri: string, role: string, method?: string, expected_status?: array<int>}>
     */
    public static array $fixedRoutes = [];

    public function test_fixed_routes_do_not_regress(): void
    {
        if (empty(static::$fixedRoutes)) {
            $this->assertTrue(true, 'Belum ada rute teregister untuk regresi; diisi di Bagian D.');

            return;
        }

        foreach (static::$fixedRoutes as $routeInfo) {
            $uri = $routeInfo['uri'];
            $role = $routeInfo['role'];
            $expected = $routeInfo['expected_status'] ?? [200, 302];

            $user = User::where('role', $role)->first();
            if (! $user) {
                $user = User::factory()->create([
                    'role' => $role,
                    'email_verified_at' => now(),
                ]);
                $r = Role::where('name', $role)->first();
                if ($r) {
                    $user->assignRbacRole($r);
                }
            }

            $response = $this->actingAs($user)->get($uri);
            $this->assertContains(
                $response->getStatusCode(),
                $expected,
                "Regresi pada rute {$uri} dengan role {$role}: status {$response->getStatusCode()}"
            );
        }
    }
}

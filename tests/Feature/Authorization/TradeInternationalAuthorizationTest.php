<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TradeInternationalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('tradeRoutesProvider')]
    public function test_trade_and_trade_finance_routes_allow_authorized_roles(string $route): void
    {
        $allowedRoles = ['admin', 'auditor', 'procurement', 'treasury'];

        foreach ($allowedRoles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->get(route($route));
            $response->assertOk();
        }
    }

    #[DataProvider('tradeRoutesProvider')]
    public function test_trade_and_trade_finance_routes_deny_unauthorized_roles(string $route): void
    {
        $unauthorizedRoles = ['driver', 'customer', 'cashier', 'waiter'];

        foreach ($unauthorizedRoles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->get(route($route));
            $response->assertStatus(403);
        }
    }

    #[DataProvider('tradeRoutesProvider')]
    public function test_trade_and_trade_finance_routes_redirect_guest(string $route): void
    {
        $response = $this->get(route($route));
        $response->assertRedirect('/login');
    }

    #[DataProvider('internationalRoutesProvider')]
    public function test_international_and_intercompany_routes_allow_authorized_roles(string $route): void
    {
        $allowedRoles = ['admin', 'auditor', 'legal', 'treasury'];

        foreach ($allowedRoles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->get(route($route));
            $response->assertOk();
        }
    }

    #[DataProvider('internationalRoutesProvider')]
    public function test_international_and_intercompany_routes_deny_unauthorized_roles(string $route): void
    {
        $unauthorizedRoles = ['driver', 'customer', 'cashier', 'waiter'];

        foreach ($unauthorizedRoles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->get(route($route));
            $response->assertStatus(403);
        }
    }

    #[DataProvider('internationalRoutesProvider')]
    public function test_international_and_intercompany_routes_redirect_guest(string $route): void
    {
        $response = $this->get(route($route));
        $response->assertRedirect('/login');
    }

    public static function tradeRoutesProvider(): array
    {
        return [
            ['trade.index'],
            ['trade_finance.index'],
            ['trade_finance.lcs'],
        ];
    }

    public static function internationalRoutesProvider(): array
    {
        return [
            ['international.index'],
            ['international.jvs'],
            ['intercompany.index'],
            ['intercompany.transactions'],
        ];
    }
}

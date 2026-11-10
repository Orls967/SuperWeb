<?php

declare(strict_types=1);

namespace Tests\Acceptance\EnterpriseFinance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnterpriseFinanceAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function roleAccessDataProvider(): array
    {
        return [
            'admin can access enterprise finance index' => ['admin', 200],
            'auditor can access enterprise finance index' => ['auditor', 200],
            'treasury can access enterprise finance index' => ['treasury', 200],
            'procurement forbidden' => ['procurement', 403],
            'customer forbidden' => ['customer', 403],
            'driver forbidden' => ['driver', 403],
        ];
    }

    #[DataProvider('roleAccessDataProvider')]
    public function test_enterprise_finance_routes_access_control(string $role, int $expectedStatus): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->get('/enterprise-finance');
        $response->assertStatus($expectedStatus);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/enterprise-finance');
        $response->assertRedirect('/login');
    }
}

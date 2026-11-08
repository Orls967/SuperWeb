<?php

declare(strict_types=1);

namespace Modules\Aviation\Tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Aviation\database\seeders\AviationRbacSeeder;
use Tests\TestCase;

final class AviationHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(AviationRbacSeeder::class);
    }

    public function test_ops_endpoint_accessible(): void
    {
        $user = User::factory()->create();
        $user->assignRole('ops_controller');

        $response = $this->actingAs($user)->get('/aviation/ops');
        $response->assertOk();
    }
}

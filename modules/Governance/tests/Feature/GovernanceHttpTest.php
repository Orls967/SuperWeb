<?php

declare(strict_types=1);

namespace Modules\Governance\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Domain\Models\Role;
use Tests\TestCase;

class GovernanceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'governance_admin'], ['label' => 'Governance Admin', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'voter'], ['label' => 'Voter', 'is_system' => true]);
        Role::firstOrCreate(['name' => 'guest'], ['label' => 'Guest User', 'is_system' => true]);
    }

    public function test_governance_web_index_accessible_for_authorized_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $voter = User::factory()->create(['role' => 'voter']);
        $guest = User::factory()->create(['role' => 'guest']);

        $this->actingAs($admin)->get('/governance')->assertOk();
        $this->actingAs($voter)->get('/governance')->assertOk();
        $this->actingAs($guest)->get('/governance')->assertForbidden();
    }
}

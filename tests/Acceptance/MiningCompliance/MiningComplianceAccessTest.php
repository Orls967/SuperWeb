<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningCompliance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mining\database\seeders\MiningRbacSeeder;
use Tests\TestCase;

final class MiningComplianceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_access_matrix_for_mining_compliance_fase_94(): void
    {
        $this->seed(MiningRbacSeeder::class);

        $weighbridgeOp = User::factory()->create();
        $weighbridgeOp->assignRole('weighbridge_operator');

        $metallurgist = User::factory()->create();
        $metallurgist->assignRole('metallurgist');

        $royaltyOfficer = User::factory()->create();
        $royaltyOfficer->assignRole('royalty_officer');

        $hseOfficer = User::factory()->create();
        $hseOfficer->assignRole('hse_officer');

        $mineManager = User::factory()->create();
        $mineManager->assignRole('mine_manager');

        $offtakeManager = User::factory()->create();
        $offtakeManager->assignRole('offtake_manager');

        $offtaker = User::factory()->create();
        $offtaker->assignRole('offtaker');

        $this->actingAs($weighbridgeOp)->get('/mining/weighbridge')->assertOk();
        $this->actingAs($weighbridgeOp)->get('/mining/royalty')->assertForbidden();

        $this->actingAs($metallurgist)->get('/mining/grade')->assertOk();
        $this->actingAs($metallurgist)->get('/mining/offtake')->assertForbidden();

        $this->actingAs($royaltyOfficer)->get('/mining/royalty')->assertOk();
        $this->actingAs($royaltyOfficer)->get('/mining/grade')->assertForbidden();

        $this->actingAs($hseOfficer)->get('/mining/hse')->assertOk();
        $this->actingAs($hseOfficer)->get('/mining/royalty')->assertForbidden();

        $this->actingAs($mineManager)->get('/mining/reclamation')->assertOk();
        $this->actingAs($offtakeManager)->get('/mining/offtake')->assertOk();
        $this->actingAs($offtaker)->get('/offtaker/mining')->assertOk();
    }
}

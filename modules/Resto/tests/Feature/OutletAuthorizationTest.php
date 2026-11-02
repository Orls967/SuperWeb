<?php

declare(strict_types=1);

namespace Modules\Resto\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\RestoStaffAssignment;
use Tests\TestCase;

class OutletAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_outlet_manager_cannot_view_or_update_other_outlet(): void
    {
        $outlet1 = Outlet::create([
            'code' => 'OUT-1',
            'name' => 'Outlet Satu',
            'type' => 'outlet',
            'address' => 'Jl. Satu',
            'city' => 'Jakarta',
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'is_active' => true,
        ]);
        $outlet2 = Outlet::create([
            'code' => 'OUT-2',
            'name' => 'Outlet Dua',
            'type' => 'outlet',
            'address' => 'Jl. Dua',
            'city' => 'Jakarta',
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'is_active' => true,
        ]);

        $manager2 = User::factory()->create(['role' => 'outlet_manager']);
        RestoStaffAssignment::create([
            'user_id' => $manager2->id,
            'outlet_id' => $outlet2->id,
            'role' => 'outlet_manager',
            'is_active' => true,
        ]);

        // Manager 2 accessing Outlet 1 must be 403
        $this->actingAs($manager2)->get(route('resto.outlets.show', $outlet1))->assertStatus(403);
        $this->actingAs($manager2)->get(route('resto.outlets.edit', $outlet1))->assertStatus(403);
        $this->actingAs($manager2)->put(route('resto.outlets.update', $outlet1), [
            'code' => 'OUT-1-MOD',
            'name' => 'Outlet Satu Modified',
            'type' => 'outlet',
            'address' => 'Jl. Satu',
            'city' => 'Jakarta',
            'opens_at' => '08:00',
            'closes_at' => '22:00',
        ])->assertStatus(403);

        // Manager 2 accessing Outlet 2 must be 200
        $this->actingAs($manager2)->get(route('resto.outlets.show', $outlet2))->assertStatus(200);
    }
}

<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\CkAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\CloudKitchen\database\seeders\CloudKitchenDemoSeeder;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;
use Tests\TestCase;

class CloudKitchenVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new CloudKitchenDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_cloud_kitchen_index_and_dispatch_meal(): void
    {
        $manager = User::factory()->create(['role' => 'outlet_manager']);
        $subscription = CateringSubscription::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($manager)->get(route('cloud_kitchen.index'));
        $response->assertOk();
        $response->assertSeeText('Cloud Kitchen & Corporate Catering Management');
        $response->assertSee($subscription->subscription_code);

        // 2. Dispatch meal
        $dispatchResponse = $this->actingAs($manager)->post(
            route('cloud_kitchen.subscriptions.dispatch', $subscription),
            ['meal_slot' => 'dinner']
        );

        $dispatchResponse->assertRedirect(route('cloud_kitchen.index'));
        $dispatchResponse->assertSessionHas('success');

        $this->assertDatabaseHas('ckt_catering_orders', [
            'subscription_id' => $subscription->id,
            'meal_slot' => 'dinner',
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'doctor']);

        $response = $this->actingAs($guest)->get(route('cloud_kitchen.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('cloud_kitchen.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_cloud_kitchen_audit_passes_cleanly(): void
    {
        $code = Artisan::call('ck:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_ck_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new CkAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('ck:audit');
        $this->assertSame(1, $code);
    }
}

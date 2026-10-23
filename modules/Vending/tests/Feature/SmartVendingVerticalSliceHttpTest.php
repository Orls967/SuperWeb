<?php

declare(strict_types=1);

namespace Modules\Vending\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\VndAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Vending\database\seeders\VendingDemoSeeder;
use Modules\Vending\Domain\Models\VendingUnit;
use Tests\TestCase;

class SmartVendingVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new VendingDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_vending_index_and_dispense(): void
    {
        $operator = User::factory()->create(['role' => 'vending_operator']);
        $unit = VendingUnit::firstOrFail();

        // 1. Access dashboard
        $response = $this->actingAs($operator)->get(route('vending.index'));
        $response->assertOk();
        $response->assertSeeText('AI Demand & Waste Forecasting & Smart Vending');
        $response->assertSee($unit->unit_code);

        // 2. Dispense test purchase
        $dispenseResponse = $this->actingAs($operator)->post(route('vending.units.dispense', $unit), [
            'item_code' => 'SNK-001',
            'qty' => 2,
            'price_idr' => 15000,
            'payment_method' => 'QRIS',
        ]);

        $dispenseResponse->assertRedirect(route('vending.index'));
        $dispenseResponse->assertSessionHas('success');

        $this->assertDatabaseHas('ven_vending_transactions', [
            'vending_unit_id' => $unit->id,
            'item_code' => 'SNK-001',
            'qty' => 2,
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'supplier']);

        $response = $this->actingAs($guest)->get(route('vending.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('vending.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_smart_vending_audit_passes_cleanly(): void
    {
        $code = Artisan::call('vnd:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_vnd_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new VndAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('vnd:audit');
        $this->assertSame(1, $code);
    }
}

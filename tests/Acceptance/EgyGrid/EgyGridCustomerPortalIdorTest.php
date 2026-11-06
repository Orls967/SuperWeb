<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyGrid;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Egy\database\seeders\EgyRbacSeeder;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Tests\TestCase;

class EgyGridCustomerPortalIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_portal_prevents_idor_access(): void
    {
        $this->seed();
        (new EgyRbacSeeder)->run();

        $userA = User::factory()->create();
        $userA->assignRole('customer');

        $userB = User::factory()->create();
        $userB->assignRole('customer');

        $meterA = SmartMeter::create([
            'meter_code' => 'MTR-CUST-A',
            'utility_type' => 'electricity',
            'property_id' => 'PROP-A',
            'node_id' => 'NODE-A',
            'customer_party_id' => (string) $userA->id,
            'is_active' => true,
        ]);

        $meterB = SmartMeter::create([
            'meter_code' => 'MTR-CUST-B',
            'utility_type' => 'electricity',
            'property_id' => 'PROP-B',
            'node_id' => 'NODE-B',
            'customer_party_id' => (string) $userB->id,
            'is_active' => true,
        ]);

        $invA = UtilityConsolidatedInvoice::create([
            'invoice_number' => 'INV-EGY-A-001',
            'property_id' => 'PROP-A',
            'customer_party_id' => (string) $userA->id,
            'period' => '2026-11',
            'total_amount_idr' => 500000,
            'status' => 'issued',
        ]);

        $invB = UtilityConsolidatedInvoice::create([
            'invoice_number' => 'INV-EGY-B-001',
            'property_id' => 'PROP-B',
            'customer_party_id' => (string) $userB->id,
            'period' => '2026-11',
            'total_amount_idr' => 750000,
            'status' => 'issued',
        ]);

        // User A accesses their own portal -> 200
        $resA = $this->actingAs($userA)->get("/customer/energy/bills/{$invA->id}");
        $this->assertContains($resA->getStatusCode(), [200, 302]);

        // User A attempts to access User B's invoice -> 403 Forbidden (Anti-IDOR)
        $resIdor = $this->actingAs($userA)->get("/customer/energy/bills/{$invB->id}");
        $this->assertEquals(403, $resIdor->getStatusCode());
    }
}

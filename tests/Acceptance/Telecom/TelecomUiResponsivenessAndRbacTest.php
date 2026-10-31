<?php

declare(strict_types=1);

namespace Tests\Acceptance\Telecom;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;
use Tests\TestCase;

class TelecomUiResponsivenessAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_telecom_dashboard_accessible_to_authorized_roles(): void
    {
        $admin = User::factory()->create(['role' => 'telecom_admin']);
        $nocOps = User::factory()->create(['role' => 'noc_operator']);
        $dcOps = User::factory()->create(['role' => 'datacenter_ops']);

        $this->actingAs($admin)->get('/telecom')->assertStatus(200);
        $this->actingAs($nocOps)->get('/telecom')->assertStatus(200);
        $this->actingAs($dcOps)->get('/telecom')->assertStatus(200);
    }

    public function test_telecom_dashboard_forbidden_to_unauthorized_roles(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($customer)->get('/telecom')->assertStatus(403);
        $this->actingAs($student)->get('/telecom')->assertStatus(403);
    }

    public function test_noc_alarms_accessible_only_to_noc_roles(): void
    {
        $nocOps = User::factory()->create(['role' => 'noc_operator']);
        $billing = User::factory()->create(['role' => 'telecom_billing']);

        $this->actingAs($nocOps)->get('/telecom/noc/alarms')->assertStatus(200);
        $this->actingAs($billing)->get('/telecom/noc/alarms')->assertStatus(403);
    }

    public function test_datacenter_colocation_accessible_only_to_dc_ops_and_admin(): void
    {
        $dcOps = User::factory()->create(['role' => 'datacenter_ops']);
        $nocOps = User::factory()->create(['role' => 'noc_operator']);

        $this->actingAs($dcOps)->get('/telecom/datacenter/colocation')->assertStatus(200);
        $this->actingAs($nocOps)->get('/telecom/datacenter/colocation')->assertStatus(403);
    }

    public function test_billing_connectivity_accessible_to_billing_role(): void
    {
        $billing = User::factory()->create(['role' => 'telecom_billing']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($billing)->get('/telecom/billing/connectivity')->assertStatus(200);
        $this->actingAs($student)->get('/telecom/billing/connectivity')->assertStatus(403);
    }

    public function test_anti_idor_protects_connectivity_invoice_details_endpoint(): void
    {
        $invoice = IotConnectivityInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV-TLX-SEC-01',
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'billing_period' => '2026-10',
            'active_device_count' => 10,
            'total_device_fee_minor' => 300000,
            'total_quota_mb' => 50000,
            'total_consumed_mb' => 45000,
            'excess_mb' => 0,
            'excess_charge_minor' => 0,
            'total_charge_minor' => 300000,
            'status' => 'ISSUED',
        ]);

        $userOtherEntity = User::factory()->create([
            'role' => 'telecom_billing',
            'entity_id' => 'ENT-MINING-01',
        ]);

        $userOwnEntity = User::factory()->create([
            'role' => 'telecom_billing',
            'entity_id' => 'ENT-LOGISTICS-01',
        ]);

        // Attempt by another entity must be 403 Forbidden
        $this->actingAs($userOtherEntity)
            ->get("/telecom/billing/connectivity/{$invoice->id}")
            ->assertStatus(403);

        // Owner entity can access
        $this->actingAs($userOwnEntity)
            ->get("/telecom/billing/connectivity/{$invoice->id}")
            ->assertStatus(200);
    }

    public function test_mobile_375px_responsiveness_for_noc_alarm_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'telecom_admin']);

        $response = $this->actingAs($admin)->get('/telecom/noc/alarms');
        $response->assertStatus(200);

        $html = $response->getContent();
        // Check viewport meta tag
        expect($html)->toContain('<meta name="viewport" content="width=device-width, initial-scale=1');
        // Check responsive container / flex / grid classes for mobile 375px
        expect($html)->toMatch('/(flex-col|grid-cols-1|overflow-x-auto|max-w-full)/');
    }

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $response = $this->get('/telecom');
        $response->assertRedirect('/login');
    }
}

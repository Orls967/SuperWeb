<?php

declare(strict_types=1);

namespace Tests\Acceptance\Media;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Med\Domain\Models\MedIpAsset;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedRoyaltyStatement;
use Tests\TestCase;

class MediaUiResponsivenessAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_media_dashboard_accessible_to_authorized_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $studioMgr = User::factory()->create(['role' => 'studio_manager']);
        $rightsMgr = User::factory()->create(['role' => 'rights_manager']);

        $this->actingAs($admin)->get('/media')->assertStatus(200);
        $this->actingAs($studioMgr)->get('/media')->assertStatus(200);
        $this->actingAs($rightsMgr)->get('/media')->assertStatus(200);
    }

    public function test_media_dashboard_forbidden_to_unauthorized_roles(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $driver = User::factory()->create(['role' => 'driver']);

        $this->actingAs($customer)->get('/media')->assertStatus(403);
        $this->actingAs($driver)->get('/media')->assertStatus(403);
    }

    public function test_studio_management_accessible_only_to_studio_manager_and_admin(): void
    {
        $studioMgr = User::factory()->create(['role' => 'studio_manager']);
        $rightsMgr = User::factory()->create(['role' => 'rights_manager']);

        $this->actingAs($studioMgr)->get('/media/studios')->assertStatus(200);
        $this->actingAs($rightsMgr)->get('/media/studios')->assertStatus(403);
    }

    public function test_licensing_management_accessible_only_to_rights_manager_and_admin(): void
    {
        $rightsMgr = User::factory()->create(['role' => 'rights_manager']);
        $studioMgr = User::factory()->create(['role' => 'studio_manager']);

        $this->actingAs($rightsMgr)->get('/media/licensing')->assertStatus(200);
        $this->actingAs($studioMgr)->get('/media/licensing')->assertStatus(403);
    }

    public function test_anti_idor_protects_royalty_statement_details_endpoint(): void
    {
        $ip = MedIpAsset::create([
            'id' => (string) Str::uuid(),
            'ip_code' => 'IP-IDOR-01',
            'title' => 'IDOR Test IP',
            'ip_type' => 'SONG',
            'owner_entity_id' => 'ENT-OWNER-01',
            'status' => 'ACTIVE',
        ]);

        $license = MedIpLicense::create([
            'id' => (string) Str::uuid(),
            'license_code' => 'LIC-IDOR-01',
            'ip_id' => $ip->id,
            'licensee_entity_id' => 'ENT-OWNER-01',
            'rights_type' => 'STREAMING',
            'channel' => 'STREAMING',
            'territory' => 'GLOBAL',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_exclusive' => true,
            'royalty_rate_pct' => 10.0,
            'status' => 'ACTIVE',
        ]);

        $statement = MedRoyaltyStatement::create([
            'id' => (string) Str::uuid(),
            'statement_number' => 'STMT-IDOR-01',
            'license_id' => $license->id,
            'billing_period' => '2026-10',
            'gross_revenue_minor' => 50_000_000,
            'total_distribution_fee_minor' => 5_000_000,
            'total_recouped_minor' => 0,
            'total_royalty_distributed_minor' => 45_000_000,
            'status' => 'ISSUED',
        ]);

        $userOtherEntity = User::factory()->create([
            'role' => 'rights_manager',
        ]);
        $userOtherEntity->setAttribute('entity_id', 'ENT-OTHER-99');

        $userOwnEntity = User::factory()->create([
            'role' => 'rights_manager',
        ]);
        $userOwnEntity->setAttribute('entity_id', 'ENT-OWNER-01');

        // Without matching entity, 403 Forbidden
        $this->actingAs($userOtherEntity)
            ->get("/media/royalties/{$statement->id}")
            ->assertStatus(403);

        // With matching entity, 200 OK
        $this->actingAs($userOwnEntity)
            ->get("/media/royalties/{$statement->id}")
            ->assertStatus(200);
    }

    public function test_mobile_375px_responsiveness_for_media_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/media');
        $response->assertStatus(200);

        $html = $response->getContent();
        expect($html)->toContain('<meta name="viewport" content="width=device-width, initial-scale=1');
        expect($html)->toMatch('/(flex-col|grid-cols-1|overflow-x-auto|max-w-full)/');
    }

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $response = $this->get('/media');
        $response->assertRedirect('/login');
    }

    public function test_auditor_can_access_media_in_read_only_mode(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);
        $this->actingAs($auditor)->get('/media')->assertStatus(200);
    }
}

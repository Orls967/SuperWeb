<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningCompliance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mining\database\seeders\MiningRbacSeeder;
use Modules\Mining\Domain\Models\MiningOfftakeContract;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

final class MiningComplianceOfftakerPortalIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_offtaker_portal_strictly_prevents_idor_access_to_other_buyer_contracts(): void
    {
        $this->seed(MiningRbacSeeder::class);

        $offtakerA = User::factory()->create();
        $offtakerA->assignRole('offtaker');

        $offtakerB = User::factory()->create();
        $offtakerB->assignRole('offtaker');

        $site = MiningSite::create([
            'site_code' => 'SITE-IDOR-94',
            'name' => 'Site Offtake IDOR',
            'commodity' => 'COAL',
            'location' => 'Kaltim',
            'timezone' => 'Asia/Makassar',
            'status' => 'ACTIVE',
        ]);

        $contractA = MiningOfftakeContract::create([
            'site_id' => $site->id,
            'contract_code' => 'CNT-BUYER-A',
            'buyer_party_ref' => (string) $offtakerA->id,
            'commodity' => 'COAL',
            'destination_type' => 'DOMESTIC',
            'contracted_tonnage_kg' => 10000000,
            'base_price_usd_cents' => 8500,
            'benchmark_gar_kcal' => 5000,
            'status' => 'ACTIVE',
        ]);

        $contractB = MiningOfftakeContract::create([
            'site_id' => $site->id,
            'contract_code' => 'CNT-BUYER-B',
            'buyer_party_ref' => (string) $offtakerB->id,
            'commodity' => 'COAL',
            'destination_type' => 'EXPORT',
            'contracted_tonnage_kg' => 20000000,
            'base_price_usd_cents' => 8500,
            'benchmark_gar_kcal' => 5000,
            'status' => 'ACTIVE',
        ]);

        $res = $this->actingAs($offtakerA)->get('/offtaker/mining');
        $res->assertOk();
        $res->assertSee('CNT-BUYER-A');
        $res->assertDontSee('CNT-BUYER-B');

        $this->actingAs($offtakerA)->post('/offtaker/mining/nominate', [
            'contract_id' => $contractB->id,
            'quantity_kg' => 5000000,
        ])->assertForbidden();
    }
}

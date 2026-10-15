<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\database\seeders\RbacSeeder;
use Modules\Core\Domain\Models\Role;
use Modules\Distribution\Domain\Models\Distributor;
use Tests\TestCase;

class AuditorReadOnlyPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * M-R0-18: 37 write routes must strictly forbid (403) users with role 'auditor'.
     */
    public function test_all_37_write_routes_forbid_auditor_role(): void
    {
        $writeRoutesWithParams = [
            // Manufacturing Costing
            ['method' => 'POST', 'name' => 'manufacturing.costing.versions.submit', 'uri' => '/manufacturing/costing/versions/1/submit'],
            ['method' => 'POST', 'name' => 'manufacturing.costing.versions.approve', 'uri' => '/manufacturing/costing/versions/1/approve'],

            // Manufacturing Quality
            ['method' => 'POST', 'name' => 'manufacturing.quality.plans.store', 'uri' => '/manufacturing/quality/plans'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.inspections.store', 'uri' => '/manufacturing/quality/inspections'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.inspections.waiver', 'uri' => '/manufacturing/quality/inspections/1/waiver'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.inspections.approve', 'uri' => '/manufacturing/quality/inspections/1/approve'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.ncrs.store', 'uri' => '/manufacturing/quality/ncrs'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.capas.store', 'uri' => '/manufacturing/quality/ncrs/1/capas'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.capas.complete', 'uri' => '/manufacturing/quality/capas/1/complete'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.lots.certificates', 'uri' => '/manufacturing/quality/lots/1/certificates'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.lots.release', 'uri' => '/manufacturing/quality/lots/1/release'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.lots.recall', 'uri' => '/manufacturing/quality/lots/1/recall'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.recalls.notify', 'uri' => '/manufacturing/quality/recalls/1/notify'],
            ['method' => 'POST', 'name' => 'manufacturing.quality.recalls.complete', 'uri' => '/manufacturing/quality/recalls/1/complete'],

            // Manufacturing Maintenance
            ['method' => 'POST', 'name' => 'manufacturing.maintenance.orders.store', 'uri' => '/manufacturing/maintenance/orders'],
            ['method' => 'POST', 'name' => 'manufacturing.maintenance.orders.transition', 'uri' => '/manufacturing/maintenance/orders/1/transition'],
            ['method' => 'POST', 'name' => 'manufacturing.maintenance.sensors.store', 'uri' => '/manufacturing/maintenance/sensors'],
            ['method' => 'POST', 'name' => 'manufacturing.maintenance.oee.compute', 'uri' => '/manufacturing/maintenance/oee'],
            ['method' => 'POST', 'name' => 'manufacturing.maintenance.parts.store', 'uri' => '/manufacturing/maintenance/parts'],
            ['method' => 'POST', 'name' => 'manufacturing.maintenance.incidents.store', 'uri' => '/manufacturing/maintenance/incidents'],
            ['method' => 'POST', 'name' => 'manufacturing.maintenance.incidents.close', 'uri' => '/manufacturing/maintenance/incidents/1/close'],
            ['method' => 'POST', 'name' => 'manufacturing.maintenance.permits.store', 'uri' => '/manufacturing/maintenance/permits'],
            ['method' => 'POST', 'name' => 'manufacturing.maintenance.permits.approve', 'uri' => '/manufacturing/maintenance/permits/1/approve'],

            // Partners
            ['method' => 'POST', 'name' => 'partners.store', 'uri' => '/partners'],
            ['method' => 'POST', 'name' => 'partners.transition', 'uri' => '/partners/1/transition'],

            // Pricing
            ['method' => 'POST', 'name' => 'pricing.price-lists.store', 'uri' => '/pricing/price-lists'],
            ['method' => 'POST', 'name' => 'pricing.price-lists.items.store', 'uri' => '/pricing/price-lists/items'],
            ['method' => 'POST', 'name' => 'pricing.price-lists.activate', 'uri' => '/pricing/price-lists/1/activate'],
            ['method' => 'POST', 'name' => 'pricing.discounts.store', 'uri' => '/pricing/discounts'],
            ['method' => 'POST', 'name' => 'pricing.promotions.store', 'uri' => '/pricing/promotions'],
            ['method' => 'POST', 'name' => 'pricing.promotions.claims.store', 'uri' => '/pricing/promotions/claims'],
            ['method' => 'POST', 'name' => 'pricing.claims.validate', 'uri' => '/pricing/claims/1/validate'],
            ['method' => 'POST', 'name' => 'pricing.claims.settle', 'uri' => '/pricing/claims/1/settle'],
            ['method' => 'POST', 'name' => 'pricing.margin-policies.store', 'uri' => '/pricing/margin-policies'],
            ['method' => 'POST', 'name' => 'pricing.overrides.store', 'uri' => '/pricing/overrides'],
            ['method' => 'POST', 'name' => 'pricing.overrides.decide', 'uri' => '/pricing/overrides/1/decide'],
            ['method' => 'POST', 'name' => 'pricing.analytics.compute', 'uri' => '/pricing/analytics'],
        ];

        $this->assertCount(37, $writeRoutesWithParams);

        $auditor = User::factory()->create(['role' => 'auditor']);

        foreach ($writeRoutesWithParams as $r) {
            $response = $this->actingAs($auditor)->call($r['method'], $r['uri']);
            $this->assertSame(
                403,
                $response->status(),
                "Rute tulis {$r['name']} ({$r['method']} {$r['uri']}) HARUS 403 untuk role auditor (M-R0-18), tetapi menghasilkan {$response->status()}."
            );
        }
    }

    /**
     * M-R0-12: distribution.portal.home allows admin (fallback distributor if none assigned).
     */
    public function test_distribution_portal_home_allows_admin(): void
    {
        Distributor::create([
            'code' => 'DST-TEST-01',
            'name' => 'Distributor Utama',
            'tier' => 'gold',
            'status' => 'approved',
            'credit_limit_idr' => 100_000_000,
            'payment_terms_days' => 30,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('distribution.portal.home'));

        $this->assertNotSame(403, $response->status(), 'Admin tidak boleh 403 saat membuka portal distributor (M-R0-12).');
        $this->assertSame(200, $response->status());
    }

    /**
     * M-R0-13: storage.local.upload returns 403 when URL signature is missing or invalid.
     */
    public function test_storage_local_upload_aborts_403_without_valid_signature(): void
    {
        $response = $this->put('/storage/test-avatar.png');
        $this->assertSame(403, $response->status(), 'storage.local.upload harus 403 bila signature tidak valid (M-R0-13).');
    }

    /**
     * D1: RbacSeeder seeds all 34 roles (26 legacy + 8 domain roles Fase R3).
     */
    public function test_rbac_seeder_contains_34_roles(): void
    {
        $this->seed(RbacSeeder::class);

        $expectedRoles = [
            'admin', 'customer', 'mekanik', 'tenant', 'outlet_manager', 'kitchen', 'cashier',
            'shipper', 'driver', 'dispatcher', 'hub_operator', 'logistics_admin', 'party_manager',
            'contract_manager', 'legal', 'asset_manager', 'auditor', 'supplier', 'procurement',
            'distributor', 'agent', 'partner', 'treasury', 'planner', 'operator', 'qc_inspector',
            'mall_admin', 'technician', 'hcm_manager', 'rnd_specialist', 'esg_officer', 'b2b_buyer',
            'farmer', 'epc_manager',
        ];

        $this->assertSame(34, count($expectedRoles));
        $this->assertSame(34, Role::count(), 'RBAC Seeder harus menghasilkan tepat 34 role.');

        foreach ($expectedRoles as $roleName) {
            $this->assertTrue(Role::where('name', $roleName)->exists(), "Role '{$roleName}' harus terdaftar di tabel roles.");
        }
    }
}

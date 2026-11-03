<?php

declare(strict_types=1);

namespace Tests\Acceptance\FashionTextile;

use App\Models\User;
use App\Quality\Audit\Fixtures\FashionAuditCorruptionFixture;
use Database\Seeders\FashionTextileT1Seeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Fashion\Application\Services\FashionSourcingService;
use Modules\Fashion\Domain\Models\FashionBuyPlan;
use Modules\Fashion\Domain\Models\FashionCollection;
use Modules\Fashion\Domain\Models\FashionSample;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;
use Tests\TestCase;

class FashionTextileAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_access_fashion_routes(): void
    {
        $buyer = User::factory()->create(['role' => 'fashion_buyer']);
        $merchandiser = User::factory()->create(['role' => 'merchandiser']);
        $cashier = User::factory()->create(['role' => 'cashier']);

        // Buyer & Merchandiser diizinkan
        $this->actingAs($buyer)->get(route('fashion.collections.index'))->assertStatus(200);
        $this->actingAs($merchandiser)->get(route('fashion.collections.index'))->assertStatus(200);

        // Role tidak berhak ditolak 403
        $this->actingAs($cashier)->get(route('fashion.collections.index'))->assertStatus(403);
    }

    public function test_hamilton_apportionment_exact_vectors(): void
    {
        $lr = new LargestRemainder;

        // Vektor 1: 1.000 pcs bobot 2/3/3/1 -> 222/334/333/111
        $items1 = [
            ['key' => 'S', 'weight' => 2],
            ['key' => 'M', 'weight' => 3],
            ['key' => 'L', 'weight' => 3],
            ['key' => 'XL', 'weight' => 1],
        ];
        $res1 = $lr->apportion(1000, $items1);
        $this->assertSame([
            'S' => 222,
            'M' => 333,
            'L' => 334,
            'XL' => 111,
        ], $res1);
        $this->assertSame(1000, array_sum($res1));

        // Vektor 2: 250 pcs bobot 1/2/2/1 -> 42/83/83/42
        $items2 = [
            ['key' => 'S', 'weight' => 1],
            ['key' => 'M', 'weight' => 2],
            ['key' => 'L', 'weight' => 2],
            ['key' => 'XL', 'weight' => 1],
        ];
        $res2 = $lr->apportion(250, $items2);
        $this->assertSame([
            'S' => 42,
            'M' => 83,
            'L' => 83,
            'XL' => 42,
        ], $res2);
        $this->assertSame(250, array_sum($res2));
    }

    public function test_production_order_allocation_conservation_invariant(): void
    {
        $sourcing = app(FashionSourcingService::class);

        $col = $sourcing->createCollection('COL-TEST-01', 'Koleksi Tenun Nusantara', 'FW26', 2026);
        $sample = $sourcing->createSample($col->id, 'SMP-TEST-01', 'Kemeja Tenun Sutra', 'TP-001');
        $sample = $sourcing->recordSampleReview($sample->id, 'APPROVED');

        $buyPlan = $sourcing->createBuyPlan($col->id, 'BUY-TEST-01', 5000, 750_000_000);

        $order = $sourcing->allocateAndIssueProductionOrder(
            $buyPlan->id,
            $sample->id,
            'PO-TEST-001',
            'PT Batik & Garmen Nusantara',
            1000,
            150_000,
            ['S' => 2, 'M' => 3, 'L' => 3, 'XL' => 1],
            ['NAVY', 'MAROON']
        );

        $this->assertSame(1000, $order->total_ordered_units);
        $this->assertSame(1000, (int) $order->allocations->sum('allocated_units'));
        $this->assertCount(8, $order->allocations); // 2 colors x 4 sizes
    }

    public function test_unapproved_sample_blocks_production_order(): void
    {
        $sourcing = app(FashionSourcingService::class);

        $col = $sourcing->createCollection('COL-TEST-02', 'Koleksi Belum Siap', 'SS26', 2026);
        $sample = $sourcing->createSample($col->id, 'SMP-TEST-PENDING', 'Prototipe Mentah', 'TP-002');
        // Sampel masih PENDING (belum disetujui)

        $buyPlan = $sourcing->createBuyPlan($col->id, 'BUY-TEST-02', 1000, 150_000_000);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('PO produksi diblokir: sampel SMP-TEST-PENDING belum berstatus APPROVED.');

        $sourcing->allocateAndIssueProductionOrder(
            $buyPlan->id,
            $sample->id,
            'PO-TEST-BLOCKED',
            'PT Pabrik Cepat',
            1000,
            150_000,
            ['M' => 1],
            ['BLACK']
        );
    }

    public function test_fashion_audit_clean_and_corruption_fixture(): void
    {
        // Kondisi bersih
        $this->assertSame(0, Artisan::call('fsh:audit'));

        // Sabotase menggunakan corruption fixture
        $fixture = new FashionAuditCorruptionFixture;
        $expectedDiscrepancy = $fixture->corrupt();
        $this->assertSame('fsh.allocation_conservation_mismatch', $expectedDiscrepancy);

        $this->assertSame(1, Artisan::call('fsh:audit'));
    }

    public function test_t1_seeder_simulation_and_audit(): void
    {
        $this->seed(FashionTextileT1Seeder::class);

        $this->assertSame(20, FashionCollection::count());
        $this->assertSame(50, FashionSample::where('approval_status', 'APPROVED')->count());
        $this->assertSame(20, FashionBuyPlan::count());
        $this->assertSame(2000, DB::table('fsh_production_allocations')->count());

        $this->assertSame(0, Artisan::call('fsh:audit'));
    }
}

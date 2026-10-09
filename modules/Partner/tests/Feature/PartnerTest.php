<?php

declare(strict_types=1);

namespace Tests\Feature\Partner;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Partner\Application\Services\PartnerService;
use Modules\Partner\Domain\Models\Partner;
use Tests\TestCase;

class PartnerTest extends TestCase
{
    use RefreshDatabase;

    private PartnerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->svc = app(PartnerService::class);
    }

    private function make(string $code, string $kind = 'strategic'): Partner
    {
        return $this->svc->registerPartner(['code' => $code, 'name' => "Mitra {$code}", 'kind' => $kind]);
    }

    public function test_lifecycle_guards(): void
    {
        $p = $this->make('P-1');
        $this->assertSame('prospect', $p->status);

        try {
            $this->svc->transition($p, 'active');
            $this->fail('Lompat status harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak sah', $e->getMessage());
        }

        foreach (['due_diligence', 'negotiation', 'active', 'review'] as $s) {
            $p = $this->svc->transition($p, $s);
        }
        $this->assertSame('review', $p->status);

        $this->expectException(InvalidArgumentException::class);
        $this->make('P-1');
    }

    public function test_invalid_kind_and_due_diligence(): void
    {
        try {
            $this->make('P-2', 'bogus');
            $this->fail('Jenis tidak sah.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak dikenal', $e->getMessage());
        }

        $p = $this->make('P-3');
        $this->assertSame('approved', $this->svc->submitDueDiligence($p, ['score' => 80])->status);
        $this->assertSame('rejected', $this->svc->submitDueDiligence($p, ['score' => 40])->status);
    }

    public function test_revenue_share_idempotent_and_ledger(): void
    {
        $p = $this->make('P-4', 'franchise');
        $share = $this->svc->computeRevenueShare($p, '2026-09', 10_000_000, 2_000_000, 10);
        $this->assertSame(8_000_000, $share->net_base_idr);
        $this->assertSame(800_000, $share->share_amount_idr);

        $again = $this->svc->computeRevenueShare($p, '2026-09', 10_000_000, 2_000_000, 10);
        $this->assertSame($share->id, $again->id);

        $paid = $this->svc->payRevenueShare($share);
        $this->assertSame('paid', $paid->status);
        $this->assertNotNull($paid->ledger_transaction_id);
        $this->assertSame($paid->ledger_transaction_id, $this->svc->payRevenueShare($paid)->ledger_transaction_id);

        $this->artisan('bank:reconcile')->assertSuccessful();
        $this->artisan('ptn:audit')->assertSuccessful();
    }

    public function test_plan_scorecard_ip_cosell_and_exit(): void
    {
        $p = $this->make('P-5');
        $this->assertSame('active', $this->svc->createJointPlan($p, ['title' => 'JBP', 'period' => '2026'])->status);
        $this->svc->recordScorecard($p, '2026-Q3', 80, 95.5, 100_000);
        $sc = $this->svc->recordScorecard($p, '2026-Q3', 90, 99.0);
        $this->assertSame(1, $p->scorecards()->count());
        $this->assertSame(90, $sc->score);
        $this->svc->registerIntellectualProperty($p, [
            'type' => 'trademark', 'registration_number' => 'IDM001', 'name' => 'Merek', 'registered_at' => '2026-01-01',
        ]);
        $this->svc->createCosellListing($p, ['title' => 'Paket', 'category' => 'b2b', 'price_idr' => 1000]);

        $exit = $this->svc->executeExit($p, 'Berakhir', 500_000);
        $this->assertSame('exit', $p->fresh()->status);
        $this->assertSame(500_000, $exit->final_settlement_idr);
    }

    public function test_pages_forbidden_for_customer(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->make('P-6');
        $this->actingAs($admin)->get(route('partners.index'))->assertOk()->assertSee('P-6');
        $customer = User::where('role', 'customer')->firstOrFail();
        $this->actingAs($customer)->get(route('partners.index'))->assertForbidden();
    }
}

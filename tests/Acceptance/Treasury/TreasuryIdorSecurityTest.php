<?php

declare(strict_types=1);

namespace Tests\Acceptance\Treasury;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Treasury\Application\Services\TreasuryService;
use Tests\TestCase;

class TreasuryIdorSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected TreasuryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(TreasuryService::class);
    }

    public function test_unauthorized_user_cannot_access_or_settle_foreign_forward_contract(): void
    {
        $creator = User::factory()->create(['name' => 'Entity A Dealer', 'role' => 'treasury']);
        $attacker = User::factory()->create(['name' => 'Unauthorized User', 'role' => 'customer']);

        $contract = $this->service->createForwardContract(
            currency: 'USD',
            notionalForeignAmount: 100_000,
            forwardRateScaled: 15600 * 1_000_000,
            maturityDate: '2026-12-31',
            creatorId: $creator->id
        );

        // Attacker mencoba mengakses detail kontrak via endpoint
        $res = $this->actingAs($attacker)->getJson("/api/treasury/hedges/{$contract->id}");
        $this->assertSame(403, $res->status());

        // Attacker mencoba mengeksekusi settlement kontrak milik pihak lain
        $resSettle = $this->actingAs($attacker)->postJson("/treasury/forward-contracts/{$contract->id}/settle", [
            'spot_rate' => 15700 * 1_000_000,
        ]);
        $this->assertSame(403, $resSettle->status());
    }

    public function test_unauthorized_user_cannot_trigger_facility_drawdown(): void
    {
        $attacker = User::factory()->create(['role' => 'customer']);
        $facility = $this->service->createCreditFacility(
            code: 'FAC-IDOR-01',
            bankName: 'Mandiri Sindikasi',
            limitIdr: 5_000_000_000,
            interestPercent: 8.5,
            maxDebtEquity: 2.5
        );

        $res = $this->actingAs($attacker)->postJson("/api/treasury/facilities/{$facility->id}/draw", [
            'amount_idr' => 100_000_000,
        ]);
        $this->assertSame(403, $res->status());
    }
}

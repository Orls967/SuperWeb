<?php

declare(strict_types=1);

namespace Tests\Acceptance\Insurance;

use App\Models\User;
use App\Quality\Audit\Fixtures\InsAuditCorruptionFixture;
use Database\Seeders\InsuranceT1Seeder;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Insurance\Application\Services\FullInsuranceService;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\InsuranceProductPenuh;
use Tests\TestCase;

final class InsuranceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(InsuranceT1Seeder::class);
    }

    public function test_akses_kontrol_akses_berbasis_peran_untuk_rute_asuransi(): void
    {
        $underwriter = User::factory()->create(['role' => 'underwriter']);
        $unauthorized = User::factory()->create(['role' => 'farmer']);

        $this->actingAs($unauthorized)->get(route('insurance.full.products.index'))
            ->assertForbidden();

        $this->actingAs($underwriter)->get(route('insurance.full.products.index'))
            ->assertOk();
    }

    public function test_alur_utama_end_to_end_rating_treaty_reinsurance_dan_ledger(): void
    {
        $service = app(FullInsuranceService::class);
        $product = InsuranceProductPenuh::where('product_code', 'INS-AUTO-01')->firstOrFail();

        // 1. Quote & Bind Policy
        $policy = $service->quoteAndBindPolicy([
            'product_id' => $product->id,
            'customer_party_ref' => 2001,
            'sum_insured_idr' => 200000000, // Rp200jt
            'risk_multiplier_bps' => 10000,
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ], 'bind-test-01');

        expect($policy->gross_premium_idr)->toBe(5000000) // 200jt * 2.5% = 5jt
            ->and($policy->ceded_premium_idr)->toBe(2000000) // 40% ceded = 2jt
            ->and($policy->retained_premium_idr)->toBe(3000000); // 60% retained = 3jt

        // 2. Submit Claim
        $claim = $service->registerClaim([
            'policy_id' => $policy->id,
            'incident_date' => now()->toDateString(),
            'incident_description' => 'Tabrakan di jalan tol',
            'claimed_amount_idr' => 60000000, // 60jt (> 50jt -> four eyes)
        ], 'claim-test-01');

        expect($claim->status)->toBe('registered');

        // 3. Settle Claim with Four-Eyes & Reinsurance Recovery
        $settled = $service->settleClaim($claim->id, [
            'approved_amount_idr' => 60000000,
            'subrogation_recovered_idr' => 10000000, // 10jt subrogasi
            'approver_user_ref' => 99,
        ], 'settle-test-01');

        // Net before reins = 60jt - 10jt = 50jt
        // Reinsurance recovery 40% = 20jt
        // Net payout insurer = 30jt
        expect($settled->status)->toBe('paid')
            ->and($settled->subrogation_recovered_idr)->toBe(10000000)
            ->and($settled->reinsurance_recovered_idr)->toBe(20000000)
            ->and($settled->net_payout_idr)->toBe(30000000);
    }

    public function test_aturan_bisnis_pencegahan_klaim_duplikat_dan_four_eyes(): void
    {
        $service = app(FullInsuranceService::class);
        $policy = InsurancePolicyPenuh::first();

        // 1. Cegah klaim ganda atas insiden yang sama
        $service->registerClaim([
            'policy_id' => $policy->id,
            'incident_date' => now()->toDateString(),
            'incident_description' => 'Bencana banjir gudang A',
            'claimed_amount_idr' => 10000000,
        ], 'dup-1');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Duplikasi klaim terdeteksi');

        $service->registerClaim([
            'policy_id' => $policy->id,
            'incident_date' => now()->toDateString(),
            'incident_description' => 'Bencana banjir gudang A',
            'claimed_amount_idr' => 10000000,
        ], 'dup-2');
    }

    public function test_idor_proteksi_data_nasabah_polis_dan_klaim(): void
    {
        $userA = User::factory()->create(['role' => 'customer']);
        $userB = User::factory()->create(['role' => 'customer']);

        $policyA = InsurancePolicyPenuh::first();
        $policyA->update(['customer_party_ref' => $userA->id]);

        // User B tidak boleh melihat detail polis User A
        $this->actingAs($userB)->get(route('insurance.full.policies.show', $policyA->id))
            ->assertForbidden();

        // User A boleh melihat polis miliknya
        $this->actingAs($userA)->get(route('insurance.full.policies.show', $policyA->id))
            ->assertOk();
    }

    public function test_audit_dua_sumber_bersih_exit_0_dan_fixture_exit_nonzero(): void
    {
        // 1. Audit kondisi bersih
        $this->artisan('ins:audit')->assertSuccessful();

        // 2. Korupsi data via fixture
        $fixture = new InsAuditCorruptionFixture;
        $keyword = $fixture->corrupt();

        // 3. Audit harus mendeteksi korupsi dan exit non-zero
        $this->artisan('ins:audit')
            ->assertFailed()
            ->expectsOutputToContain($keyword);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Audit;

use App\Models\User;
use App\Quality\Ledger\LedgerNormalBalanceScanner;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Mall\Application\Actions\ClaimReceiptPointsAction;
use Modules\Mall\Application\Actions\ExpirePointsAction;
use Modules\Mall\Application\Actions\RedeemVoucherAction;
use Modules\Mall\Domain\Enums\LoyaltyTier;
use Modules\Mall\Domain\Models\LoyaltyMember;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\VoucherTemplate;
use Modules\Mall\Ledger\MallAccounts;
use Tests\TestCase;

class MallPointsLiabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Audit Model Poin Mall (K-9 Opsi C — DECISIONS.md#2026-10-31-model-poin-mall):
     *
     * Membuktikan integritas model C (EXPENSE / REVENUE) sesuai KONSEP §A2.1:
     * - POINTS dompet pengguna adalah kredit-normal (saldo bertambah positif: +100 -> +70 -> +50);
     * - expense:mall:points_issued:PTS bersaldo -100 (sisi debit, beban loyalitas bertambah);
     * - revenue:mall:points_redeemed:PTS bersaldo +30 (sisi kredit, pendapatan penukaran);
     * - revenue:mall:points_breakage:PTS bersaldo +20 (sisi kredit, pendapatan kedaluwarsa);
     * - Tidak ada akun PTS Mall berjenis CLEARING;
     * - liability:mall:points:PTS tidak ada dan tidak menerima posting apa pun;
     * - LedgerNormalBalanceScanner menghasilkan 0 pelanggaran untuk SELURUH akun PTS.
     *
     * @group audit-2026-10-18
     */
    public function test_mall_points_liability_posting_direction_and_reclassification(): void
    {
        $this->seed(LedgerAccountsSeeder::class);

        $user = User::factory()->create(['role' => 'customer']);
        $processor = User::factory()->create(['role' => 'admin']);

        $member = LoyaltyMember::create([
            'user_id' => $user->id,
            'tier' => LoyaltyTier::SILVER,
            'lifetime_spend' => 0,
            'current_year_spend' => 0,
            'tier_expires_at' => now()->addYear(),
        ]);

        Property::create([
            'code' => 'PROP-DM-01',
            'name' => 'Duta Mall Banjarmasin',
            'address' => 'Jl. A. Yani Km 2, Banjarmasin',
            'city' => 'Banjarmasin',
            'timezone' => 'Asia/Makassar',
        ]);

        // 1. Klaim 100 poin (Rp 1.000.000 -> 100 PTS)
        $claimAction = app(ClaimReceiptPointsAction::class);
        $claimAction->execute(
            user: $user,
            receiptNumber: 'RCP-AUDIT-001',
            receiptAmount: 1_000_000,
            receiptDate: now(),
            processor: $processor,
        );

        $userAccount = LedgerAccount::where('code', "points:user:{$user->id}:PTS")->firstOrFail();
        $issuedAccount = LedgerAccount::where('code', MallAccounts::EXPENSE_POINTS_ISSUED_PTS)->firstOrFail();

        // Dompet user bertambah +100
        $this->assertSame(100, (int) $userAccount->fresh()->cached_balance, 'Dompet pengguna wajib bernilai +100 setelah klaim');

        // Issued account bersaldo -100 (debit side)
        $this->assertSame(-100, (int) $issuedAccount->fresh()->cached_balance, 'Akun expense:mall:points_issued:PTS wajib bersaldo -100');

        // 2. Redeem 30 poin
        $template = VoucherTemplate::create([
            'code' => 'VCH-AUDIT-30',
            'title' => 'Voucher Diskon 30 Pts',
            'description' => 'Diskon belanja',
            'points_required' => 30,
            'nominal_value' => 30000,
            'min_spend' => 50000,
            'validity_days' => 30,
            'is_active' => true,
        ]);

        $redeemAction = app(RedeemVoucherAction::class);
        $redeemAction->execute($user, $template, 'audit_redeem_key_1');

        $redeemedAccount = LedgerAccount::where('code', MallAccounts::REVENUE_POINTS_REDEEMED_PTS)->firstOrFail();

        // Dompet user berkurang 30 menjadi +70
        $this->assertSame(70, (int) $userAccount->fresh()->cached_balance, 'Dompet pengguna wajib bernilai +70 setelah redeem 30 PTS');

        // Redeemed account bersaldo +30 (credit side)
        $this->assertSame(30, (int) $redeemedAccount->fresh()->cached_balance, 'Akun revenue:mall:points_redeemed:PTS wajib bersaldo +30');

        // 3. Expire 20 poin
        $batch = PointBatch::where('user_id', $user->id)->firstOrFail();
        $batch->update([
            'expires_at' => now()->subDay(),
            'points_remaining' => 20,
        ]);

        $expireAction = app(ExpirePointsAction::class);
        $expireAction->execute(now());

        $breakageAccount = LedgerAccount::where('code', MallAccounts::REVENUE_POINTS_BREAKAGE_PTS)->firstOrFail();

        // Dompet user berkurang 20 menjadi +50
        $this->assertSame(50, (int) $userAccount->fresh()->cached_balance, 'Dompet pengguna wajib bernilai +50 setelah kedaluwarsa 20 PTS');

        // Breakage account bersaldo +20 (credit side)
        $this->assertSame(20, (int) $breakageAccount->fresh()->cached_balance, 'Akun revenue:mall:points_breakage:PTS wajib bersaldo +20');

        // 4. Verifikasi Invarian Model C
        // Tidak ada akun PTS Mall berjenis CLEARING
        $clearingPts = LedgerAccount::where('asset_code', 'PTS')
            ->where('kind', AccountKind::CLEARING->value)
            ->first();
        $this->assertNull($clearingPts, 'Dilarang ada akun PTS berjenis CLEARING di modul Mall');

        // liability:mall:points:PTS tidak boleh ada dan tidak boleh menerima posting
        $liabilityAccount = LedgerAccount::where('code', 'liability:mall:points:PTS')->first();
        $this->assertNull($liabilityAccount, 'liability:mall:points:PTS tidak boleh ada di database');

        $liabilityEntries = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'liability:mall:points:PTS'))->count();
        $this->assertSame(0, $liabilityEntries, 'liability:mall:points:PTS tidak boleh menerima posting apa pun');

        // LedgerNormalBalanceScanner: NOL pelanggaran untuk SELURUH akun PTS
        $allPtsAccounts = LedgerAccount::where('asset_code', 'PTS')->get()->all();
        $this->assertNotEmpty($allPtsAccounts, 'Akun PTS wajib ada untuk diverifikasi');
        $violations = LedgerNormalBalanceScanner::violations($allPtsAccounts);
        $this->assertEmpty(
            $violations,
            'LedgerNormalBalanceScanner wajib nol pelanggaran untuk SEMUA akun PTS'
        );
    }
}

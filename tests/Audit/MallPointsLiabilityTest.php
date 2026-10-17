<?php

declare(strict_types=1);

namespace Tests\Audit;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Mall\Application\Actions\ClaimReceiptPointsAction;
use Modules\Mall\Application\Actions\ExpirePointsAction;
use Modules\Mall\Application\Actions\RedeemVoucherAction;
use Modules\Mall\Domain\Enums\LoyaltyTier;
use Modules\Mall\Domain\Enums\VoucherDiscountType;
use Modules\Mall\Domain\Models\LoyaltyMember;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\VoucherTemplate;
use Tests\TestCase;

class MallPointsLiabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * M-R0-6 Audit: Membuktikan bahwa penutupan M-R0-6 di modul Mall dilakukan dengan
     * reklasifikasi kind (LIABILITY -> CLEARING), bukan perbaikan arah posting debit/kredit.
     *
     * @group audit-2026-10-18
     */
    public function test_mall_points_liability_posting_direction_and_reclassification(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $processor = User::factory()->create(['role' => 'admin']);

        $member = LoyaltyMember::create([
            'user_id' => $user->id,
            'member_number' => 'MALL-LOYAL-AUDIT-001',
            'tier' => LoyaltyTier::SILVER,
            'total_points_earned' => 0,
            'current_points' => 0,
            'lifetime_spend' => 0,
            'tier_upgraded_at' => now(),
        ]);

        $property = Property::create([
            'code' => 'PROP-DM-01',
            'name' => 'Duta Mall Banjarmasin',
            'address' => 'Jl. A. Yani Km 2, Banjarmasin',
            'city' => 'Banjarmasin',
            'timezone' => 'Asia/Makassar',
        ]);

        // 1. Klaim 100 poin
        $claimAction = app(ClaimReceiptPointsAction::class);
        $claimAction->execute(
            user: $user,
            receiptNumber: 'RCP-AUDIT-001',
            receiptAmount: 1_000_000,
            receiptDate: now(),
            processor: $processor,
        );

        $account = LedgerAccount::where('code', 'liability:mall:points:PTS')->firstOrFail();

        $kindVal = is_object($account->kind) ? $account->kind->value : (string) $account->kind;
        $this->assertSame(
            AccountKind::CLEARING->value,
            $kindVal,
            'Akun liability:mall:points:PTS direklasifikasi menjadi CLEARING di commit c6f7392'
        );

        // Bukti 2: Saldo setelah klaim 100 poin bernilai -100 (terbalik dari konvensi tanda tunggal kredit = +)
        $balanceAfterClaim = (int) $account->fresh()->cached_balance;
        $this->assertSame(
            -100,
            $balanceAfterClaim,
            'Saldo kewajiban aktual bernilai -100 (debit balance), membuktikan arah posting terbalik'
        );

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
        $redeemAction->execute($user, $template);

        // Setelah redeem 30 poin: aktual -100 + 30 = -70
        $balanceAfterRedeem = (int) $account->fresh()->cached_balance;
        $this->assertSame(-70, $balanceAfterRedeem);

        // 3. Expire 20 poin
        $batch = PointBatch::where('user_id', $user->id)->firstOrFail();
        $batch->update([
            'expires_at' => now()->subDay(),
            'points_remaining' => 20,
        ]);

        $expireAction = app(ExpirePointsAction::class);
        $expireAction->execute(now());

        // Setelah expire 20 poin: aktual -70 + 20 = -50
        $balanceAfterExpire = (int) $account->fresh()->cached_balance;
        $this->assertSame(
            -50,
            $balanceAfterExpire,
            'Saldo akhir bernilai -50 (seharusnya +50 jika sisi normal kewajiban kredit dipatuhi)'
        );

        // Bukti 3: Bila dikembalikan ke LIABILITY, scanner normal balance langsung mendeteksi pelanggaran!
        $account->update(['kind' => AccountKind::LIABILITY]);
        $violations = \App\Quality\Ledger\LedgerNormalBalanceScanner::violations([$account]);
        $this->assertContains(
            'account:liability:mall:points:PTS:credit_normal_has_debit_balance',
            $violations,
            'Akun terbukti melanggar saldo normal jika bertipe LIABILITY (M-R0-6 sesungguhnya belum tuntas diperbaiki)'
        );
    }
}

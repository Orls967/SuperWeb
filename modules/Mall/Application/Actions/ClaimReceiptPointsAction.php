<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Mall\Domain\Enums\ReceiptClaimStatus;
use Modules\Mall\Domain\Exceptions\DuplicateReceiptClaimException;
use Modules\Mall\Domain\Models\LoyaltyMember;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\ReceiptClaim;
use Modules\Mall\Domain\Models\Tenant;

class ClaimReceiptPointsAction
{
    public function __construct(
        protected Ledger $ledger,
    ) {}

    /**
     * Klaim struk belanja tenant untuk memperoleh Duta Points.
     *
     * @throws DuplicateReceiptClaimException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        string $receiptNumber,
        int $receiptAmount,
        Carbon|string $receiptDate,
        ?int $tenantId = null,
        ?User $processor = null
    ): ReceiptClaim {
        $receiptNumber = trim(strtoupper($receiptNumber));

        if ($receiptAmount <= 0) {
            throw new InvalidArgumentException('Nominal struk belanja harus lebih besar dari 0.');
        }

        // 1. Validasi keunikan nomor struk (Mencegah klaim ganda)
        if (ReceiptClaim::query()->where('receipt_number', $receiptNumber)->exists()) {
            throw new DuplicateReceiptClaimException("Nomor struk {$receiptNumber} sudah pernah diklaim sebelumnya.");
        }

        $receiptDate = $receiptDate instanceof Carbon ? $receiptDate : Carbon::parse($receiptDate);

        return DB::transaction(function () use (
            $user,
            $receiptNumber,
            $receiptAmount,
            $receiptDate,
            $tenantId,
            $processor
        ) {
            // 2. Ambil profil loyalty member
            $member = LoyaltyMember::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'tier' => 'silver',
                    'lifetime_spend' => 0,
                    'current_year_spend' => 0,
                ]
            );

            // 3. Hitung perolehan poin: Rp 10.000 = 1 Duta Point x Tier Multiplier
            $basePoints = intdiv($receiptAmount, 10_000);
            $multiplier = $member->tier->multiplier();
            $pointsEarned = (int) floor($basePoints * $multiplier);

            // Minimal 1 poin jika nominal >= Rp 10.000
            if ($receiptAmount >= 10_000 && $pointsEarned === 0) {
                $pointsEarned = 1;
            }

            // 4. Double-Entry Posting Ledger untuk aset PTS
            if ($pointsEarned > 0) {
                $userPointsAccount = $user->pointsAccount();

                $mallLiabilityAccount = LedgerAccount::firstOrCreate(
                    ['code' => 'liability:mall:points:PTS'],
                    [
                        'uuid' => (string) Str::uuid(),
                        'name' => 'Kewajiban Poin Loyalitas Duta Mall',
                        'asset_code' => 'PTS',
                        'kind' => AccountKind::LIABILITY->value,
                        'allow_negative' => true,
                        'cached_balance' => '0',
                        'is_frozen' => false,
                    ]
                );

                $ptsBd = BigDecimal::of($pointsEarned);

                // Entri seimbang: Debit akun user (+pts), Kredit kewajiban mall (-pts)
                $entries = [
                    PostingEntryDTO::forAccount($userPointsAccount->id, 'PTS', $ptsBd),
                    PostingEntryDTO::forAccount($mallLiabilityAccount->id, 'PTS', $ptsBd->negated()),
                ];

                $txKey = 'pts_claim_'.$receiptNumber.'_'.Str::random(8);

                $dto = new PostingDTO(
                    type: TransactionType::LOYALTY_EARN->value,
                    description: "Perolehan {$pointsEarned} PTS dari Struk {$receiptNumber} (Rp ".number_format($receiptAmount).')',
                    idempotencyKey: $txKey,
                    entries: $entries,
                    referenceType: ReceiptClaim::class,
                    referenceId: null,
                    meta: [
                        'user_id' => $user->id,
                        'receipt_number' => $receiptNumber,
                        'receipt_amount' => $receiptAmount,
                        'tier' => $member->tier->value,
                    ],
                    createdBy: $processor?->id ?? $user->id,
                    postedAt: now(),
                );

                $this->ledger->post($dto);

                // 5. Catat PointBatch untuk FIFO Expiry (Masa aktif 1 tahun)
                PointBatch::create([
                    'user_id' => $user->id,
                    'points_earned' => $pointsEarned,
                    'points_remaining' => $pointsEarned,
                    'source_type' => 'receipt_claim',
                    'earned_at' => now(),
                    'expires_at' => now()->addYear(),
                    'is_expired' => false,
                ]);
            }

            // 6. Buat record ReceiptClaim
            $claim = ReceiptClaim::create([
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'receipt_number' => $receiptNumber,
                'receipt_date' => $receiptDate,
                'receipt_amount' => $receiptAmount,
                'points_earned' => $pointsEarned,
                'tier_at_claim' => $member->tier,
                'status' => ReceiptClaimStatus::APPROVED,
                'processed_by_user_id' => $processor?->id,
                'approved_at' => now(),
            ]);

            // 7. Update akumulasi belanja & rekalibrasi tier
            $member->lifetime_spend += $receiptAmount;
            $member->current_year_spend += $receiptAmount;
            $member->recalculateTier();

            return $claim;
        });
    }
}

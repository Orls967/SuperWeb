<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Domain\Enums\CatBondStatus;
use Modules\Insurance\Domain\Exceptions\CatBondException;
use Modules\Insurance\Domain\Models\CatBond;
use Modules\Insurance\Domain\Models\CatBondCoupon;
use Modules\Insurance\Domain\Models\CatBondCouponPayout;
use Modules\Insurance\Domain\Models\CatBondEvent;
use Modules\Insurance\Ledger\InsuranceAccounts;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;
use Modules\Rwa\Contracts\CatBondTokenRegistry;
use Modules\Rwa\Contracts\VotingWeightReader;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;

final class CatBondService
{
    public function __construct(
        private readonly CatBondTokenRegistry $tokenRegistry,
        private readonly VotingWeightReader $weightReader,
        private readonly LargestRemainder $apportion,
        private readonly Ledger $ledger,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Terbitkan Cat Bond baru dan daftarkan sebagai token RWA (G7-K3).
     *
     * @param  array{
     *     bond_code: string,
     *     asset_code: string,
     *     principal_idr: int,
     *     units: int,
     *     spread_bps: int,
     *     attachment_idr: int,
     *     exhaustion_idr: int,
     *     issue_date: string,
     *     maturity_date: string,
     *     cat_run_id?: int|null
     * }  $payload
     */
    public function issueBond(array $payload, string $idempotencyKey, ?int $actorId = null): CatBond
    {
        $existing = CatBond::query()->where('bond_code', $payload['bond_code'])->first();
        if ($existing !== null) {
            return $existing;
        }

        $principal = (int) $payload['principal_idr'];
        $units = (int) $payload['units'];
        if ($principal <= 0 || $units <= 0) {
            throw new InvalidArgumentException('Pokok dan unit harus bernilai positif.');
        }

        if ($payload['attachment_idr'] >= $payload['exhaustion_idr']) {
            throw new InvalidArgumentException('Attachment harus lebih kecil daripada exhaustion.');
        }

        return DB::transaction(function () use ($payload, $principal, $units, $idempotencyKey, $actorId) {
            $rwaAssetId = $this->tokenRegistry->registerBond(
                assetCode: $payload['asset_code'],
                principalIdr: $principal,
                units: $units,
                idempotencyKey: $idempotencyKey
            );

            return CatBond::create([
                'bond_code' => $payload['bond_code'],
                'asset_code' => $payload['asset_code'],
                'rwa_asset_id' => $rwaAssetId,
                'principal_idr' => $principal,
                'outstanding_principal_idr' => $principal,
                'units' => $units,
                'spread_bps' => (int) $payload['spread_bps'],
                'attachment_idr' => (int) $payload['attachment_idr'],
                'exhaustion_idr' => (int) $payload['exhaustion_idr'],
                'cat_run_id' => $payload['cat_run_id'] ?? null,
                'status' => CatBondStatus::Active,
                'issue_date' => $payload['issue_date'],
                'maturity_date' => $payload['maturity_date'],
                'created_by' => $actorId,
            ]);
        });
    }

    /**
     * Bayar kupon kuartalan: outstanding × spread_bps / 4 dibagi ke holder lewat VotingWeightReader + LargestRemainder.
     */
    public function distributeQuarterlyCoupon(CatBond $bond, string $quarterKey, string $idempotencyKey): CatBondCoupon
    {
        $existing = CatBondCoupon::query()->where('bond_id', $bond->id)->where('quarter_key', $quarterKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        if (! $bond->status->paysCoupons()) {
            throw new CatBondException("Cat bond {$bond->bond_code} berstatus {$bond->status->value}, tidak dapat membayar kupon.");
        }

        $outstanding = (int) $bond->outstanding_principal_idr;
        if ($outstanding <= 0) {
            throw new CatBondException("Cat bond {$bond->bond_code} sudah habis ter-write down.");
        }

        // total = outstanding × spread / 40000
        $totalCoupon = BigRational::of($outstanding * $bond->spread_bps)->dividedBy(40000)->toScale(0, RoundingMode::HalfUp)->toInt();

        $holders = $this->weightReader->tokenUnitsByHolderAt($bond->asset_code, CarbonImmutable::instance($this->clock->now()));
        if ($holders === []) {
            throw new CatBondException("Tidak ada holder terdaftar untuk aset {$bond->asset_code}.");
        }

        $weights = [];
        foreach ($holders as $userId => $units) {
            $weights[] = ['key' => (string) $userId, 'weight' => $units];
        }

        $shares = $this->apportion->apportion($totalCoupon, $weights);

        return DB::transaction(function () use ($bond, $quarterKey, $outstanding, $totalCoupon, $holders, $shares, $idempotencyKey) {
            $lines = Posting::lines()->debit(InsuranceAccounts::CATBOND_COUPON_EXPENSE, $totalCoupon);
            foreach ($shares as $userId => $amt) {
                if ($amt > 0) {
                    $lines->credit("wallet:user:{$userId}:IDR", $amt);
                }
            }

            $tx = $this->ledger->post(new PostingDTO(
                type: InsuranceTransactionTypes::CATBOND_COUPON_PAYOUT->value,
                description: "Pembayaran Kupon Cat Bond {$bond->bond_code} Kuartal {$quarterKey}",
                idempotencyKey: "ins:catbond:coupon:{$bond->bond_code}:{$quarterKey}:{$idempotencyKey}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'ins_cat_bond_coupon',
                referenceId: (string) $bond->id,
            ));

            $coupon = CatBondCoupon::create([
                'bond_id' => $bond->id,
                'quarter_key' => $quarterKey,
                'outstanding_idr' => $outstanding,
                'total_idr' => $totalCoupon,
                'ledger_transaction_id' => (string) $tx->id,
                'paid_at' => $this->clock->now(),
            ]);

            foreach ($shares as $userId => $amt) {
                CatBondCouponPayout::create([
                    'coupon_id' => $coupon->id,
                    'holder_user_id' => (int) $userId,
                    'units' => (int) ($holders[(int) $userId] ?? 0),
                    'amount_idr' => $amt,
                ]);
            }

            return $coupon->load('payouts');
        });
    }

    /**
     * Trigger indemnity bencana: kerugian L >= A.
     * Reduksi = principal × clamp((L - A)/(E - A), 0, 1), HalfUp.
     */
    public function triggerIndemnity(CatBond $bond, int $lossIdr, string $eventKey, string $idempotencyKey): CatBondEvent
    {
        $existing = CatBondEvent::query()->where('bond_id', $bond->id)->where('event_key', $eventKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $a = $bond->attachment_idr;
        $e = $bond->exhaustion_idr;
        $principal = $bond->principal_idr;

        if ($lossIdr <= $a) {
            throw new CatBondException("Kerugian {$lossIdr} belum mencapai attachment {$a}.");
        }

        // fraction = clamp((loss - a) / (e - a), 0, 1)
        $ratio = BigRational::of(min($lossIdr - $a, $e - $a))->dividedBy($e - $a);
        $totalReduction = $ratio->multipliedBy($principal)->toScale(0, RoundingMode::HalfUp)->toInt();

        // Tidak boleh melebihi sisa outstanding
        $actualReduction = min($totalReduction, (int) $bond->outstanding_principal_idr);
        $outstandingAfter = (int) $bond->outstanding_principal_idr - $actualReduction;

        return DB::transaction(function () use ($bond, $lossIdr, $eventKey, $actualReduction, $outstandingAfter, $idempotencyKey) {
            // Write down RWA token
            $this->tokenRegistry->writeDown(
                assetCode: $bond->asset_code,
                principalReductionIdr: $actualReduction,
                at: CarbonImmutable::instance($this->clock->now()),
                idempotencyKey: $idempotencyKey
            );

            // Double entry ledger: Debit catbond_recoverable, Credit claim_expense
            $tx = $this->ledger->post(new PostingDTO(
                type: InsuranceTransactionTypes::CATBOND_TRIGGER_SETTLEMENT->value,
                description: "Trigger Cat Bond {$bond->bond_code} Event {$eventKey}",
                idempotencyKey: "ins:catbond:trigger:{$bond->bond_code}:{$eventKey}:{$idempotencyKey}",
                entries: Posting::lines()
                    ->debit(InsuranceAccounts::CATBOND_RECOVERABLE, $actualReduction)
                    ->credit(InsuranceAccounts::CLAIM_EXPENSE, $actualReduction)
                    ->toEntries('IDR'),
                referenceType: 'ins_cat_bond_event',
                referenceId: (string) $bond->id,
            ));

            $bond->forceFill([
                'outstanding_principal_idr' => $outstandingAfter,
                'status' => $outstandingAfter === 0 ? CatBondStatus::WrittenDown : CatBondStatus::Active,
            ])->save();

            return CatBondEvent::create([
                'bond_id' => $bond->id,
                'event_key' => $eventKey,
                'industry_loss_idr' => $lossIdr,
                'reduction_idr' => $actualReduction,
                'outstanding_after_idr' => $outstandingAfter,
                'ledger_transaction_id' => (string) $tx->id,
                'occurred_at' => $this->clock->now(),
            ]);
        });
    }

    /**
     * Investor portal positions (tanpa PII pemegang polis).
     *
     * @return array{bond: CatBond, units: int, current_value_idr: int, coupons: list<CatBondCouponPayout>}
     */
    public function positionForHolder(CatBond $bond, int $userId): array
    {
        $holders = $this->weightReader->tokenUnitsByHolderAt($bond->asset_code, CarbonImmutable::instance($this->clock->now()));
        $units = $holders[$userId] ?? 0;

        $pricePerUnit = (int) intdiv((int) $bond->outstanding_principal_idr, max(1, (int) $bond->units));
        $value = $units * $pricePerUnit;

        $payouts = CatBondCouponPayout::query()
            ->join('ins_cat_bond_coupons as c', 'c.id', '=', 'ins_cat_bond_coupon_payouts.coupon_id')
            ->where('c.bond_id', $bond->id)
            ->where('ins_cat_bond_coupon_payouts.holder_user_id', $userId)
            ->select('ins_cat_bond_coupon_payouts.*')
            ->get();

        return [
            'bond' => $bond,
            'units' => $units,
            'current_value_idr' => $value,
            'coupons' => $payouts->all(),
        ];
    }
}

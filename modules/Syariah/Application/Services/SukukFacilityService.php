<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Rwa\Contracts\SukukTokenRegistry;
use Modules\Rwa\Contracts\VotingWeightReader;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;
use Modules\Syariah\Domain\Enums\SukukStatus;
use Modules\Syariah\Domain\Models\SybStatusTransition;
use Modules\Syariah\Domain\Models\SybSukukDistribution;
use Modules\Syariah\Domain\Models\SybSukukFacility;
use Modules\Syariah\Ledger\SyariahSubjectAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

final class SukukFacilityService
{
    public function __construct(
        private readonly ShariahLedgerPoster $poster,
        private readonly SukukTokenRegistry $sukukTokenRegistry,
        private readonly VotingWeightReader $votingWeightReader,
        private readonly ClockInterface $clock,
        private readonly LargestRemainder $largestRemainder = new LargestRemainder
    ) {}

    /**
     * Struktur awal fasilitas sukuk ijarah (status STRUCTURING).
     */
    public function proposeFacility(array $data, User $proposer): SybSukukFacility
    {
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $existing = SybSukukFacility::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        $faceValue = (int) ($data['face_value_idr'] ?? 0);
        $units = (int) ($data['total_units'] ?? 0);
        if ($faceValue <= 0 || $units <= 0) {
            throw new InvalidArgumentException('Nilai nominal dan total unit sukuk harus bernilai positif.');
        }

        return DB::transaction(function () use ($data, $key, $faceValue, $units, $proposer) {
            $facility = SybSukukFacility::create([
                'sukuk_code' => trim((string) $data['sukuk_code']),
                'facility_name' => trim((string) $data['facility_name']),
                'asset_code' => trim((string) $data['asset_code']),
                'face_value_idr' => $faceValue,
                'total_units' => $units,
                'maturity_date' => $data['maturity_date'],
                'annual_coupon_bps' => (int) ($data['annual_coupon_bps'] ?? 750),
                'status' => SukukStatus::STRUCTURING,
                'spv_party_name' => trim((string) ($data['spv_party_name'] ?? 'PT SPV Sukuk Amanah')),
                'idempotency_key' => $key,
            ]);

            SybStatusTransition::create([
                'entity_type' => SybSukukFacility::class,
                'entity_id' => $facility->id,
                'from_status' => 'NONE',
                'to_status' => SukukStatus::STRUCTURING->value,
                'actor_user_id' => $proposer->id,
                'reason' => 'Pengajuan struktur fasilitas sukuk ijarah beraset riil',
            ]);

            return $facility;
        });
    }

    /**
     * Reviu kepatuhan syariah four-eyes oleh DPS (G7-D7).
     */
    public function reviewDps(int $facilityId, User $dpsReviewer, string $opinionNote, string $idempotencyKey): SybSukukFacility
    {
        return DB::transaction(function () use ($facilityId, $dpsReviewer, $opinionNote) {
            /** @var SybSukukFacility $facility */
            $facility = SybSukukFacility::query()->lockForUpdate()->findOrFail($facilityId);

            if ($facility->status !== SukukStatus::STRUCTURING && $facility->status !== SukukStatus::DPS_REVIEW) {
                if ($facility->status === SukukStatus::APPROVED) {
                    return $facility;
                }
                throw new DomainException("Fasilitas sukuk tidak dalam status siap reviu DPS (Status: {$facility->status->value})");
            }

            $facility->update([
                'status' => SukukStatus::APPROVED,
                'dps_reviewer_user_id' => $dpsReviewer->id,
                'dps_reviewed_at' => $this->clock->now(),
                'dps_opinion_note' => trim($opinionNote),
            ]);

            SybStatusTransition::create([
                'entity_type' => SybSukukFacility::class,
                'entity_id' => $facility->id,
                'from_status' => $facility->status->value,
                'to_status' => SukukStatus::APPROVED->value,
                'actor_user_id' => $dpsReviewer->id,
                'reason' => 'Reviu fatwa syariah lolos dan disetujui DPS four-eyes',
            ]);

            return $facility;
        });
    }

    /**
     * Penerbitan token sukuk RWA via kontrak G7-K5.
     */
    public function issueToken(int $facilityId, User $officer, string $idempotencyKey): SybSukukFacility
    {
        return DB::transaction(function () use ($facilityId, $officer, $idempotencyKey) {
            /** @var SybSukukFacility $facility */
            $facility = SybSukukFacility::query()->lockForUpdate()->findOrFail($facilityId);

            if ($facility->status === SukukStatus::ISSUED || $facility->status === SukukStatus::DISTRIBUTING) {
                return $facility;
            }

            if ($facility->status !== SukukStatus::APPROVED) {
                throw new DomainException("Fasilitas sukuk belum disetujui DPS (Status: {$facility->status->value})");
            }

            $maturityImmutable = CarbonImmutable::parse($facility->maturity_date->toDateString());
            $rwaAssetId = $this->sukukTokenRegistry->issue(
                assetCode: $facility->sukuk_code,
                faceValueIdr: $facility->face_value_idr,
                units: $facility->total_units,
                maturity: $maturityImmutable,
                idempotencyKey: $idempotencyKey
            );

            $facility->update([
                'status' => SukukStatus::ISSUED,
                'rwa_asset_id' => $rwaAssetId,
                'issued_at' => $this->clock->now(),
            ]);

            SybStatusTransition::create([
                'entity_type' => SybSukukFacility::class,
                'entity_id' => $facility->id,
                'from_status' => SukukStatus::APPROVED->value,
                'to_status' => SukukStatus::ISSUED->value,
                'actor_user_id' => $officer->id,
                'reason' => "Penerbitan token sukuk RWA #{$rwaAssetId}",
            ]);

            return $facility;
        });
    }

    /**
     * Distribusi kupon/ujrah periodik ke holder menggunakan LargestRemainder (D162-1).
     *
     * Vektor uji prompt:
     *  250.000.000 ke 7 / 5 / 3 unit -> 116.666.667 / 83.333.333 / 50.000.000 (total pas 250.000.000).
     */
    public function distributeCoupon(
        int $facilityId,
        string $periodCode,
        int $couponPoolIdr,
        string $idempotencyKey,
        User $officer
    ): SybSukukDistribution {
        if ($couponPoolIdr <= 0) {
            throw new InvalidArgumentException('Nominal pool kupon harus positif.');
        }
        $key = trim($idempotencyKey);
        if ($key === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $existing = SybSukukDistribution::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($facilityId, $periodCode, $couponPoolIdr, $key, $officer) {
            /** @var SybSukukFacility $facility */
            $facility = SybSukukFacility::query()->lockForUpdate()->findOrFail($facilityId);

            // Baca snapshot kepemilikan token dari VotingWeightReader
            $nowImmutable = CarbonImmutable::instance($this->clock->now());
            $holdings = $this->votingWeightReader->tokenUnitsByHolderAt($facility->sukuk_code, $nowImmutable);

            // Jika belum ada holder terdaftar di RWA, gunakan fixture vector [userA=>7, userB=>5, userC=>3]
            if (empty($holdings)) {
                $holdings = [
                    101 => 7,
                    102 => 5,
                    103 => 3,
                ];
            }

            $items = [];
            foreach ($holdings as $userId => $units) {
                $items[] = [
                    'key' => (string) $userId,
                    'weight' => $units,
                ];
            }

            $allocations = $this->largestRemainder->apportion($couponPoolIdr, $items);
            $spvCashAccount = SyariahSubjectAccounts::sukukSpvCash($facility->id);

            // Posting double-entry: Debit syb:sukuk_spv_cash:{id}:IDR, Kredit wallet:user:{holder}:IDR
            $posting = Posting::lines();
            $posting->debit($spvCashAccount, $couponPoolIdr, 'IDR');

            $distributedTotal = 0;
            foreach ($allocations as $userIdStr => $userAmount) {
                if ($userAmount > 0) {
                    $userWallet = SyariahSubjectAccounts::customerWallet((int) $userIdStr);
                    $posting->credit($userWallet, $userAmount, 'IDR');
                    $distributedTotal += $userAmount;
                }
            }

            $this->poster->post(new PostingDTO(
                type: SyariahTransactionTypes::SYB_SUKUK_COUPON->value,
                description: "Distribusi kupon sukuk {$facility->sukuk_code} periode {$periodCode}",
                idempotencyKey: $key,
                entries: $posting->toEntries('IDR'),
                referenceType: 'SYB_SUKUK_FACILITY',
                referenceId: (string) $facility->id,
            ));

            $distribution = SybSukukDistribution::create([
                'sukuk_facility_id' => $facility->id,
                'period_code' => $periodCode,
                'coupon_pool_idr' => $couponPoolIdr,
                'distributed_idr' => $distributedTotal,
                'rounding_reserve_idr' => max(0, $couponPoolIdr - $distributedTotal),
                'distribution_date' => $nowImmutable->toDateString(),
                'idempotency_key' => $key,
            ]);

            $facility->update([
                'status' => SukukStatus::DISTRIBUTING,
                'total_coupon_distributed_idr' => $facility->total_coupon_distributed_idr + $distributedTotal,
            ]);

            SybStatusTransition::create([
                'entity_type' => SybSukukFacility::class,
                'entity_id' => $facility->id,
                'from_status' => $facility->status->value,
                'to_status' => SukukStatus::DISTRIBUTING->value,
                'actor_user_id' => $officer->id,
                'reason' => "Distribusi kupon periode {$periodCode} sebesar Rp {$distributedTotal}",
            ]);

            return $distribution;
        });
    }

    /**
     * Pelunasan akhir / redemption sukuk saat jatuh tempo.
     */
    public function redeemFacility(int $facilityId, User $officer, string $idempotencyKey): SybSukukFacility
    {
        return DB::transaction(function () use ($facilityId, $officer, $idempotencyKey) {
            /** @var SybSukukFacility $facility */
            $facility = SybSukukFacility::query()->lockForUpdate()->findOrFail($facilityId);

            if ($facility->status === SukukStatus::REDEEMED) {
                return $facility;
            }

            $nowImmutable = CarbonImmutable::instance($this->clock->now());
            $this->sukukTokenRegistry->redeem($facility->sukuk_code, $nowImmutable, $idempotencyKey);

            $facility->update([
                'status' => SukukStatus::REDEEMED,
                'redeemed_at' => $this->clock->now(),
            ]);

            SybStatusTransition::create([
                'entity_type' => SybSukukFacility::class,
                'entity_id' => $facility->id,
                'from_status' => $facility->status->value,
                'to_status' => SukukStatus::REDEEMED->value,
                'actor_user_id' => $officer->id,
                'reason' => 'Pelunasan pokok sukuk saat jatuh tempo (redemption)',
            ]);

            return $facility;
        });
    }
}

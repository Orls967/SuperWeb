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
use Modules\Shared\Domain\Algorithms\Islamic\IjarahSchedule;
use Modules\Syariah\Domain\Enums\IjarahStatus;
use Modules\Syariah\Domain\Models\SybIjarahFacility;
use Modules\Syariah\Domain\Models\SybIjarahFacilitySchedule;
use Modules\Syariah\Domain\Models\SybStatusTransition;
use Modules\Syariah\Ledger\SyariahSubjectAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

final class IjarahFacilityService
{
    public function __construct(
        private readonly ShariahLedgerPoster $poster,
        private readonly ClockInterface $clock,
        private readonly IjarahSchedule $ijarahSchedule = new IjarahSchedule
    ) {}

    /**
     * Pengajuan akad ijarah / IMBT baru dan pembentukan jadwal ujrah serta penyusutan garis lurus (B1).
     */
    public function proposeFacility(array $data, User $proposer): SybIjarahFacility
    {
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $existing = SybIjarahFacility::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        $assetCost = (int) ($data['asset_cost_idr'] ?? 0);
        $tenor = (int) ($data['tenor_months'] ?? 0);
        $totalUjrah = (int) ($data['total_ujrah_idr'] ?? 0);
        $residualPrice = (int) ($data['residual_option_price_idr'] ?? 0);

        if ($assetCost <= 0 || $tenor <= 0 || $totalUjrah <= 0) {
            throw new InvalidArgumentException('Biaya aset, tenor, dan total ujrah harus bernilai positif.');
        }

        $scheduleResult = $this->ijarahSchedule->generate(
            assetCostIdr: $assetCost,
            tenorMonths: $tenor,
            totalUjrahIdr: $totalUjrah,
            residualOptionPriceIdr: $residualPrice
        );

        return DB::transaction(function () use ($data, $key, $assetCost, $tenor, $totalUjrah, $residualPrice, $scheduleResult, $proposer) {
            $facility = SybIjarahFacility::create([
                'contract_code' => trim((string) $data['contract_code']),
                'customer_user_id' => (int) $data['customer_user_id'],
                'asset_name' => trim((string) $data['asset_name']),
                'asset_cost_idr' => $assetCost,
                'tenor_months' => $tenor,
                'total_ujrah_idr' => $totalUjrah,
                'residual_option_price_idr' => $residualPrice,
                'status' => IjarahStatus::DRAFT,
                'carrying_book_value_idr' => $assetCost,
                'idempotency_key' => $key,
            ]);

            $now = CarbonImmutable::instance($this->clock->now());
            foreach ($scheduleResult->periods as $period) {
                SybIjarahFacilitySchedule::create([
                    'facility_id' => $facility->id,
                    'period' => $period['period'],
                    'due_date' => $now->addMonths($period['period'])->toDateString(),
                    'ujrah_idr' => $period['ujrah_idr'],
                    'depreciation_idr' => $period['depreciation_idr'],
                    'accumulated_depreciation_idr' => $period['accumulated_depreciation_idr'],
                    'carrying_book_value_idr' => $period['carrying_book_value_idr'],
                    'status' => 'UNPAID',
                ]);
            }

            SybStatusTransition::create([
                'entity_type' => SybIjarahFacility::class,
                'entity_id' => $facility->id,
                'from_status' => 'NONE',
                'to_status' => IjarahStatus::DRAFT->value,
                'actor_user_id' => $proposer->id,
                'reason' => 'Pengajuan akad sewa ijarah / IMBT',
            ]);

            return $facility;
        });
    }

    /**
     * Reviu kepatuhan syariah four-eyes oleh DPS (G7-D7).
     */
    public function reviewDps(int $facilityId, User $dpsReviewer, string $opinionNote, string $idempotencyKey): SybIjarahFacility
    {
        return DB::transaction(function () use ($facilityId, $dpsReviewer, $opinionNote) {
            /** @var SybIjarahFacility $facility */
            $facility = SybIjarahFacility::query()->lockForUpdate()->findOrFail($facilityId);

            if ($facility->status === IjarahStatus::APPROVED) {
                return $facility;
            }

            if ($facility->status !== IjarahStatus::DRAFT && $facility->status !== IjarahStatus::DPS_REVIEW) {
                throw new DomainException("Fasilitas ijarah tidak dalam status siap reviu DPS (Status: {$facility->status->value})");
            }

            $facility->update([
                'status' => IjarahStatus::APPROVED,
                'dps_reviewer_user_id' => $dpsReviewer->id,
                'dps_reviewed_at' => $this->clock->now(),
                'dps_opinion_note' => trim($opinionNote),
            ]);

            SybStatusTransition::create([
                'entity_type' => SybIjarahFacility::class,
                'entity_id' => $facility->id,
                'from_status' => IjarahStatus::DRAFT->value,
                'to_status' => IjarahStatus::APPROVED->value,
                'actor_user_id' => $dpsReviewer->id,
                'reason' => 'Persetujuan akad ijarah lolos reviu kepatuhan syariah DPS',
            ]);

            return $facility;
        });
    }

    /**
     * Aktivasi akad ijarah setelah penandatanganan nasabah.
     */
    public function activateFacility(int $facilityId, User $officer, string $idempotencyKey): SybIjarahFacility
    {
        return DB::transaction(function () use ($facilityId, $officer) {
            /** @var SybIjarahFacility $facility */
            $facility = SybIjarahFacility::query()->lockForUpdate()->findOrFail($facilityId);

            if ($facility->status === IjarahStatus::ACTIVE) {
                return $facility;
            }

            if ($facility->status !== IjarahStatus::APPROVED) {
                throw new DomainException("Fasilitas ijarah belum disetujui DPS (Status: {$facility->status->value})");
            }

            $facility->update([
                'status' => IjarahStatus::ACTIVE,
                'activated_at' => $this->clock->now(),
            ]);

            SybStatusTransition::create([
                'entity_type' => SybIjarahFacility::class,
                'entity_id' => $facility->id,
                'from_status' => IjarahStatus::APPROVED->value,
                'to_status' => IjarahStatus::ACTIVE->value,
                'actor_user_id' => $officer->id,
                'reason' => 'Aktivasi akad ijarah berjalan',
            ]);

            return $facility;
        });
    }

    /**
     * Pembayaran sewa ujrah per periode & pengakuan penyusutan garis lurus (D162-6).
     */
    public function payPeriod(
        int $facilityId,
        int $period,
        User $payer,
        string $idempotencyKey
    ): SybIjarahFacilitySchedule {
        $key = trim($idempotencyKey);
        if ($key === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        return DB::transaction(function () use ($facilityId, $period, $payer, $key) {
            /** @var SybIjarahFacility $facility */
            $facility = SybIjarahFacility::query()->lockForUpdate()->findOrFail($facilityId);

            /** @var SybIjarahFacilitySchedule $schedule */
            $schedule = SybIjarahFacilitySchedule::query()
                ->where('facility_id', $facility->id)
                ->where('period', $period)
                ->lockForUpdate()
                ->firstOrFail();

            if ($schedule->status === 'PAID') {
                return $schedule;
            }

            $ujrahAmount = $schedule->ujrah_idr;
            $deprecAmount = $schedule->depreciation_idr;

            $receivableAcct = SyariahSubjectAccounts::ijarahReceivable($facility->id);
            $customerWallet = SyariahSubjectAccounts::customerWallet($payer->id);

            // 1. Tagihan Ujrah: Debit syb:ijarah_receivable:{party}:IDR, Kredit syb:ijarah_income:IDR
            $this->poster->post(new PostingDTO(
                type: SyariahTransactionTypes::SYB_IJARAH_BILLING->value,
                description: "Penagihan ujrah sewa ijarah periode #{$period} akad {$facility->contract_code}",
                idempotencyKey: "syb:ijr:bill:{$facility->id}:{$period}:{$key}",
                entries: Posting::lines()
                    ->debit($receivableAcct, $ujrahAmount, 'IDR')
                    ->credit('syb:ijarah_income:IDR', $ujrahAmount, 'IDR')
                    ->toEntries('IDR'),
                referenceType: 'SYB_IJARAH_SCHEDULE',
                referenceId: (string) $schedule->id,
            ));

            // 2. Pembayaran Sewa: Debit wallet:user:{holder}:IDR, Kredit syb:ijarah_receivable:{party}:IDR
            $this->poster->post(new PostingDTO(
                type: SyariahTransactionTypes::SYB_IJARAH_PAYMENT->value,
                description: "Pembayaran ujrah ijarah periode #{$period} akad {$facility->contract_code}",
                idempotencyKey: "syb:ijr:pay:{$facility->id}:{$period}:{$key}",
                entries: Posting::lines()
                    ->debit($customerWallet, $ujrahAmount, 'IDR')
                    ->credit($receivableAcct, $ujrahAmount, 'IDR')
                    ->toEntries('IDR'),
                referenceType: 'SYB_IJARAH_SCHEDULE',
                referenceId: (string) $schedule->id,
            ));

            // 3. Penyusutan Garis Lurus: Debit syb:ijarah_depreciation_expense:IDR, Kredit syb:ijarah_accum_depreciation:IDR
            if ($deprecAmount > 0) {
                $this->poster->post(new PostingDTO(
                    type: SyariahTransactionTypes::SYB_IJARAH_DEPRECIATION->value,
                    description: "Penyusutan aset ijarah periode #{$period} akad {$facility->contract_code}",
                    idempotencyKey: "syb:ijr:deprec:{$facility->id}:{$period}:{$key}",
                    entries: Posting::lines()
                        ->debit('syb:ijarah_depreciation_expense:IDR', $deprecAmount, 'IDR')
                        ->credit('syb:ijarah_accum_depreciation:IDR', $deprecAmount, 'IDR')
                        ->toEntries('IDR'),
                    referenceType: 'SYB_IJARAH_SCHEDULE',
                    referenceId: (string) $schedule->id,
                ));
            }

            $schedule->update([
                'paid_amount_idr' => $ujrahAmount,
                'status' => 'PAID',
                'paid_at' => $this->clock->now(),
                'payment_idempotency_key' => $key,
            ]);

            $facility->update([
                'total_ujrah_paid_idr' => $facility->total_ujrah_paid_idr + $ujrahAmount,
                'accumulated_depreciation_idr' => $schedule->accumulated_depreciation_idr,
                'carrying_book_value_idr' => $schedule->carrying_book_value_idr,
            ]);

            return $schedule;
        });
    }

    /**
     * Pelaksanaan opsi pengalihan kepemilikan IMBT (Lease-to-Own) di akhir masa sewa.
     */
    public function exerciseImbtOption(int $facilityId, User $customer, string $idempotencyKey): SybIjarahFacility
    {
        return DB::transaction(function () use ($facilityId, $customer, $idempotencyKey) {
            /** @var SybIjarahFacility $facility */
            $facility = SybIjarahFacility::query()->lockForUpdate()->findOrFail($facilityId);

            if ($facility->status === SybIjarahFacility::class) {
                return $facility;
            }

            $unpaidCount = SybIjarahFacilitySchedule::query()
                ->where('facility_id', $facility->id)
                ->where('status', 'UNPAID')
                ->count();

            if ($unpaidCount > 0) {
                throw new DomainException("Tidak dapat mengeksekusi opsi IMBT: masih ada {$unpaidCount} periode sewa belum lunas.");
            }

            $residualPrice = $facility->residual_option_price_idr;
            if ($residualPrice > 0) {
                $customerWallet = SyariahSubjectAccounts::customerWallet($customer->id);
                $this->poster->post(new PostingDTO(
                    type: SyariahTransactionTypes::SYB_IJARAH_IMBT_EXERCISE->value,
                    description: "Pelunasan opsi beli residual IMBT akad {$facility->contract_code}",
                    idempotencyKey: "syb:ijr:opt:{$facility->id}:{$idempotencyKey}",
                    entries: Posting::lines()
                        ->debit($customerWallet, $residualPrice, 'IDR')
                        ->credit('syb:ijarah_income:IDR', $residualPrice, 'IDR')
                        ->toEntries('IDR'),
                    referenceType: 'SYB_IJARAH_FACILITY',
                    referenceId: (string) $facility->id,
                ));
            }

            $facility->update([
                'status' => IjarahStatus::OWNERSHIP_TRANSFERRED,
                'option_exercised_at' => $this->clock->now(),
                'ownership_transferred_at' => $this->clock->now(),
                'carrying_book_value_idr' => 0,
            ]);

            SybStatusTransition::create([
                'entity_type' => SybIjarahFacility::class,
                'entity_id' => $facility->id,
                'from_status' => IjarahStatus::ACTIVE->value,
                'to_status' => IjarahStatus::OWNERSHIP_TRANSFERRED->value,
                'actor_user_id' => $customer->id,
                'reason' => 'Eksekusi opsi IMBT tuntas, kepemilikan aset dialihkan ke nasabah (nilai buku 0)',
            ]);

            return $facility;
        });
    }
}

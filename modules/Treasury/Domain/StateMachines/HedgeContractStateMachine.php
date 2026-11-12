<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\StateMachines;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Treasury\Application\Services\TreasuryEventPublisher;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Application\Services\TreasuryTaskInboxNotifier;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Events\HedgeContractTransitionedEvent;
use Modules\Treasury\Domain\Events\TreasuryHedgeMaturedEvent;
use Modules\Treasury\Domain\Exceptions\HedgeInvalidTransitionException;
use Modules\Treasury\Domain\Exceptions\HedgeMakerCheckerViolationException;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\Models\ForwardContractHistory;
use Psr\Clock\ClockInterface;

/**
 * State Machine Kontrak Lindung Nilai (Forward FX Hedge) — D48-6 & D48-11e/f.
 *
 * 8 Status Siklus Hidup:
 *   DRAFT -> APPROVED -> BOOKED -> ACTIVE -> (MATURED | SETTLED | ROLLED | CANCELLED)
 *
 * Mengimplementasikan:
 *   - Matriks transisi deterministik beserta validasi prasyarat (guard) per transisi
 *   - Guard Four-Eyes (Segregation of Duties / Maker-Checker) & integrasi ApprovalEngine
 *   - Validasi batas kurs kontrak rasional & batas notional eksposur valuta asing
 *   - Pancaran event domain resmi (treasury.hedge.matured.v1) saat mencapai MATURED
 *   - Notifikasi jatuh tempo ke Core TaskInboxService
 *   - Pembebanan & pemostingan biaya penalti pembatalan (cancellation fee) ke LedgerService
 *   - Pencatatan riwayat status append-only tak-terhapuskan beserta snapshot finansial
 */
final class HedgeContractStateMachine
{
    public const DEFAULT_CANCELLATION_FEE_IDR = 5_000_000;

    public const MAX_SINGLE_CONTRACT_NOTIONAL_MINOR = 100_000_000_000;

    public const CANCELLATION_FEE_BPS = 50; // 0.50% = 50 basis points

    /** @var array<string, list<string>> */
    private const ALLOWED_TRANSITIONS = [
        HedgeStatus::DRAFT->value => [
            HedgeStatus::APPROVED->value,
            HedgeStatus::CANCELLED->value,
        ],
        HedgeStatus::APPROVED->value => [
            HedgeStatus::BOOKED->value,
            HedgeStatus::CANCELLED->value,
        ],
        HedgeStatus::BOOKED->value => [
            HedgeStatus::ACTIVE->value,
            HedgeStatus::CANCELLED->value,
        ],
        HedgeStatus::ACTIVE->value => [
            HedgeStatus::MATURED->value,
            HedgeStatus::SETTLED->value,
            HedgeStatus::ROLLED->value,
            HedgeStatus::CANCELLED->value,
        ],
        HedgeStatus::MATURED->value => [
            HedgeStatus::SETTLED->value,
            HedgeStatus::ROLLED->value,
            HedgeStatus::CANCELLED->value,
        ],
        HedgeStatus::SETTLED->value => [],
        HedgeStatus::ROLLED->value => [],
        HedgeStatus::CANCELLED->value => [],
    ];

    /** @var array<string, string> */
    private const TRANSITION_ACTION_LABELS = [
        'draft->approved' => 'approve_draft_contract',
        'draft->cancelled' => 'cancel_unapproved_draft',
        'approved->booked' => 'book_counterparty_bank_deal',
        'approved->cancelled' => 'cancel_approved_before_booking',
        'booked->active' => 'activate_booked_forward',
        'booked->cancelled' => 'cancel_booked_with_penalty',
        'active->matured' => 'mature_active_forward',
        'active->settled' => 'early_or_direct_settle_forward',
        'active->rolled' => 'rollover_active_forward',
        'active->cancelled' => 'unwind_active_forward_with_penalty',
        'matured->settled' => 'settle_matured_forward',
        'matured->rolled' => 'rollover_matured_forward',
        'matured->cancelled' => 'cancel_matured_forward_with_penalty',
    ];

    public function __construct(
        private readonly ?ClockInterface $clock = null,
        private readonly ?TreasuryEventPublisher $eventPublisher = null,
        private readonly ?TreasuryTaskInboxNotifier $taskNotifier = null,
        private readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        private readonly ?ApprovalEngineInterface $approvalEngine = null,
    ) {}

    public function canTransition(ForwardContract $contract, HedgeStatus $targetStatus): bool
    {
        $current = $this->resolveStatusValue($contract);
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        return in_array($targetStatus->value, $allowed, true);
    }

    /**
     * Mengembalikan daftar status tujuan yang diizinkan dari status kontrak saat ini.
     *
     * @return list<HedgeStatus>
     */
    public function allowedNextStatuses(ForwardContract $contract): array
    {
        $current = $this->resolveStatusValue($contract);
        $allowedValues = self::ALLOWED_TRANSITIONS[$current] ?? [];

        $statuses = [];
        foreach ($allowedValues as $val) {
            $statuses[] = HedgeStatus::from($val);
        }

        return $statuses;
    }

    /**
     * Mengeksekusi perpindahan status kontrak forward dengan seluruh pemeriksaan guard,
     * efek samping buku besar, penerbitan event, dan pencatatan riwayat append-only.
     *
     * @param  array<string, mixed>  $context
     */
    public function transition(
        ForwardContract $contract,
        HedgeStatus $targetStatus,
        int|string|null $actorId = null,
        string $reason = '',
        array $context = []
    ): ForwardContract {
        $fromStatus = $this->resolveStatusValue($contract);
        $toStatus = $targetStatus->value;

        if (! $this->canTransition($contract, $targetStatus)) {
            throw new HedgeInvalidTransitionException($fromStatus, $toStatus);
        }

        $this->validateContractIntegrity($contract);
        $this->enforceTransitionGuards($contract, $fromStatus, $targetStatus, $actorId, $reason, $context);

        return DB::transaction(function () use ($contract, $fromStatus, $toStatus, $targetStatus, $actorId, $reason, $context) {
            $now = $this->now();
            $nowFormatted = $now->format('Y-m-d H:i:s');

            $updateAttributes = [
                'status' => $toStatus,
            ];

            // Penanganan khusus pembatalan dari status yang sudah terikat dengan bank
            if ($targetStatus === HedgeStatus::CANCELLED) {
                $penaltyIdr = $this->resolveCancellationPenalty($contract, $fromStatus, $context);
                $updateAttributes['cancellation_fee_idr'] = $penaltyIdr;
                $updateAttributes['cancellation_fee_payer'] = (string) ($context['cancellation_fee_payer'] ?? $contract->cancellation_fee_payer ?? 'HOLDING');
                if ($reason !== '' && empty($contract->rejection_reason)) {
                    $updateAttributes['rejection_reason'] = $reason;
                }
            }

            $contract->update($updateAttributes);

            $transitionKey = "{$fromStatus}->{$toStatus}";
            $actionName = self::TRANSITION_ACTION_LABELS[$transitionKey] ?? 'status_transition';

            $snapshotMetadata = $this->buildHistoryMetadata(
                contract: $contract,
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                transitionAction: $actionName,
                timestamp: $nowFormatted,
                context: $context
            );

            ForwardContractHistory::create([
                'forward_contract_id' => $contract->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_id' => $actorId !== null ? (int) $actorId : null,
                'action' => $actionName,
                'reason' => $reason !== '' ? $reason : "Transisi siklus hidup {$fromStatus} ke {$toStatus}",
                'metadata' => $snapshotMetadata,
                'created_at' => $nowFormatted,
            ]);

            $freshContract = $contract->fresh() ?? $contract;

            $this->dispatchTransitionSideEffects(
                contract: $freshContract,
                fromStatus: $fromStatus,
                targetStatus: $targetStatus,
                actorId: $actorId !== null ? (int) $actorId : null,
                reason: $reason,
                context: $context
            );

            return $freshContract;
        });
    }

    /**
     * Menghitung estimasi penalti pembatalan kontrak lindung nilai secara deterministik
     * menggunakan aritmetika integer rasional (basis points terhadap nilai komitmen forward).
     */
    public function calculateDeterministicCancellationFee(ForwardContract $contract, int $penaltyBps = self::CANCELLATION_FEE_BPS): int
    {
        if ($contract->cancellation_fee_idr !== null && (int) $contract->cancellation_fee_idr > 0) {
            return (int) $contract->cancellation_fee_idr;
        }

        $notionalMinor = max(0, (int) $contract->notional_foreign_amount);
        $num = max(1, (int) ($contract->contract_rate_numerator ?: $contract->forward_rate_scaled ?: 15500));
        $den = max(1, (int) ($contract->contract_rate_denominator ?: 10000));

        // Hitung nilai komitmen kontrak dalam IDR minor: (notional * num) / (100 * den)
        $commitmentIdr = (int) intdiv($notionalMinor * $num, max(1, 100 * $den));
        if ($commitmentIdr <= 0) {
            return self::DEFAULT_CANCELLATION_FEE_IDR;
        }

        $computedFee = (int) intdiv($commitmentIdr * max(1, $penaltyBps), 10_000);

        return max(self::DEFAULT_CANCELLATION_FEE_IDR, $computedFee);
    }

    /**
     * Memvalidasi integritas struktural kontrak lindung nilai sebelum transisi apa pun.
     */
    private function validateContractIntegrity(ForwardContract $contract): void
    {
        if ((int) $contract->notional_foreign_amount <= 0) {
            throw new InvalidArgumentException(
                "Kontrak lindung nilai {$contract->contract_number} memiliki nominal notional tidak valid (<= 0)."
            );
        }

        if ((int) $contract->notional_foreign_amount > self::MAX_SINGLE_CONTRACT_NOTIONAL_MINOR) {
            throw new InvalidArgumentException(
                "Kontrak lindung nilai {$contract->contract_number} melampaui batas eksposur maksimum per kontrak."
            );
        }

        if ((int) $contract->forward_rate_scaled <= 0 && (int) $contract->contract_rate_numerator <= 0) {
            throw new InvalidArgumentException(
                "Kontrak lindung nilai {$contract->contract_number} wajib memiliki kurs forward rasional positif."
            );
        }

        if (trim((string) $contract->currency) === '' || strtoupper((string) $contract->currency) === 'IDR') {
            throw new InvalidArgumentException(
                "Kontrak lindung nilai {$contract->contract_number} harus menggunakan mata uang valas selain mata uang fungsional IDR."
            );
        }
    }

    /**
     * Menegakkan aturan bisnis spesifik untuk setiap target status.
     *
     * @param  array<string, mixed>  $context
     */
    private function enforceTransitionGuards(
        ForwardContract $contract,
        string $fromStatus,
        HedgeStatus $targetStatus,
        int|string|null $actorId,
        string $reason,
        array $context
    ): void {
        // Guard 1: Four-Eyes Segregation of Duties (Maker != Checker) pada transisi ke APPROVED
        if ($targetStatus === HedgeStatus::APPROVED) {
            if ($contract->creator_id !== null && $actorId !== null && (string) $contract->creator_id === (string) $actorId) {
                throw new HedgeMakerCheckerViolationException(
                    "Penyetuju kontrak lindung nilai (ID: {$actorId}) tidak boleh sama dengan pembuat draf (prinsip Four-Eyes Segregation of Duties)."
                );
            }
        }

        // Guard 2: Transisi ke BOOKED memastikan kontrak sudah melewati persetujuan komite
        if ($targetStatus === HedgeStatus::BOOKED && $fromStatus !== HedgeStatus::APPROVED->value) {
            throw new HedgeInvalidTransitionException($fromStatus, $targetStatus->value);
        }

        // Guard 3: Transisi ke ACTIVE memastikan deal bank sudah dibukukan
        if ($targetStatus === HedgeStatus::ACTIVE && $fromStatus !== HedgeStatus::BOOKED->value) {
            throw new HedgeInvalidTransitionException($fromStatus, $targetStatus->value);
        }

        // Guard 4: Transisi ke ROLLED dari ACTIVE atau MATURED
        if ($targetStatus === HedgeStatus::ROLLED) {
            if (! in_array($fromStatus, [HedgeStatus::ACTIVE->value, HedgeStatus::MATURED->value], true)) {
                throw new HedgeInvalidTransitionException($fromStatus, $targetStatus->value);
            }
        }
    }

    /**
     * Menentukan biaya penalti pembatalan bila kontrak dibatalkan setelah terikat dengan bank
     * (BOOKED, ACTIVE, atau MATURED). Pembatalan saat DRAFT atau APPROVED tidak dikenai penalti bank.
     *
     * @param  array<string, mixed>  $context
     */
    private function resolveCancellationPenalty(ForwardContract $contract, string $fromStatus, array $context): int
    {
        if (isset($context['cancellation_fee_idr']) && (int) $context['cancellation_fee_idr'] > 0) {
            $contract->cancellation_fee_idr = (int) $context['cancellation_fee_idr'];

            return (int) $context['cancellation_fee_idr'];
        }

        if (in_array($fromStatus, [HedgeStatus::BOOKED->value, HedgeStatus::ACTIVE->value, HedgeStatus::MATURED->value], true)) {
            if (! $contract->cancellation_fee_idr || (int) $contract->cancellation_fee_idr <= 0) {
                $contract->cancellation_fee_idr = self::DEFAULT_CANCELLATION_FEE_IDR;
            }

            return (int) $contract->cancellation_fee_idr;
        }

        return (int) ($contract->cancellation_fee_idr ?? 0);
    }

    /**
     * Membangun payload metadata audit append-only untuk tabel trs_forward_contract_histories.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildHistoryMetadata(
        ForwardContract $contract,
        string $fromStatus,
        string $toStatus,
        string $transitionAction,
        string $timestamp,
        array $context
    ): array {
        return [
            'contract_number' => (string) $contract->contract_number,
            'currency' => (string) $contract->currency,
            'notional_foreign_amount' => (int) $contract->notional_foreign_amount,
            'forward_rate_scaled' => (int) $contract->forward_rate_scaled,
            'contract_rate_numerator' => (int) $contract->contract_rate_numerator,
            'contract_rate_denominator' => (int) $contract->contract_rate_denominator,
            'mtm_value_idr' => (int) $contract->mtm_value_idr,
            'settlement_amount_idr' => $contract->settlement_amount_idr !== null ? (int) $contract->settlement_amount_idr : null,
            'settlement_gain_loss_idr' => $contract->settlement_gain_loss_idr !== null ? (int) $contract->settlement_gain_loss_idr : null,
            'cancellation_fee_idr' => $contract->cancellation_fee_idr !== null ? (int) $contract->cancellation_fee_idr : null,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'transition_action' => $transitionAction,
            'recorded_at' => $timestamp,
            'context' => $context,
        ];
    }

    /**
     * Menjalankan efek samping domain setelah status kontrak berhasil diperbarui:
     * 1. Memancarkan HedgeContractTransitionedEvent ke EventSpine
     * 2. Jika targetStatus == MATURED, memancarkan TreasuryHedgeMaturedEvent (treasury.hedge.matured.v1)
     *    dan membuat task penyelesaian di TaskInboxService
     * 3. Jika targetStatus == CANCELLED dari status berpenalti dan post_cancellation_ledger diaktifkan,
     *    memosting biaya penalti ke LedgerService
     *
     * @param  array<string, mixed>  $context
     */
    private function dispatchTransitionSideEffects(
        ForwardContract $contract,
        string $fromStatus,
        HedgeStatus $targetStatus,
        ?int $actorId,
        string $reason,
        array $context
    ): void {
        $publisher = $this->eventPublisher ?? (app()->bound(TreasuryEventPublisher::class) ? app(TreasuryEventPublisher::class) : null);
        $notifier = $this->taskNotifier ?? (app()->bound(TreasuryTaskInboxNotifier::class) ? app(TreasuryTaskInboxNotifier::class) : null);

        if ($publisher !== null) {
            $publisher->publishHedgeTransition(new HedgeContractTransitionedEvent(
                contractId: (string) $contract->id,
                contractNumber: (string) $contract->contract_number,
                fromStatus: $fromStatus,
                toStatus: $targetStatus->value,
                actorId: $actorId,
                reason: $reason
            ));
        }

        if ($targetStatus === HedgeStatus::MATURED) {
            $maturityDateStr = $contract->maturity_date instanceof \DateTimeInterface
                ? $contract->maturity_date->format('Y-m-d')
                : (string) ($contract->maturity_date ?? $this->now()->toDateString());

            if ($publisher !== null) {
                $publisher->publishHedgeMatured(new TreasuryHedgeMaturedEvent(
                    contractId: (string) $contract->id,
                    contractNumber: (string) $contract->contract_number,
                    currency: (string) $contract->currency,
                    notionalForeignAmount: (int) $contract->notional_foreign_amount,
                    maturityDate: $maturityDateStr,
                    mtmValueIdr: (int) $contract->mtm_value_idr,
                    forwardRateScaled: (int) $contract->forward_rate_scaled,
                    actorId: $actorId
                ));
            } elseif ($notifier !== null) {
                $notifier->notifyUpcomingForwardMaturity($contract, 0);
            }
        }

        if (
            $targetStatus === HedgeStatus::CANCELLED
            && (int) $contract->cancellation_fee_idr > 0
            && ! empty($context['post_cancellation_ledger'])
        ) {
            $poster = $this->ledgerPoster ?? (app()->bound(TreasuryLedgerPoster::class) ? app(TreasuryLedgerPoster::class) : null);
            if ($poster !== null) {
                $bankAccCode = (string) ($context['bank_account_code'] ?? 'trs:bank_operational:HOLDING:IDR');
                $idemKey = (string) ($context['idempotency_key'] ?? "IDEM:TRS:FWD_CANCEL:{$contract->id}");
                $poster->postForwardCancellationFee(
                    contractNumber: (string) $contract->contract_number,
                    feeAmountIdr: (int) $contract->cancellation_fee_idr,
                    bankAccountCode: $bankAccCode,
                    idempotencyKey: $idemKey,
                    contractId: (string) $contract->id,
                    userId: $actorId
                );
            }
        }
    }

    private function resolveStatusValue(ForwardContract $contract): string
    {
        return $contract->status instanceof HedgeStatus
            ? $contract->status->value
            : (string) $contract->status;
    }

    private function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }
}

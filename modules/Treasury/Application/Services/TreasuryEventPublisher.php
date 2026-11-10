<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Services;

use Modules\Core\Application\Services\EventSchemaRegistry;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Treasury\Domain\Events\BankStatementReconciledEvent;
use Modules\Treasury\Domain\Events\CashPoolSweepExecutedEvent;
use Modules\Treasury\Domain\Events\FacilityDrawdownExecutedEvent;
use Modules\Treasury\Domain\Events\FxRevaluationCompletedEvent;
use Modules\Treasury\Domain\Events\HedgeContractTransitionedEvent;
use Modules\Treasury\Domain\Events\PettyCashVoucherProcessedEvent;

/**
 * Service penerbit event domain Treasury ke bus EventSpine terpusat.
 * Mendaftarkan skema aditif ke EventSchemaRegistry dan menegakkan kontrak idempotensi.
 */
class TreasuryEventPublisher
{
    public const TOPIC_TREASURY = 'treasury.events';

    public function __construct(
        protected readonly ?EventSpineInterface $spine = null,
        protected readonly ?EventSchemaRegistry $registry = null
    ) {}

    /**
     * Mendaftarkan seluruh skema event domain Treasury ke EventSchemaRegistry.
     */
    public function registerAllSchemas(): void
    {
        if ($this->registry === null) {
            return;
        }

        $this->registry->registerSchema(
            FxRevaluationCompletedEvent::EVENT_NAME,
            1,
            [
                'type' => 'object',
                'properties' => [
                    'event' => ['type' => 'string'],
                    'revaluation_id' => ['type' => 'string'],
                    'period' => ['type' => 'string'],
                    'currency' => ['type' => 'string'],
                    'foreign_balance_minor' => ['type' => 'integer'],
                    'book_functional_idr' => ['type' => 'integer'],
                    'revalued_functional_idr' => ['type' => 'integer'],
                    'gain_loss_idr' => ['type' => 'integer'],
                    'certificate_hash' => ['type' => 'string'],
                    'user_id' => ['type' => ['integer', 'null']],
                ],
                'required' => ['event', 'revaluation_id', 'period', 'currency', 'gain_loss_idr', 'certificate_hash'],
            ],
            'Diterbitkan saat kalkulasi revaluasi kurs PSAK 10 berhasil diposting',
            'Treasury',
            ['GeneralLedger', 'Reporting', 'Auditing']
        );

        $this->registry->registerSchema(
            HedgeContractTransitionedEvent::EVENT_NAME,
            1,
            [
                'type' => 'object',
                'properties' => [
                    'event' => ['type' => 'string'],
                    'contract_id' => ['type' => 'string'],
                    'contract_number' => ['type' => 'string'],
                    'from_status' => ['type' => 'string'],
                    'to_status' => ['type' => 'string'],
                    'actor_id' => ['type' => ['integer', 'null']],
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['event', 'contract_id', 'contract_number', 'from_status', 'to_status'],
            ],
            'Diterbitkan saat status kontrak forward berpindah siklus hidup',
            'Treasury',
            ['RiskManagement', 'Compliance', 'Auditing']
        );

        $this->registry->registerSchema(
            FacilityDrawdownExecutedEvent::EVENT_NAME,
            1,
            [
                'type' => 'object',
                'properties' => [
                    'event' => ['type' => 'string'],
                    'facility_id' => ['type' => 'string'],
                    'facility_code' => ['type' => 'string'],
                    'bank_name' => ['type' => 'string'],
                    'drawdown_amount_minor' => ['type' => 'integer'],
                    'currency' => ['type' => 'string'],
                    'new_total_drawn_minor' => ['type' => 'integer'],
                    'current_der_permille' => ['type' => 'integer'],
                    'max_der_permille' => ['type' => 'integer'],
                    'actor_id' => ['type' => ['integer', 'null']],
                ],
                'required' => ['event', 'facility_id', 'facility_code', 'drawdown_amount_minor', 'current_der_permille'],
            ],
            'Diterbitkan saat penarikan pinjaman bank fasilitas kredit dieksekusi',
            'Treasury',
            ['CorporateFinance', 'GeneralLedger', 'Auditing']
        );

        $this->registry->registerSchema(
            CashPoolSweepExecutedEvent::EVENT_NAME,
            1,
            [
                'type' => 'object',
                'properties' => [
                    'event' => ['type' => 'string'],
                    'pool_id' => ['type' => 'string'],
                    'pool_code' => ['type' => 'string'],
                    'source_account_id' => ['type' => 'string'],
                    'target_account_id' => ['type' => 'string'],
                    'sweep_amount_minor' => ['type' => 'integer'],
                    'currency' => ['type' => 'string'],
                    'source_balance_after_minor' => ['type' => 'integer'],
                    'target_balance_after_minor' => ['type' => 'integer'],
                    'posting_reference' => ['type' => 'string'],
                    'actor_id' => ['type' => ['integer', 'null']],
                ],
                'required' => ['event', 'pool_id', 'pool_code', 'sweep_amount_minor', 'posting_reference'],
            ],
            'Diterbitkan saat penyapuan kas pool notional dieksekusi',
            'Treasury',
            ['Intercompany', 'GeneralLedger', 'Auditing']
        );

        $this->registry->registerSchema(
            BankStatementReconciledEvent::EVENT_NAME,
            1,
            [
                'type' => 'object',
                'properties' => [
                    'event' => ['type' => 'string'],
                    'bank_account_id' => ['type' => 'string'],
                    'account_number' => ['type' => 'string'],
                    'currency' => ['type' => 'string'],
                    'matched_count' => ['type' => 'integer'],
                    'unmatched_count' => ['type' => 'integer'],
                    'matched_amount_minor' => ['type' => 'integer'],
                    'unmatched_amount_minor' => ['type' => 'integer'],
                    'certificate_hash' => ['type' => 'string'],
                    'actor_id' => ['type' => ['integer', 'null']],
                ],
                'required' => ['event', 'bank_account_id', 'matched_count', 'certificate_hash'],
            ],
            'Diterbitkan saat batch rekonsiliasi mutasi rekening koran selesai',
            'Treasury',
            ['Auditing', 'Reporting']
        );

        $this->registry->registerSchema(
            PettyCashVoucherProcessedEvent::EVENT_NAME,
            1,
            [
                'type' => 'object',
                'properties' => [
                    'event' => ['type' => 'string'],
                    'voucher_id' => ['type' => 'string'],
                    'voucher_number' => ['type' => 'string'],
                    'action_type' => ['type' => 'string'],
                    'amount_minor' => ['type' => 'integer'],
                    'currency' => ['type' => 'string'],
                    'remaining_balance_minor' => ['type' => 'integer'],
                    'expense_category' => ['type' => ['string', 'null']],
                    'actor_id' => ['type' => ['integer', 'null']],
                ],
                'required' => ['event', 'voucher_id', 'voucher_number', 'amount_minor'],
            ],
            'Diterbitkan saat voucher kas kecil dicairkan atau dana kas kecil diisi kembali',
            'Treasury',
            ['GeneralLedger', 'PettyCashManagement']
        );
    }

    public function publishFxRevaluation(FxRevaluationCompletedEvent $event): void
    {
        if ($this->spine === null) {
            return;
        }

        $idempotencyKey = "trs_fx_reval:{$event->revaluationId}:{$event->period}";
        $this->spine->publish(
            self::TOPIC_TREASURY,
            FxRevaluationCompletedEvent::EVENT_NAME,
            $event->jsonSerialize(),
            $idempotencyKey,
            1
        );
    }

    public function publishHedgeTransition(HedgeContractTransitionedEvent $event): void
    {
        if ($this->spine === null) {
            return;
        }

        $idempotencyKey = "trs_hedge_trans:{$event->contractId}:{$event->toStatus}";
        $this->spine->publish(
            self::TOPIC_TREASURY,
            HedgeContractTransitionedEvent::EVENT_NAME,
            $event->jsonSerialize(),
            $idempotencyKey,
            1
        );
    }

    public function publishFacilityDrawdown(FacilityDrawdownExecutedEvent $event): void
    {
        if ($this->spine === null) {
            return;
        }

        $idempotencyKey = "trs_facility_draw:{$event->facilityId}:{$event->drawdownAmountMinor}:{$event->newTotalDrawnMinor}";
        $this->spine->publish(
            self::TOPIC_TREASURY,
            FacilityDrawdownExecutedEvent::EVENT_NAME,
            $event->jsonSerialize(),
            $idempotencyKey,
            1
        );
    }

    public function publishCashPoolSweep(CashPoolSweepExecutedEvent $event): void
    {
        if ($this->spine === null) {
            return;
        }

        $idempotencyKey = "trs_pool_sweep:{$event->poolId}:{$event->postingReference}";
        $this->spine->publish(
            self::TOPIC_TREASURY,
            CashPoolSweepExecutedEvent::EVENT_NAME,
            $event->jsonSerialize(),
            $idempotencyKey,
            1
        );
    }

    public function publishBankReconciled(BankStatementReconciledEvent $event): void
    {
        if ($this->spine === null) {
            return;
        }

        $idempotencyKey = "trs_bank_recon:{$event->bankAccountId}:{$event->certificateHash}";
        $this->spine->publish(
            self::TOPIC_TREASURY,
            BankStatementReconciledEvent::EVENT_NAME,
            $event->jsonSerialize(),
            $idempotencyKey,
            1
        );
    }

    public function publishPettyCashVoucher(PettyCashVoucherProcessedEvent $event): void
    {
        if ($this->spine === null) {
            return;
        }

        $idempotencyKey = "trs_petty_voucher:{$event->voucherId}:{$event->voucherNumber}";
        $this->spine->publish(
            self::TOPIC_TREASURY,
            PettyCashVoucherProcessedEvent::EVENT_NAME,
            $event->jsonSerialize(),
            $idempotencyKey,
            1
        );
    }
}

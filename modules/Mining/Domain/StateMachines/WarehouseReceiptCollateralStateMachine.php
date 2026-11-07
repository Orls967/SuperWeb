<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Modules\Mining\Domain\Enums\WarehouseReceiptStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;

/**
 * State machine untuk Resi Gudang Logam Beragun SCF Bank (121.4).
 * Mengawal 8 status: ISSUED -> VERIFIED_ASSAY -> REGISTERED_BAPPEBTI -> PLEDGED_SCF -> PARTIAL_RELEASE -> FULLY_RELEASED / DEFAULTED / CANCELLED.
 */
class WarehouseReceiptCollateralStateMachine
{
    protected ?MiningMetalsWarehouseReceipt $receipt = null;

    public function __construct(?MiningMetalsWarehouseReceipt $receipt = null)
    {
        $this->receipt = $receipt;
    }

    public function canTransition(WarehouseReceiptStatus $current, WarehouseReceiptStatus $target): bool
    {
        return $current->canTransitionTo($target);
    }

    public function transitionTo(WarehouseReceiptStatus $targetStatus): void
    {
        if (! $this->receipt) {
            throw new InvalidMiningStateTransitionException('Instance MiningMetalsWarehouseReceipt belum dikonfigurasi dalam state machine.');
        }

        $this->transition($this->receipt, $targetStatus);
    }

    public function transition(MiningMetalsWarehouseReceipt $receipt, WarehouseReceiptStatus $targetStatus): void
    {
        $currentVal = strtoupper((string) ($receipt->status ?? 'ISSUED'));
        $currentStatus = WarehouseReceiptStatus::tryFrom($currentVal) ?? WarehouseReceiptStatus::ISSUED;

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningMetalsWarehouseReceipt',
                $currentStatus->value,
                $targetStatus->value,
                "Resi gudang {$receipt->receipt_number} ({$currentStatus->value}) tidak dapat dialihkan ke {$targetStatus->value}."
            );
        }

        $this->assertTransitionGuard($receipt, $currentStatus, $targetStatus);

        $receipt->status = $targetStatus->value;
        $receipt->save();
    }

    protected function assertTransitionGuard(
        MiningMetalsWarehouseReceipt $receipt,
        WarehouseReceiptStatus $current,
        WarehouseReceiptStatus $target
    ): void {
        if ($target === WarehouseReceiptStatus::VERIFIED_ASSAY && empty($receipt->assay_id)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningMetalsWarehouseReceipt',
                $current->value,
                $target->value,
                'Verifikasi resi gudang mewajibkan tautan sertifikasi assay laboratorium.'
            );
        }

        if ($target === WarehouseReceiptStatus::PLEDGED_SCF && empty($receipt->pledged_bank_name)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningMetalsWarehouseReceipt',
                $current->value,
                $target->value,
                'Penjaminan SCF bank mewajibkan nama bank mitra penyedia fasilitas SCF.'
            );
        }

        if ($target === WarehouseReceiptStatus::PLEDGED_SCF && $receipt->max_financing_limit_idr <= 0) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningMetalsWarehouseReceipt',
                $current->value,
                $target->value,
                'Plafond pembiayaan SCF harus lebih besar dari 0 IDR.'
            );
        }
    }
}

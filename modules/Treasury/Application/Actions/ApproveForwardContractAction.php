<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine;

class ApproveForwardContractAction
{
    public function __construct(
        protected readonly HedgeContractStateMachine $stateMachine = new HedgeContractStateMachine
    ) {}

    /**
     * Memverifikasi dan menyetujui kontrak forward (Four-Eyes / Segregation of Duties).
     */
    public function execute(ForwardContract $contract, int $approverId, string $notes = ''): ForwardContract
    {
        return DB::transaction(function () use ($contract, $approverId, $notes) {
            // Transisi PENDING_APPROVAL -> APPROVED (guard checker != creator dipicu di sini)
            $approved = $this->stateMachine->transition(
                $contract,
                HedgeStatus::APPROVED,
                $approverId,
                $notes ?: 'Persetujuan resmi Four-Eyes oleh pejabat berwenang'
            );

            // Transisi otomatis APPROVED -> BOOKED -> ACTIVE
            $booked = $this->stateMachine->transition(
                $approved,
                HedgeStatus::BOOKED,
                $approverId,
                'Konfirmasi deal perbankan rekanan'
            );

            return $this->stateMachine->transition(
                $booked,
                HedgeStatus::ACTIVE,
                $approverId,
                'Aktivasi efektif instrumen lindung nilai aktif'
            );
        });
    }
}

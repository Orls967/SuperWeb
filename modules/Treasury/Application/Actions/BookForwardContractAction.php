<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\Models\ForwardContractHistory;
use Modules\Treasury\Domain\StateMachines\HedgeContractStateMachine;
use Psr\Clock\ClockInterface;

class BookForwardContractAction
{
    public function __construct(
        protected readonly ?DocumentNumberingInterface $documentNumbering = null,
        protected readonly ?ClockInterface $clock = null,
        protected readonly HedgeContractStateMachine $stateMachine = new HedgeContractStateMachine,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Membuat draf kontrak lindung nilai dan mengajukannya untuk persetujuan (maker).
     */
    public function execute(
        string $currency,
        int $notionalForeignMinor,
        int $forwardRateScaled,
        string $maturityDate,
        int $creatorId,
        ?string $customContractNumber = null
    ): ForwardContract {
        if ($notionalForeignMinor <= 0) {
            throw new InvalidArgumentException('Nominal nosional valas harus lebih besar dari 0.');
        }

        if ($forwardRateScaled <= 0) {
            throw new InvalidArgumentException('Kurs forward harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($currency, $notionalForeignMinor, $forwardRateScaled, $maturityDate, $creatorId, $customContractNumber) {
            $contractNumber = $customContractNumber;
            if ($contractNumber === null) {
                if ($this->documentNumbering !== null) {
                    $contractNumber = $this->documentNumbering->nextNumber('HOLDING', 'FWD', true, 'FWD/{YYYY}/{MM}/', 4);
                } else {
                    $contractNumber = 'FWD-'.date('Ymd').'-'.str_pad((string) (ForwardContract::count() + 1), 4, '0', STR_PAD_LEFT);
                }
            }

            $contract = ForwardContract::create([
                'contract_number' => $contractNumber,
                'currency' => strtoupper($currency),
                'creator_id' => $creatorId,
                'notional_foreign_amount' => $notionalForeignMinor,
                'forward_rate_scaled' => $forwardRateScaled,
                'contract_rate_numerator' => $forwardRateScaled,
                'contract_rate_denominator' => 10000,
                'maturity_date' => $maturityDate,
                'status' => HedgeStatus::DRAFT->value,
                'mtm_value_idr' => 0,
            ]);

            ForwardContractHistory::create([
                'forward_contract_id' => $contract->id,
                'from_status' => 'NONE',
                'to_status' => HedgeStatus::DRAFT->value,
                'actor_id' => $creatorId,
                'action' => 'contract_created',
                'reason' => 'Pembuatan draf awal deal kontrak lindung nilai',
                'created_at' => $this->now(),
            ]);

            // Transisi ke PENDING_APPROVAL
            return $this->stateMachine->transition(
                $contract,
                HedgeStatus::PENDING_APPROVAL,
                $creatorId,
                'Pengajuan verifikasi approval lindung nilai'
            );
        });
    }
}

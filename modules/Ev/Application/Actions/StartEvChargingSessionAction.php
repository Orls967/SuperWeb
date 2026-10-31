<?php

declare(strict_types=1);

namespace Modules\Ev\Application\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Ledger\EvAccounts;
use Modules\Ev\Ledger\EvTransactionTypes;

class StartEvChargingSessionAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public function execute(
        int $chargerId,
        int $vehicleId,
        int $userId,
        int $estimatedDepositIdr,
        CarbonInterface $startTime
    ): EvSession {
        $charger = EvCharger::findOrFail($chargerId);

        if (strtoupper($charger->status) !== 'AVAILABLE') {
            throw new \LogicException('Charger is not available');
        }

        $sessionCode = 'SES-EV-'.strtoupper(Str::random(10));
        $userWallet = EvAccounts::ensureUserWallet($this->ledger, $userId);

        // Posting Hold Deposit: Debet User Wallet, Kredit Escrow
        $lines = Posting::lines()
            ->debit($userWallet, $estimatedDepositIdr)
            ->credit(EvAccounts::ESCROW, $estimatedDepositIdr)
            ->toEntries('IDR');

        $this->ledger->post(new PostingDTO(
            type: EvTransactionTypes::EV_HOLD->value,
            description: "Hold Deposit Pengisian EV {$sessionCode}",
            idempotencyKey: "oto:ev_hold:{$sessionCode}",
            entries: $lines,
            referenceType: 'EvSession',
            referenceId: $sessionCode
        ));

        $charger->update(['status' => 'OCCUPIED']);

        return EvSession::create([
            'session_code' => $sessionCode,
            'charger_id' => $chargerId,
            'vehicle_id' => $vehicleId,
            'user_id' => $userId,
            'status' => 'CHARGING',
            'deposit_held_idr' => $estimatedDepositIdr,
            'started_at' => $startTime,
            'plugged_in_at' => $startTime,
            'energy_wh' => 0,
            'total_cost_idr' => 0,
            'refund_idr' => 0,
        ]);
    }
}

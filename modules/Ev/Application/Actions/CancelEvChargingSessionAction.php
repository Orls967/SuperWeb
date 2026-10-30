<?php

declare(strict_types=1);

namespace Modules\Ev\Application\Actions;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Ledger\EvAccounts;
use Modules\Ev\Ledger\EvTransactionTypes;

class CancelEvChargingSessionAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public function execute(int $sessionId, string $reason = ''): EvSession
    {
        $session = EvSession::with('charger')->findOrFail($sessionId);

        if ($session->status === 'CANCELLED') {
            return $session;
        }

        $depositHeld = (int) $session->deposit_held_idr;
        $userWallet = EvAccounts::ensureUserWallet($this->ledger, (int) $session->user_id);

        if ($depositHeld > 0) {
            $lines = Posting::lines()
                ->debit(EvAccounts::ESCROW, $depositHeld)
                ->credit($userWallet, $depositHeld)
                ->toEntries('IDR');

            $this->ledger->post(new PostingDTO(
                type: EvTransactionTypes::EV_RELEASE->value,
                description: "Pembatalan Sesi EV {$session->session_code}: {$reason}",
                idempotencyKey: "oto:ev_cancel:{$session->session_code}",
                entries: $lines,
                referenceType: 'EvSession',
                referenceId: (string) $session->id
            ));
        }

        if ($session->charger) {
            $session->charger->update(['status' => 'AVAILABLE']);
        }

        $session->update([
            'status' => 'CANCELLED',
            'refund_idr' => $depositHeld,
        ]);

        return $session->fresh();
    }
}

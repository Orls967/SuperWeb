<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Sagas;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\IdentityVelocityRiskScorer;
use Modules\Venue\Application\Actions\ExecuteSecondaryTicketResaleAction;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingCapEngine;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingInput;
use Modules\Venue\Domain\DTOs\SecondaryResalePurchaseDTO;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\StateMachines\TicketResaleStateMachine;

final class SecondaryTicketPurchaseSaga
{
    public function __construct(
        private readonly ExecuteSecondaryTicketResaleAction $executeAction,
        private readonly AntiScalpingCapEngine $antiScalpingEngine,
        private readonly ?IdentityVelocityRiskScorer $velocityRiskScorer = null
    ) {}

    /**
     * @return array{saga_id: string, status: string, resale_id: int, ticket_id: int, buyer_user_id: int, new_ticket_hash: string, settled_price_idr: int}
     */
    public function execute(SecondaryResalePurchaseDTO $purchaseDto): array
    {
        $sagaId = 'SAGA-SEC-'.bin2hex(random_bytes(8));

        return DB::transaction(function () use ($purchaseDto, $sagaId): array {
            $resale = TicketResale::query()->lockForUpdate()->findOrFail($purchaseDto->resaleId);

            if ($resale->seller_user_id === $purchaseDto->buyerUserId) {
                throw new \DomainException('Penjual tidak dapat membeli tiketnya sendiri di pasar sekunder.');
            }

            if ($resale->status !== TicketResaleStateMachine::STATUS_LISTED) {
                throw new \DomainException("Status listing resale bukan LISTED melainkan {$resale->status}");
            }

            // Step 1: Evaluasi risiko bot / velocity
            if ($this->velocityRiskScorer !== null && $purchaseDto->buyerIp !== null) {
                $velocityScore = $this->velocityRiskScorer->scoreVelocity(
                    $purchaseDto->buyerUserId,
                    $purchaseDto->buyerIp,
                    $purchaseDto->buyerDeviceFingerprint
                );

                if ($velocityScore >= 80) {
                    throw new \DomainException("Pemesanan ditolak oleh Anti-Scalping Engine: skor velocity terlalu tinggi ({$velocityScore})");
                }
            }

            // Step 2: Verifikasi batas cap anti-scalping 120%
            $capInput = new AntiScalpingInput(
                $resale->face_value_idr,
                $purchaseDto->offeredPriceIdr,
                1
            );
            $capResult = $this->antiScalpingEngine->evaluate($capInput);
            if (! $capResult->isAllowed) {
                throw new \DomainException("Harga penawaran melebihi batas legal anti-scalping: {$capResult->reason}");
            }

            // Step 3: Transisi status ke ESCROW_HOLD
            $resale->transitionTo(TicketResaleStateMachine::STATUS_ESCROW_HOLD);
            $resale->buyer_user_id = $purchaseDto->buyerUserId;
            $resale->save();

            try {
                // Step 4: Eksekusi settlement dan waterfall
                $executedResale = $this->executeAction->execute(
                    $resale,
                    $purchaseDto->buyerUserId,
                    $purchaseDto->offeredPriceIdr
                );

                return [
                    'saga_id' => $sagaId,
                    'status' => 'SETTLED',
                    'resale_id' => $executedResale->id,
                    'ticket_id' => $executedResale->ticket_id,
                    'buyer_user_id' => $purchaseDto->buyerUserId,
                    'new_ticket_hash' => (string) $executedResale->new_ticket_hash,
                    'settled_price_idr' => $purchaseDto->offeredPriceIdr,
                ];
            } catch (\Throwable $e) {
                // Kompensasi rollback: kembalikan ke LISTED
                $resale->transitionTo(TicketResaleStateMachine::STATUS_LISTED);
                $resale->buyer_user_id = null;
                $resale->save();

                throw new \RuntimeException("Saga SecondaryTicketPurchase gagal dan dikompensasi: {$e->getMessage()}", 0, $e);
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingCapEngine;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingInput;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\StateMachines\TicketResaleStateMachine;

final class VenueSecondaryMarketService
{
    public function __construct(
        private readonly AntiScalpingCapEngine $capEngine
    ) {}

    /**
     * @return LengthAwarePaginator<TicketResale>
     */
    public function getActiveListings(int $perPage = 20, ?int $eventId = null): LengthAwarePaginator
    {
        $query = TicketResale::query()
            ->with(['ticket', 'seller', 'ticket.event'])
            ->where('status', TicketResaleStateMachine::STATUS_LISTED)
            ->orderBy('resale_price_idr', 'asc');

        if ($eventId !== null) {
            $query->whereHas('ticket', function ($q) use ($eventId): void {
                $q->where('event_id', $eventId);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @return Collection<int, TicketResale>
     */
    public function getSellerListings(int $sellerUserId): Collection
    {
        return TicketResale::query()
            ->with(['ticket', 'ticket.event'])
            ->where('seller_user_id', $sellerUserId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * @return Collection<int, TicketResale>
     */
    public function getBuyerPurchases(int $buyerUserId): Collection
    {
        return TicketResale::query()
            ->with(['ticket', 'ticket.event', 'seller'])
            ->where('buyer_user_id', $buyerUserId)
            ->where('status', TicketResaleStateMachine::STATUS_SETTLED)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Validasi kelayakan tiket sebelum didaftarkan ke pasar sekunder.
     *
     * @return array{is_eligible: bool, reason: string, max_allowed_price_idr: int, face_value_idr: int}
     */
    public function checkTicketListingEligibility(EntertainmentTicket $ticket, int $proposedPriceIdr): array
    {
        if ($ticket->status !== 'VALID' && $ticket->status !== 'ISSUED') {
            return [
                'is_eligible' => false,
                'reason' => "Status tiket tidak valid untuk dijual: {$ticket->status}",
                'max_allowed_price_idr' => 0,
                'face_value_idr' => $ticket->price_idr,
            ];
        }

        $settledCount = TicketResale::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', TicketResaleStateMachine::STATUS_SETTLED)
            ->count();

        if ($settledCount > 0) {
            return [
                'is_eligible' => false,
                'reason' => 'Tiket sudah pernah dipindahtangankan di pasar sekunder (aturan single transfer).',
                'max_allowed_price_idr' => 0,
                'face_value_idr' => $ticket->price_idr,
            ];
        }

        $activeListing = TicketResale::query()
            ->where('ticket_id', $ticket->id)
            ->whereIn('status', [TicketResaleStateMachine::STATUS_LISTED, TicketResaleStateMachine::STATUS_ESCROW_HOLD])
            ->exists();

        if ($activeListing) {
            return [
                'is_eligible' => false,
                'reason' => 'Tiket saat ini sudah terdaftar di pasar sekunder dan masih aktif.',
                'max_allowed_price_idr' => 0,
                'face_value_idr' => $ticket->price_idr,
            ];
        }

        $evalInput = new AntiScalpingInput($ticket->price_idr, $proposedPriceIdr, 1);
        $capResult = $this->capEngine->evaluate($evalInput);

        return [
            'is_eligible' => $capResult->isAllowed,
            'reason' => $capResult->reason,
            'max_allowed_price_idr' => $capResult->maxAllowedPriceIdr,
            'face_value_idr' => $ticket->price_idr,
        ];
    }

    /**
     * Ringkasan metrik pasar sekunder untuk dashboard analitik.
     *
     * @return array{total_listed: int, total_settled: int, total_turnover_idr: int, total_tax_pbjt_idr: int, total_platform_fee_idr: int}
     */
    public function getMarketMetricsSummary(): array
    {
        $listedCount = TicketResale::query()->where('status', TicketResaleStateMachine::STATUS_LISTED)->count();
        $settled = TicketResale::query()->where('status', TicketResaleStateMachine::STATUS_SETTLED)->get();

        $totalSettled = $settled->count();
        $totalTurnover = (int) $settled->sum('resale_price_idr');
        $totalTaxPbjt = (int) $settled->sum('pbjt_tax_idr');
        $totalPlatformFee = (int) $settled->sum('platform_fee_idr');

        return [
            'total_listed' => $listedCount,
            'total_settled' => $totalSettled,
            'total_turnover_idr' => $totalTurnover,
            'total_tax_pbjt_idr' => $totalTaxPbjt,
            'total_platform_fee_idr' => $totalPlatformFee,
        ];
    }
}

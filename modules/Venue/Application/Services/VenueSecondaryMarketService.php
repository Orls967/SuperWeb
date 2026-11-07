<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingCapEngine;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingInput;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\Models\TicketWaitlist;
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

    public function listTicketForResale(EntertainmentTicket $ticket, int $sellerUserId, int $priceIdr): TicketResale
    {
        $faceValue = $ticket->price_paid_idr ?? $ticket->price_idr ?? 1000000;
        $maxAllowed = (int) intdiv($faceValue * 120, 100);

        if ($priceIdr > $maxAllowed) {
            throw new \RuntimeException("Proposed price {$priceIdr} exceeds anti-scalping price cap of {$maxAllowed}");
        }

        $transfers = TicketResale::query()
            ->where('ticket_id', $ticket->id)
            ->whereIn('status', ['SOLD', 'SETTLED'])
            ->count();

        if ($transfers > 0) {
            throw new \RuntimeException('Ticket has already reached maximum of 1 secondary transfer');
        }

        return TicketResale::create([
            'resale_code' => 'RSL-'.Str::upper(Str::random(8)),
            'ticket_id' => $ticket->id,
            'seller_user_id' => $sellerUserId,
            'original_face_value_idr' => $faceValue,
            'resale_price_idr' => $priceIdr,
            'platform_fee_idr' => (int) intdiv($priceIdr * 10, 100),
            'tax_pbjt_idr' => (int) intdiv($priceIdr * 10, 100),
            'entertainment_tax_idr' => (int) intdiv($priceIdr * 10, 100),
            'seller_payout_idr' => $priceIdr - (int) intdiv($priceIdr * 20, 100),
            'transfer_count' => 0,
            'status' => 'LISTED',
        ]);
    }

    public function purchaseResaleTicket(TicketResale $resale, int $buyerUserId): TicketResale
    {
        $price = $resale->resale_price_idr;
        $fee = (int) intdiv($price * 10, 100);
        $tax = (int) intdiv($price * 10, 100);
        $payout = $price - ($fee + $tax);

        $resale->status = 'SOLD';
        $resale->buyer_user_id = $buyerUserId;
        $resale->platform_fee_idr = $fee;
        $resale->tax_pbjt_idr = $tax;
        $resale->entertainment_tax_idr = $tax;
        $resale->seller_payout_idr = $payout;
        $resale->transfer_count = 1;
        $resale->settled_at = now();
        $resale->save();

        // Update ticket buyer
        $ticket = $resale->ticket;
        if ($ticket !== null) {
            $ticket->buyer_user_id = $buyerUserId;
            $ticket->save();
        }

        // Ledger posting using Ledger facade / Posting helper if available
        $clearing = LedgerAccount::where('code', 'ven:secondary_ticket_clearing:IDR')->first();
        $feeRev = LedgerAccount::where('code', 'ven:resale_platform_fee_revenue:IDR')->first();
        $taxPayable = LedgerAccount::where('code', 'ven:entertainment_tax_payable:IDR')->first();
        $sellerPayable = LedgerAccount::where('code', 'ven:resale_seller_payable:IDR')->first();

        if ($feeRev) {
            $feeRev->cached_balance = (string) (((int) $feeRev->cached_balance) + $fee);
            $feeRev->save();
        }
        if ($taxPayable) {
            $taxPayable->cached_balance = (string) (((int) $taxPayable->cached_balance) + $tax);
            $taxPayable->save();
        }
        if ($sellerPayable) {
            $sellerPayable->cached_balance = (string) (((int) $sellerPayable->cached_balance) + $payout);
            $sellerPayable->save();
        }
        if ($clearing) {
            $clearing->cached_balance = (string) (((int) $clearing->cached_balance) + $price);
            $clearing->save();
        }

        return $resale;
    }

    public function joinWaitlist(int $eventId, int $zoneId, int $userId): TicketWaitlist
    {
        return TicketWaitlist::create([
            'waitlist_code' => 'WLT-'.Str::upper(Str::random(8)),
            'event_id' => $eventId,
            'zone_id' => $zoneId,
            'user_id' => $userId,
            'status' => 'WAITING',
        ]);
    }

    public function offerSeatToWaitlist(TicketWaitlist $waitlist, int $minutes = 15): TicketWaitlist
    {
        $waitlist->status = 'OFFERED';
        $waitlist->offer_expires_at = now()->addMinutes($minutes);
        $waitlist->save();

        return $waitlist;
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

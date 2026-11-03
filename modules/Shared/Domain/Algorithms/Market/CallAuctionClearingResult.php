<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Market;

/**
 * DTO hasil komputasi Call Auction Clearing beserta sertifikat validitas (D24-3).
 */
final readonly class CallAuctionClearingResult
{
    /**
     * @param  int  $clearingPrice  Harga kliring per unit (Rp/kg)
     * @param  int  $clearingVolume  Volume total transaksi yang tereksekusi
     * @param  array<string|int, int>  $bidAllocations  Peta ID bid => kuantitas terisi
     * @param  array<string|int, int>  $askAllocations  Peta ID ask => kuantitas terisi
     * @param  list<array{bid_id: string|int, ask_id: string|int, quantity: int, price: int}>  $trades  Daftar transaksi berpasangan
     * @param array{
     *     clearing_price: int,
     *     clearing_volume: int,
     *     highest_unexecuted_bid_price: ?int,
     *     lowest_unexecuted_ask_price: ?int,
     *     is_valid: bool
     * } $certificate Sertifikat matematis kliring
     */
    public function __construct(
        public int $clearingPrice,
        public int $clearingVolume,
        public array $bidAllocations,
        public array $askAllocations,
        public array $trades,
        public array $certificate,
    ) {}
}

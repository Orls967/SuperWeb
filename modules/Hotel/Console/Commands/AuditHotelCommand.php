<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Hotel\Application\Services\HotelOperationsService;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;

class AuditHotelCommand extends Command
{
    protected $signature = 'htl:audit';

    protected $aliases = ['hotel:audit'];

    protected $description = 'Audit operasional hotel terpadu: okupansi, inventaris kamar per malam, dan rekonsiliasi folio terhadap ledger';

    public function handle(HotelOperationsService $service): int
    {
        $this->info('Starting Hotel Operations Audit (htl:audit)...');

        $result = $service->auditHotel();
        $discrepancies = $result['discrepancies'];

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $result['status']],
                ['Properties', $result['property_count']],
                ['Rooms', $result['room_count']],
                ['Reservations', $result['reservation_count']],
                ['Folios', $result['folio_count']],
                ['Occupancy Discrepancies', $result['discrepancies']],
            ]
        );

        // 1. Verifikasi Keseimbangan Setiap Transaksi Ledger Hotel (Debit + Credit = 0)
        $hotelTxTypes = [
            'HOTEL_GUEST_DEPOSIT',
            'HOTEL_NIGHT_AUDIT_ROOM',
            'HOTEL_FOLIO_OUTLET_CHARGE',
            'HOTEL_CITY_LEDGER_TRANSFER',
            'HOTEL_DEPOSIT_REFUND',
            'HOTEL_CANCELLATION_PENALTY',
            'HOTEL_NO_SHOW_PENALTY',
            'HOTEL_OVERBOOKING_WALK',
            'HOTEL_FOLIO_SETTLEMENT',
        ];

        $transactions = LedgerTransaction::whereIn('type', $hotelTxTypes)->get();
        foreach ($transactions as $tx) {
            $sum = (int) LedgerEntry::where('transaction_id', $tx->id)->sum('amount');
            if ($sum !== 0) {
                $this->error("Transaksi ledger {$tx->id} ({$tx->type}) tidak seimbang! Selisih: {$sum}");
                $discrepancies++;
            }
        }

        // 2. Verifikasi Dua-Sumber: Baris Folio vs Ledger Entry
        $lines = HotelFolioLine::all();
        foreach ($lines as $line) {
            $tx = LedgerTransaction::where('reference_id', $line->id)->first();
            if ($tx) {
                $guestLedgerEntry = LedgerEntry::where('transaction_id', $tx->id)
                    ->whereHas('account', fn ($q) => $q->where('code', 'htl:guest_ledger:IDR'))
                    ->first();

                if ($guestLedgerEntry) {
                    $expectedEntryAmount = -$line->amount_idr;
                    if ((int) $guestLedgerEntry->amount !== $expectedEntryAmount) {
                        $this->error("Ketidakcocokan nominal baris folio {$line->id}: Folio={$line->amount_idr}, Ledger={$guestLedgerEntry->amount}");
                        $discrepancies++;
                    }
                }
            }
        }

        // 3. Verifikasi Saldo Outstanding Folio vs Jumlah Baris & Pembayaran
        $folios = HotelFolio::with('lines')->get();
        foreach ($folios as $folio) {
            if ($folio->lines->isNotEmpty()) {
                $calculatedCharges = (int) $folio->lines->sum('amount_idr');
                $expectedOutstanding = max(0, $calculatedCharges - (int) $folio->total_paid);
                if ((int) $folio->outstanding_balance !== $expectedOutstanding) {
                    $this->error("Ketidakcocokan saldo folio {$folio->id}: Header={$folio->outstanding_balance}, Expected={$expectedOutstanding}");
                    $discrepancies++;
                }
            }
        }

        // 4. Verifikasi Inventaris Kamar: Duplikasi malam pada kamar fisik
        $duplicates = HotelRoomNight::whereNotNull('room_id')
            ->select('room_id', 'night_date', DB::raw('count(*) as count'))
            ->groupBy('room_id', 'night_date')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $this->error("Duplikasi alokasi kamar fisik {$dup->room_id} pada tanggal {$dup->night_date} ({$dup->count} kali)!");
            $discrepancies++;
        }

        // 5. Cek kamar fisik out_of_order yang teralokasi sold
        $oooRooms = HotelRoom::where('status', 'out_of_order')->pluck('id');
        $soldOooNights = HotelRoomNight::whereIn('room_id', $oooRooms)
            ->where('status', 'sold')
            ->get();

        foreach ($soldOooNights as $night) {
            $this->error("Kamar out_of_order {$night->room_id} terjual pada tanggal {$night->night_date}!");
            $discrepancies++;
        }

        // 6. Cek orphan room nights yang reservasinya sudah dibatalkan atau no-show
        $cancelledIds = HotelReservation::whereIn('status', ['dibatalkan', 'dibatalkan_denda', 'cancelled', 'cancelled_with_penalty'])->pluck('id');
        $orphanNights = HotelRoomNight::whereIn('reservation_id', $cancelledIds)->get();

        $noShowReservations = HotelReservation::where('status', 'no_show')->get();
        foreach ($noShowReservations as $noShow) {
            $extraNights = HotelRoomNight::where('reservation_id', $noShow->id)
                ->whereDate('night_date', '>', $noShow->check_in_date)
                ->get();
            $orphanNights = $orphanNights->merge($extraNights);
        }

        foreach ($orphanNights as $night) {
            $this->error("Room night {$night->id} ({$night->night_date}) masih terdaftar untuk reservasi tidak aktif {$night->reservation_id}!");
            $discrepancies++;
        }

        if ($discrepancies > 0) {
            $this->error("Hotel audit FAILED with {$discrepancies} total discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Hotel audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}

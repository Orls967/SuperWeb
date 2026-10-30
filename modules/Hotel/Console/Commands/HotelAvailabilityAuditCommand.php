<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;

class HotelAvailabilityAuditCommand extends Command
{
    protected $signature = 'hotel:availability-audit';

    protected $description = 'Audit rekonsiliasi dua-sumber antara inventaris kamar per malam dan status reservasi';

    public function handle(): int
    {
        $this->info('Memulai Hotel Room Availability & Inventory Audit (hotel:availability-audit)...');

        $discrepancies = 0;

        // 1. Cek duplikasi malam pada kamar fisik yang sama (Double assignment)
        $duplicates = HotelRoomNight::whereNotNull('room_id')
            ->select('room_id', 'night_date', DB::raw('count(*) as count'))
            ->groupBy('room_id', 'night_date')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $this->error("Duplikasi alokasi kamar fisik {$dup->room_id} pada tanggal {$dup->night_date} ({$dup->count} kali)!");
            $discrepancies++;
        }

        // 2. Cek kamar fisik out_of_order yang teralokasi sold
        $oooRooms = HotelRoom::where('status', 'out_of_order')->pluck('id');
        $soldOooNights = HotelRoomNight::whereIn('room_id', $oooRooms)
            ->where('status', 'sold')
            ->get();

        foreach ($soldOooNights as $night) {
            $this->error("Kamar out_of_order {$night->room_id} terjual pada tanggal {$night->night_date}!");
            $discrepancies++;
        }

        // 3. Cek orphan room nights yang reservasinya sudah dibatalkan atau no-show
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

        $this->table(
            ['Pemeriksaan', 'Hasil'],
            [
                ['Total Room Nights Diperiksa', HotelRoomNight::count()],
                ['Duplikasi Kamar Fisik', $duplicates->count()],
                ['Kamar OOO Terjual', $soldOooNights->count()],
                ['Orphan Night Reservasi Batal', $orphanNights->count()],
                ['Diskrepansi Total', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Hotel availability audit GAGAL dengan {$discrepancies} diskrepansi terdeteksi!");

            return self::FAILURE;
        }

        $this->info('Hotel availability audit BERHASIL dengan 0 diskrepansi.');

        return self::SUCCESS;
    }
}

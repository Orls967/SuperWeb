<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;

class HotelFolioAuditCommand extends Command
{
    protected $signature = 'hotel:folio-audit';

    protected $description = 'Audit rekonsiliasi dua-sumber antara baris folio hotel dan mutasi Banking Ledger';

    public function handle(): int
    {
        $this->info('Memulai Hotel Folio & Ledger Reconciliation Audit (hotel:folio-audit)...');

        $discrepancies = 0;

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
                    // Nominal pada guest ledger didebit negatif (-amount_idr) untuk tagihan
                    $expectedEntryAmount = -$line->amount_idr;
                    if ((int) $guestLedgerEntry->amount !== $expectedEntryAmount) {
                        $this->error("Ketidakcocokan nominal baris folio {$line->id}: Folio={$line->amount_idr}, Ledger={$guestLedgerEntry->amount}");
                        $discrepancies++;
                    }
                }
            }
        }

        // 3. Verifikasi Saldo Outstanding Folio vs Jumlah Baris
        $folios = HotelFolio::with('lines')->get();
        foreach ($folios as $folio) {
            if ($folio->lines->isNotEmpty()) {
                $calculatedBalance = (int) $folio->lines->sum('amount_idr');
                // Outstanding balance harus konsisten dengan total baris
                if ((int) $folio->outstanding_balance !== $calculatedBalance) {
                    $this->error("Ketidakcocokan saldo folio {$folio->id}: Header={$folio->outstanding_balance}, SumLines={$calculatedBalance}");
                    $discrepancies++;
                }
            }
        }

        $this->table(
            ['Metrik Audit', 'Hasil'],
            [
                ['Transaksi Ledger Diperiksa', $transactions->count()],
                ['Baris Folio Diperiksa', $lines->count()],
                ['Folio Header Diperiksa', $folios->count()],
                ['Diskrepansi Terdeteksi', $discrepancies],
            ]
        );

        if ($discrepancies > 0) {
            $this->error("Hotel folio audit GAGAL dengan {$discrepancies} diskrepansi terdeteksi!");

            return self::FAILURE;
        }

        $this->info('Hotel folio audit BERHASIL dengan 0 diskrepansi.');

        return self::SUCCESS;
    }
}

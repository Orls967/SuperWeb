<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Algoritma V5: ArtistDoorSplitCalculator
 *
 * Menghitung penyelesaian kontrak artis bertaraf internasional/lokal
 * berbasis deal guarantee, door split, atau versus (yang lebih tinggi antara guarantee vs % gross pintu).
 */
final class ArtistDoorSplitCalculator
{
    public function calculate(ArtistDoorSplitInput $input): ArtistDoorSplitResult
    {
        if ($input->guaranteeIdr < 0 || $input->doorGrossIdr < 0 || $input->approvedExpensesIdr < 0) {
            throw new InvalidArgumentException('Nilai moneter artis tidak boleh negatif.');
        }

        if ($input->shareBps < 0 || $input->shareBps > 10_000) {
            throw new InvalidArgumentException('Share basis points harus berada di rentang 0..10000.');
        }

        if ($input->whtBps < 0 || $input->whtBps > 10_000) {
            throw new InvalidArgumentException('WHT basis points harus berada di rentang 0..10000.');
        }

        // 1. Hitung Net Pintu
        $doorNet = max(0, $input->doorGrossIdr - $input->approvedExpensesIdr);
        $doorShare = (int) intdiv($doorNet * $input->shareBps, 10_000);

        // 2. Gross Pay sesuai tipe kesepakatan
        $grossPayIdr = match ($input->dealType) {
            'guarantee' => $input->guaranteeIdr,
            'door_split' => $doorShare,
            'versus' => max($input->guaranteeIdr, $doorShare),
            default => throw new InvalidArgumentException("Tipe kesepakatan artis tidak dikenal: {$input->dealType}"),
        };

        // 3. Potongan Pajak Penghasilan WHT (PPh 21/26)
        $whtIdr = (int) intdiv($grossPayIdr * $input->whtBps, 10_000);

        // 4. Net Tunai yang harus ditransfer ke artis (dikurangi advance yang sudah dicairkan sebelumnya)
        $netPayableIdr = max(0, $grossPayIdr - $whtIdr - $input->advancePaidIdr);

        // 5. Konversi ke valuta asing minor cents (USD/SGD) bila kurs disediakan
        $valasMinorCents = 0;
        if ($input->exchangeRateIdr > 0 && strtoupper($input->currency) !== 'IDR') {
            $valasMinorCents = (int) floor(($netPayableIdr * 100) / $input->exchangeRateIdr);
        }

        // 6. Terbitkan Sertifikat Keaslian Komputasi
        $signature = ArtistDoorSplitVerifier::sign(
            dealType: $input->dealType,
            guarantee: $input->guaranteeIdr,
            doorGross: $input->doorGrossIdr,
            approvedExp: $input->approvedExpensesIdr,
            shareBps: $input->shareBps,
            whtBps: $input->whtBps,
            advancePaid: $input->advancePaidIdr,
            grossPay: $grossPayIdr,
            whtIdr: $whtIdr,
            netPayable: $netPayableIdr,
            valasMinorCents: $valasMinorCents
        );

        $certificate = new ArtistDoorSplitCertificate(
            dealType: $input->dealType,
            guaranteeIdr: $input->guaranteeIdr,
            doorGrossIdr: $input->doorGrossIdr,
            approvedExpensesIdr: $input->approvedExpensesIdr,
            shareBps: $input->shareBps,
            whtBps: $input->whtBps,
            advancePaidIdr: $input->advancePaidIdr,
            grossPayIdr: $grossPayIdr,
            whtIdr: $whtIdr,
            netPayableIdr: $netPayableIdr,
            valasMinorCents: $valasMinorCents,
            hmacSignature: $signature
        );

        return new ArtistDoorSplitResult(
            grossPayIdr: $grossPayIdr,
            whtIdr: $whtIdr,
            netPayableIdr: $netPayableIdr,
            valasMinorCents: $valasMinorCents,
            certificate: $certificate
        );
    }
}

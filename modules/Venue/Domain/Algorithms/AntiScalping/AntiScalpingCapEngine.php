<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\AntiScalping;

class AntiScalpingCapEngine
{
    public const MAX_PRICE_PERCENTAGE = 120;

    public const MAX_TRANSFERS = 1;

    public const MAX_ACTIVE_TICKETS = 4;

    public const MIN_VELOCITY_SECONDS = 1.0;

    public function evaluate(AntiScalpingInput $input): AntiScalpingResult
    {
        $maxAllowedPriceIdr = intdiv($input->originalFaceValueIdr * self::MAX_PRICE_PERCENTAGE, 100);

        if ($input->transferCount >= self::MAX_TRANSFERS) {
            return new AntiScalpingResult(
                isAllowed: false,
                effectivePriceIdr: 0,
                rejectionReason: 'Tiket telah dipindahtangankan maksimum 1 kali; transfer sekunder berulang dilarang (single transfer rule)',
            );
        }

        if ($input->askingPriceIdr > $maxAllowedPriceIdr) {
            return new AntiScalpingResult(
                isAllowed: false,
                effectivePriceIdr: 0,
                rejectionReason: sprintf(
                    'Harga penawaran Rp %s melebihi batas anti-scalping (maksimum 120%% dari harga asli Rp %s, plafon Rp %s)',
                    number_format($input->askingPriceIdr, 0, ',', '.'),
                    number_format($input->originalFaceValueIdr, 0, ',', '.'),
                    number_format($maxAllowedPriceIdr, 0, ',', '.')
                ),
            );
        }

        if ($input->userActiveTicketCount >= self::MAX_ACTIVE_TICKETS) {
            return new AntiScalpingResult(
                isAllowed: false,
                effectivePriceIdr: 0,
                rejectionReason: 'Batas kepemilikan maksimum 4 tiket aktif per NIK/identitas telah tercapai',
            );
        }

        if ($input->velocitySeconds < self::MIN_VELOCITY_SECONDS) {
            return new AntiScalpingResult(
                isAllowed: false,
                effectivePriceIdr: 0,
                rejectionReason: 'Aktivitas transaksi mencurigakan / bot velocity detected (< 1.0 detik)',
            );
        }

        $effectivePriceIdr = min($input->askingPriceIdr, $maxAllowedPriceIdr);

        $signature = AntiScalpingVerifier::sign(
            $input->originalFaceValueIdr,
            $input->askingPriceIdr,
            $effectivePriceIdr,
            $maxAllowedPriceIdr,
            $input->transferCount,
            $input->userActiveTicketCount
        );

        $cert = new AntiScalpingCertificate(
            originalFaceValueIdr: $input->originalFaceValueIdr,
            askingPriceIdr: $input->askingPriceIdr,
            effectivePriceIdr: $effectivePriceIdr,
            maxAllowedPriceIdr: $maxAllowedPriceIdr,
            transferCount: $input->transferCount,
            userActiveTicketCount: $input->userActiveTicketCount,
            hmacSignature: $signature
        );

        return new AntiScalpingResult(
            isAllowed: true,
            effectivePriceIdr: $effectivePriceIdr,
            rejectionReason: null,
            certificate: $cert
        );
    }
}

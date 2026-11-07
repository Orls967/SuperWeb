<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Exceptions;

final class SecondaryMarketException extends \RuntimeException
{
    public static function priceExceedsLegalCap(int $priceIdr, int $maxAllowedIdr): self
    {
        return new self('Harga penawaran (Rp '.number_format($priceIdr, 0, ',', '.').') melanggar batas legal anti-scalping (Maks: Rp '.number_format($maxAllowedIdr, 0, ',', '.').').');
    }

    public static function singleTransferViolation(int $ticketId): self
    {
        return new self("Tiket #{$ticketId} telah pernah dipindahtangankan di pasar sekunder dan tidak dapat dijual kembali.");
    }

    public static function sellerCannotBuyOwnListing(int $userId): self
    {
        return new self("Pengguna #{$userId} tidak diizinkan membeli tiket yang didaftarkannya sendiri di pasar sekunder.");
    }

    public static function waitlistOfferExpired(string $token): self
    {
        return new self("Batas waktu penawaran waitlist untuk token {$token} telah berakhir (kedaluwarsa 15 menit).");
    }

    public static function dynamicBundleFloorViolated(int $priceIdr, int $floorIdr): self
    {
        return new self("Harga paket bundle dinamis (Rp {$priceIdr}) melanggar batas floor price pelindung laba (Rp {$floorIdr}).");
    }

    public static function botScalpingDetected(int $userId, string $ip): self
    {
        return new self("Akses akun #{$userId} dari IP {$ip} diblokir sementara karena pola perilaku bot scraping tidak wajar.");
    }
}

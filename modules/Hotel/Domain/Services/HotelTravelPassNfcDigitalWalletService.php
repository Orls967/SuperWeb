<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlTravelPass;

final class HotelTravelPassNfcDigitalWalletService
{
    /**
     * @return array{
     *     pass_number: string,
     *     wallet_type: string,
     *     serial_number: string,
     *     nfc_payload: array{
     *         encryption_version: string,
     *         hce_token: string,
     *         terminal_auth_key: string
     *     },
     *     barcode: array{
     *         format: string,
     *         message: string,
     *         alt_text: string
     *     },
     *     colors: array{
     *         background: string,
     *         foreground: string,
     *         label: string
     *     },
     *     tier_display: string,
     *     points_display: string,
     *     generated_at: string
     * }
     */
    public function generateAppleWalletPassPayload(string $travelPassId): array
    {
        $pass = HtlTravelPass::findOrFail($travelPassId);

        $colors = match ($pass->tier) {
            'BLACK' => [
                'background' => '#0F172A',
                'foreground' => '#FDE047',
                'label' => '#94A3B8',
            ],
            'PLATINUM' => [
                'background' => '#3B0764',
                'foreground' => '#FAF5FF',
                'label' => '#C084FC',
            ],
            'GOLD' => [
                'background' => '#78350F',
                'foreground' => '#FEF3C7',
                'label' => '#FCD34D',
            ],
            default => [
                'background' => '#1E293B',
                'foreground' => '#F8FAFC',
                'label' => '#94A3B8',
            ],
        };

        $hceToken = hash_hmac('sha256', $pass->pass_number.now()->timestamp, 'SUPERWEB-NFC-HOTEL-KEY');

        return [
            'pass_number' => $pass->pass_number,
            'wallet_type' => 'APPLE_WALLET_PKPASS',
            'serial_number' => "SW-PKPASS-{$pass->id}",
            'nfc_payload' => [
                'encryption_version' => 'AES-GCM-256',
                'hce_token' => $hceToken,
                'terminal_auth_key' => 'SW-TERMINAL-VAL-HOTEL-2026',
            ],
            'barcode' => [
                'format' => 'PKBarcodeFormatPDF417',
                'message' => "sw://travel-pass/{$pass->pass_number}/hce/{$hceToken}",
                'alt_text' => $pass->pass_number,
            ],
            'colors' => $colors,
            'tier_display' => $pass->tier.' ELITE MEMBER',
            'points_display' => number_format($pass->pts_balance).' PTS',
            'generated_at' => now()->toIso8601String(),
        ];
    }
}

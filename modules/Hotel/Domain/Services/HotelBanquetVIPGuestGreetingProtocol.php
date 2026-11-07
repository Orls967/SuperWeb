<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelBanquetVIPGuestGreetingProtocol
{
    /**
     * Mempersiapkan protokol penyambutan tamu kehormatan kenegaraan atau pimpinan puncak korporat saat tiba di lobi banquet.
     *
     * @return array{contract_id: string, escort_officer_title: string, welcome_drink_variant: string, boutonniere_color: string, protocol_status: string}
     */
    public function prepareVipGreeting(string $contractId, string $vipTitle): array
    {
        $officer = in_array($vipTitle, ['PRESIDENT', 'MINISTER', 'AMBASSADOR'], true)
            ? 'General Manager & Chief of Protocol'
            : 'Director of Food & Beverage';

        return [
            'contract_id' => $contractId,
            'escort_officer_title' => $officer,
            'welcome_drink_variant' => 'Sparkling Lemongrass Pandan Infusion',
            'boutonniere_color' => 'Royal White Orchid',
            'protocol_status' => 'GREETING_READY',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Aviation\Integrations;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Application\Exceptions\SanctionedPassengerException;

class PartyPassengerVerificationService
{
    /**
     * Memverifikasi data identitas penumpang terhadap modul Party & daftar cekal (No-Fly List).
     *
     * @param int $partyId
     * @param string $passengerName
     * @return array{is_verified: bool, status: string, risk_score: int}
     * @throws SanctionedPassengerException
     */
    public function verifyPassenger(int $partyId, string $passengerName): array
    {
        $normalizedName = strtoupper(trim($passengerName));

        // Daftar nama cekal simulasi / regulasi keamanan penerbangan sipil
        $blacklistedNames = [
            'TERRORIST_SUSPECT',
            'UNRULY_PASSENGER_BANNED',
            'SANCTIONED_ENTITY',
        ];

        foreach ($blacklistedNames as $blacklisted) {
            if (str_contains($normalizedName, $blacklisted)) {
                throw new SanctionedPassengerException("Penumpang '{$passengerName}' tercantum dalam daftar larangan terbang nasional.");
            }
        }

        return [
            'is_verified' => true,
            'status' => 'CLEARED_FOR_TRAVEL',
            'risk_score' => 0,
        ];
    }
}


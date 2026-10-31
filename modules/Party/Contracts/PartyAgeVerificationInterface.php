<?php

declare(strict_types=1);

namespace Modules\Party\Contracts;

interface PartyAgeVerificationInterface
{
    /**
     * Mengembalikan usia terverifikasi dari pihak (Party/User) atau null bila belum diverifikasi KYC (V-6, Z9).
     */
    public function getVerifiedAge(int $partyOrUserId): ?int;
}

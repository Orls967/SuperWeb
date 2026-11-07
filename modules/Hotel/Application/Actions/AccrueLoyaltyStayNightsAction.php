<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Algorithms\LoyaltyNightTierEngine;
use Modules\Hotel\Domain\Algorithms\LoyaltyTierCertificate;
use Modules\Hotel\Domain\Algorithms\LoyaltyTierCertificateVerifier;
use RuntimeException;

/**
 * Tindakan Akumulasi Malam Menginap dan Perolehan Poin Loyalitas Tamu (Algoritma H7).
 */
final class AccrueLoyaltyStayNightsAction
{
    public function __construct(
        private readonly LoyaltyNightTierEngine $engine,
        private readonly LoyaltyTierCertificateVerifier $verifier,
    ) {}

    public function execute(
        string $guestId,
        int $priorNights,
        int $stayNights,
        int $eligibleSpendIdr,
        int $existingPointsBalance
    ): LoyaltyTierCertificate {
        $cert = $this->engine->evaluate(
            guestId: $guestId,
            priorNights: $priorNights,
            stayNights: $stayNights,
            eligibleSpendIdr: $eligibleSpendIdr,
            existingPointsBalance: $existingPointsBalance
        );

        if (! $this->verifier->verify($cert)) {
            throw new RuntimeException("Verifikasi sertifikat LoyaltyTier gagal untuk tamu {$guestId}");
        }

        return $cert;
    }
}

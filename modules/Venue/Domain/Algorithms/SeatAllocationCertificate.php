<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

use RuntimeException;

class SeatAllocationCertificate
{
    /**
     * @param  list<int>  $seatIds
     * @param  list<int>  $seatNumbers
     */
    public function __construct(
        public int $eventId,
        public int $tierId,
        public int $quantity,
        public string $sectionCode,
        public string $rowLabel,
        public array $seatIds,
        public array $seatNumbers,
    ) {}

    public function isValid(): bool
    {
        if (count($this->seatIds) !== $this->quantity) {
            return false;
        }

        if (count($this->seatNumbers) !== $this->quantity) {
            return false;
        }

        // Pastikan nomor kursi berurutan (kontigu)
        for ($i = 1; $i < count($this->seatNumbers); $i++) {
            if ($this->seatNumbers[$i] !== $this->seatNumbers[$i - 1] + 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Memvalidasi hasil alokasi terhadap sertifikat (PR2).
     *
     * @throws RuntimeException
     */
    public function verify(SeatAllocationResult $result): bool
    {
        $verifier = new SeatAllocationCertificateVerifier;

        return $verifier->verify($this, $result);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Nilai Objek Konteks Personalisasi Promosi VIP di Lokasi Venue.
 */
final readonly class PromoPersonalizationContext
{
    /**
     * @param  int  $userId  Identitas tamu VIP
     * @param  int  $zoneId  Zona lokasi fisik saat ini
     * @param  string  $tierCode  Tier keanggotaan
     * @param  int  $totalSpendTonightIdr  Akumulasi belanja malam ini
     * @param  int  $cooldownSecondsRemaining  Sisa durasi jeda penawaran (detik)
     * @param  bool  $isEligibleForDiscounts  Apakah berhak menerima potongan harga
     */
    public function __construct(
        public int $userId,
        public int $zoneId,
        public string $tierCode,
        public int $totalSpendTonightIdr,
        public int $cooldownSecondsRemaining,
        public bool $isEligibleForDiscounts
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('User ID harus bernilai positif integer.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'zone_id' => $this->zoneId,
            'tier_code' => $this->tierCode,
            'total_spend_idr' => $this->totalSpendTonightIdr,
            'cooldown_seconds_remaining' => $this->cooldownSecondsRemaining,
            'eligible' => $this->isEligibleForDiscounts,
        ];
    }
}

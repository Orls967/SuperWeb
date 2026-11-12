<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Events;

use JsonSerializable;

/**
 * Event resmi diterbitkan saat covenant rasio utang terhadap ekuitas (DER)
 * fasilitas kredit bank terdeteksi melampaui atau mendekati batas plafon
 * (EventCatalog: treasury.covenant.breached.v1).
 */
final class TreasuryCovenantBreachedEvent implements JsonSerializable
{
    public const EVENT_NAME = 'treasury.covenant.breached.v1';

    public function __construct(
        public readonly string $facilityId,
        public readonly string $facilityCode,
        public readonly string $bankName,
        public readonly int $covenantDerPermil,
        public readonly int $maxDerPermil,
        public readonly int $verifiedDebtIdr,
        public readonly int $verifiedEquityIdr,
        public readonly string $severity = 'BREACH',
        public readonly string $entityCode = 'HOLDING',
        public readonly ?int $actorId = null
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function jsonSerialize(): array
    {
        return [
            'event' => self::EVENT_NAME,
            'facility_id' => $this->facilityId,
            'facility_code' => $this->facilityCode,
            'bank_name' => $this->bankName,
            'covenant_der_permil' => $this->covenantDerPermil,
            'max_der_permil' => $this->maxDerPermil,
            'verified_debt_idr' => $this->verifiedDebtIdr,
            'verified_equity_idr' => $this->verifiedEquityIdr,
            'severity' => $this->severity,
            'entity_code' => $this->entityCode,
            'actor_id' => $this->actorId,
        ];
    }
}

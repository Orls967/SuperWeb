<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class EvacuationSignageDirective
{
    public function __construct(
        public readonly int $zoneId,
        public readonly string $displayColorHex,
        public readonly string $arrowDirection,
        public readonly string $headlineText,
        public readonly float $flashingRateHz,
        public readonly bool $turnstilesUnlocked,
        public readonly bool $soundAlarm
    ) {}

    public function toArray(): array
    {
        return [
            'zone_id' => $this->zoneId,
            'color' => $this->displayColorHex,
            'arrow' => $this->arrowDirection,
            'headline' => $this->headlineText,
            'flashing_rate_hz' => $this->flashingRateHz,
            'turnstiles_unlocked' => $this->turnstilesUnlocked,
            'sound_alarm' => $this->soundAlarm,
        ];
    }
}

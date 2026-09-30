<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum TruckType: string
{
    case CDE = 'cde'; // Colt Diesel Engkel (4 Roda)
    case CDD = 'cdd'; // Colt Diesel Double (6 Roda)
    case FUSO = 'fuso'; // Fuso Medium Heavy (6 Roda)
    case TRONTON = 'tronton'; // Heavy Duty Tronton (10 Roda)
    case TRACTOR_HEAD = 'tractor_head'; // Kepala Penarik Trailer (Container Chassis)
    case CAR_CARRIER = 'car_carrier'; // Pengangkut Mobil Antar-Pulau
    case REEFER = 'reefer'; // Truk Pendingin Cold Chain

    public function label(): string
    {
        return match ($this) {
            self::CDE => 'Colt Diesel Engkel (CDE - 4 Roda)',
            self::CDD => 'Colt Diesel Double (CDD - 6 Roda)',
            self::FUSO => 'Fuso Engkel Besar (6 Roda)',
            self::TRONTON => 'Tronton Wingbox/Bak (10 Roda)',
            self::TRACTOR_HEAD => 'Tractor Head (Prime Mover Trailer)',
            self::CAR_CARRIER => 'Car Carrier (Otomotif)',
            self::REEFER => 'Truk Reefer (Pendingin/Cold Chain)',
        };
    }

    public function requiredLicense(): string
    {
        return match ($this) {
            self::CDE, self::CDD, self::FUSO, self::REEFER => 'SIM B1 Umum',
            self::TRONTON, self::TRACTOR_HEAD, self::CAR_CARRIER => 'SIM B2 Umum',
        };
    }

    public function defaultPayloadKg(): int
    {
        return match ($this) {
            self::CDE => 2_500,
            self::CDD => 5_000,
            self::FUSO => 8_000,
            self::TRONTON => 18_000,
            self::TRACTOR_HEAD => 32_000,
            self::CAR_CARRIER => 14_000,
            self::REEFER => 4_500,
        };
    }

    public function defaultVolumeDm3(): int
    {
        return match ($this) {
            self::CDE => 9_000,
            self::CDD => 16_000,
            self::FUSO => 28_000,
            self::TRONTON => 48_000,
            self::TRACTOR_HEAD => 0, // Dihitung di trailer
            self::CAR_CARRIER => 60_000,
            self::REEFER => 14_000,
        };
    }
}

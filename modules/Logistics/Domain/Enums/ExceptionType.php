<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum ExceptionType: string
{
    case Missort = 'missort';
    case DeliveryFailed = 'delivery_failed';
    case Damaged = 'damaged';
    case AddressInvalid = 'address_invalid';
    case VehicleBreakdown = 'vehicle_breakdown';
    case WeatherDelay = 'weather_delay';
    case Late = 'late';
    case CustomsHold = 'customs_hold';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Missort => 'Salah Sortir (Missort)',
            self::DeliveryFailed => 'Pengantaran Gagal',
            self::Damaged => 'Kargo Rusak',
            self::AddressInvalid => 'Alamat Tidak Valid',
            self::VehicleBreakdown => 'Armada Mogok',
            self::WeatherDelay => 'Gangguan Cuaca',
            self::Late => 'Terlambat (SLA Terlampaui)',
            self::CustomsHold => 'Pemeriksaan Bea Cukai',
            self::Other => 'Lainnya',
        };
    }

    public function defaultSeverity(): ExceptionSeverity
    {
        return match ($this) {
            self::Damaged => ExceptionSeverity::Critical,
            self::Missort, self::VehicleBreakdown, self::Late => ExceptionSeverity::High,
            self::DeliveryFailed, self::AddressInvalid, self::CustomsHold => ExceptionSeverity::Medium,
            self::WeatherDelay, self::Other => ExceptionSeverity::Low,
        };
    }

    /** Tipe yang dilaporkan manual dan menghentikan resi (status Exception) sampai diselesaikan. */
    public function blocksShipment(): bool
    {
        return in_array($this, [self::Damaged, self::AddressInvalid, self::VehicleBreakdown], true);
    }

    /** @return array<int, self> tipe yang boleh dilaporkan manual oleh petugas. */
    public static function manual(): array
    {
        return [self::Damaged, self::AddressInvalid, self::VehicleBreakdown, self::WeatherDelay, self::Other];
    }
}

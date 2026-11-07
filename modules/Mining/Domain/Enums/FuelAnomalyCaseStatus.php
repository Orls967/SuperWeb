<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Enums;

enum FuelAnomalyCaseStatus: string
{
    case DETECTED = 'detected';
    case VERIFYING = 'verifying';
    case CONFIRMED = 'confirmed';
    case PAYMENT_HELD = 'payment_held';
    case DISPUTED = 'disputed';
    case RESOLVED_DEDUCTED = 'resolved_deducted';
    case RESOLVED_RELEASED = 'resolved_released';
    case WRITTEN_OFF = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::DETECTED => 'Terdeteksi Deviasi BBM',
            self::VERIFYING => 'Verifikasi Telematika',
            self::CONFIRMED => 'Anomali Dikonfirmasi',
            self::PAYMENT_HELD => 'Pembayaran Ditahan (Hold)',
            self::DISPUTED => 'Sanggahan Kontraktor',
            self::RESOLVED_DEDUCTED => 'Selesai: Dipotong Permanen',
            self::RESOLVED_RELEASED => 'Selesai: Dana Dirilis',
            self::WRITTEN_OFF => 'Dihapusbukukan (Write-off)',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::DETECTED => in_array($next, [self::VERIFYING, self::CONFIRMED, self::WRITTEN_OFF], true),
            self::VERIFYING => in_array($next, [self::CONFIRMED, self::RESOLVED_RELEASED, self::WRITTEN_OFF], true),
            self::CONFIRMED => in_array($next, [self::PAYMENT_HELD, self::WRITTEN_OFF], true),
            self::PAYMENT_HELD => in_array($next, [self::DISPUTED, self::RESOLVED_DEDUCTED, self::RESOLVED_RELEASED], true),
            self::DISPUTED => in_array($next, [self::RESOLVED_DEDUCTED, self::RESOLVED_RELEASED], true),
            self::RESOLVED_DEDUCTED, self::RESOLVED_RELEASED, self::WRITTEN_OFF => false,
        };
    }
}


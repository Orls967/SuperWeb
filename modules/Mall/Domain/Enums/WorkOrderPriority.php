<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum WorkOrderPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case EMERGENCY = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Rendah (Low)',
            self::MEDIUM => 'Sedang (Medium)',
            self::HIGH => 'Tinggi (High)',
            self::EMERGENCY => 'Darurat (Emergency)',
        };
    }

    /**
     * Target batas waktu penyelesaian SLA (dalam jam).
     */
    public function defaultSlaHours(): int
    {
        return match ($this) {
            self::LOW => 72,
            self::MEDIUM => 24,
            self::HIGH => 8,
            self::EMERGENCY => 2,
        };
    }
}

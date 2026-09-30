<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum WorkOrderType: string
{
    case PREVENTIVE = 'preventive';
    case CORRECTIVE = 'corrective';
    case EMERGENCY = 'emergency';
    case TENANT_REQUEST = 'tenant_request';

    public function label(): string
    {
        return match ($this) {
            self::PREVENTIVE => 'Pemeliharaan Berkala (Preventive)',
            self::CORRECTIVE => 'Perbaikan Kerusakan (Corrective)',
            self::EMERGENCY => 'Tanggap Darurat (Emergency)',
            self::TENANT_REQUEST => 'Permintaan Tenant (Tenant Request)',
        };
    }
}

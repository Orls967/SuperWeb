<?php

declare(strict_types=1);

namespace Modules\Logistics\Infrastructure\Tenancy;

use Modules\Core\Contracts\TenantScopedModelProvider;
use Modules\Logistics\Domain\Models\DockAppointment;

class LogisticsTenantScopedModelProvider implements TenantScopedModelProvider
{
    public function moduleName(): string
    {
        return 'Logistics';
    }

    public function scopedModels(): array
    {
        return [
            [
                'model' => DockAppointment::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
        ];
    }
}

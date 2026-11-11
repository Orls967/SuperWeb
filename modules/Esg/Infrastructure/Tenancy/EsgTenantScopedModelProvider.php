<?php

declare(strict_types=1);

namespace Modules\Esg\Infrastructure\Tenancy;

use Modules\Core\Contracts\TenantScopedModelProvider;
use Modules\Esg\Domain\Models\EsgEmission;

class EsgTenantScopedModelProvider implements TenantScopedModelProvider
{
    public function moduleName(): string
    {
        return 'Esg';
    }

    public function scopedModels(): array
    {
        return [
            [
                'model' => EsgEmission::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
        ];
    }
}

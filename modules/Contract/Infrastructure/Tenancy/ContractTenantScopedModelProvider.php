<?php

declare(strict_types=1);

namespace Modules\Contract\Infrastructure\Tenancy;

use Modules\Contract\Domain\Models\Contract;
use Modules\Core\Contracts\TenantScopedModelProvider;

class ContractTenantScopedModelProvider implements TenantScopedModelProvider
{
    public function moduleName(): string
    {
        return 'Contract';
    }

    public function scopedModels(): array
    {
        return [
            [
                'model' => Contract::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
        ];
    }
}


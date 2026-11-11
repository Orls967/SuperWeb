<?php

declare(strict_types=1);

namespace Modules\Mall\Infrastructure\Tenancy;

use Modules\Core\Contracts\TenantScopedModelProvider;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\Lease;

class MallTenantScopedModelProvider implements TenantScopedModelProvider
{
    public function moduleName(): string
    {
        return 'Mall';
    }

    public function scopedModels(): array
    {
        return [
            [
                'model' => Invoice::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
            [
                'model' => Lease::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
        ];
    }
}

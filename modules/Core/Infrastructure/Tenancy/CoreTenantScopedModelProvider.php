<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Tenancy;

use Modules\Core\Contracts\TenantScopedModelProvider;
use Modules\Core\Domain\Models\CoreApiClient;
use Modules\Core\Domain\Models\CoreWebhook;

class CoreTenantScopedModelProvider implements TenantScopedModelProvider
{
    public function moduleName(): string
    {
        return 'Core';
    }

    public function scopedModels(): array
    {
        return [
            [
                'model' => CoreWebhook::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
            [
                'model' => CoreApiClient::class,
                'column' => 'tenant_id',
                'kind' => 'tenant',
            ],
        ];
    }
}

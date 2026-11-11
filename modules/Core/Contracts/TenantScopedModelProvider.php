<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for owner modules to register models with existing physical tenant/entity/party columns
 * into TenantScopeRegistry without modifying business model source files (W8-D51).
 */
interface TenantScopedModelProvider
{
    public function moduleName(): string;

    /**
     * List of model descriptors owned by this module.
     *
     * @return list<array{model: class-string<Model>, column: string, kind: string}>
     */
    public function scopedModels(): array;
}


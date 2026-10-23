<?php

declare(strict_types=1);

namespace Modules\Supplier\Contracts;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cross-module contract for Supplier model.
 */
class Supplier extends Model
{
    use HasUuids;

    protected $table = 'sup_suppliers';

    public function scorecards(): HasMany
    {
        return $this->hasMany(SupplierScorecard::class, 'supplier_id')->orderByDesc('period');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Gudang/pusat distribusi (41.1). */
class Warehouse extends Model
{
    use HasUuids;

    protected $table = 'wms_warehouses';

    protected $fillable = ['code', 'name', 'kind', 'address', 'city', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class, 'warehouse_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rak di dalam zona. */
class Rack extends Model
{
    protected $table = 'wms_racks';

    protected $fillable = ['zone_id', 'code', 'name'];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class, 'rack_id');
    }
}

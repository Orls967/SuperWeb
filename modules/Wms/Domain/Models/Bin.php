<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bin: unit penyimpanan terkecil; pick-face menentukan slot order-pick. */
class Bin extends Model
{
    protected $table = 'wms_bins';

    protected $fillable = ['rack_id', 'code', 'capacity_units', 'is_pick_face', 'is_active'];

    protected $casts = ['capacity_units' => 'integer', 'is_pick_face' => 'boolean', 'is_active' => 'boolean'];

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(BinStock::class, 'bin_id');
    }

    public function capacityReached(int $incoming): bool
    {
        if ($this->capacity_units === 0) {
            return false;
        }

        return $this->stocks()->sum('qty') + $incoming > $this->capacity_units;
    }
}

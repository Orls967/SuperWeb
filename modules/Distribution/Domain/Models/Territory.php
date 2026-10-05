<?php

declare(strict_types=1);

namespace Modules\Distribution\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Peta wilayah: provinsi → kota → kecamatan. */
class Territory extends Model
{
    protected $table = 'dist_territories';

    protected $fillable = ['parent_id', 'code', 'name', 'level'];

    protected $casts = ['parent_id' => 'integer'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(TerritoryCoverage::class, 'territory_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shared\Domain\Traits\HasUuid;

class Zone extends Model
{
    use HasUuid;

    protected $table = 'mall_zones';

    protected $fillable = [
        'uuid',
        'property_id',
        'name',
        'floor',
        'color_code',
        'description',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'zone_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Shared\Domain\Traits\HasUuid;

class CateringPackage extends Model
{
    use HasUuid;

    protected $table = 'resto_catering_packages';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'price_per_pax',
        'min_pax',
        'items',
        'is_active',
    ];

    protected $casts = [
        'price_per_pax' => 'integer',
        'min_pax' => 'integer',
        'items' => 'array',
        'is_active' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(CateringOrder::class, 'package_id');
    }
}

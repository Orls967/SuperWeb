<?php

namespace Modules\Agri\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgriFarmer extends Model
{
    use HasUuids;

    protected $table = 'agri_farmers';

    protected $fillable = [
        'farmer_code',
        'farmer_group_name',
        'full_name',
        'phone_number',
        'land_polygon_geojson',
        'land_area_hectares',
        'primary_commodity',
        'status',
    ];

    protected $casts = [
        'land_area_hectares' => 'decimal:2',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(AgriContract::class, 'farmer_id');
    }
}

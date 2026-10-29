<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HotelProperty extends Model
{
    protected $table = 'htl_properties';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'property_code',
        'name',
        'property_type',
        'city',
        'total_rooms',
        'tax_pbjt_bps',
        'service_charge_bps',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_rooms' => 'integer',
            'tax_pbjt_bps' => 'integer',
            'service_charge_bps' => 'integer',
        ];
    }

    public function businessDate(): HasOne
    {
        return $this->hasOne(HotelBusinessDate::class, 'property_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HotelRoom::class, 'property_id');
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class, 'property_id');
    }

    public function ratePlans(): HasMany
    {
        return $this->hasMany(HotelRatePlan::class, 'property_id');
    }
}

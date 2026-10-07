<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MiceContract extends Model
{
    protected $table = 'htl_mice_contracts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'event_code',
        'property_id',
        'event_type',
        'client_name',
        'event_date',
        'contract_total_value_idr',
        'actual_banquet_cost_idr',
        'current_milestone_index',
        'status',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }

    public function roomBlock(): HasOne
    {
        return $this->hasOne(RoomBlockAllotment::class, 'mice_contract_id');
    }

    public function banquetProductionSheet(): HasOne
    {
        return $this->hasOne(BanquetProductionSheet::class, 'mice_contract_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MiceContract extends Model
{
    protected $table = 'htl_mice_contracts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['signature_hash'];

    protected $casts = [
        'is_bridal_suite_complimentary' => 'boolean',
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

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(HtlEventProposal::class, 'proposal_id');
    }

    public function weddingMilestones(): HasMany
    {
        return $this->hasMany(HtlWeddingMilestoneSchedule::class, 'mice_contract_id')->orderBy('milestone_index');
    }

    public function exhibitionBooths(): HasMany
    {
        return $this->hasMany(HtlExhibitionBoothReservation::class, 'mice_contract_id');
    }

    public function banquetOrders(): HasMany
    {
        return $this->hasMany(HtlBanquetOrder::class, 'mice_contract_id');
    }
}

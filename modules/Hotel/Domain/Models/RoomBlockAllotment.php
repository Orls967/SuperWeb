<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBlockAllotment extends Model
{
    protected $table = 'htl_room_block_allotments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'block_code',
        'mice_contract_id',
        'check_in_date',
        'release_deadline_date',
        'allotted_rooms_count',
        'confirmed_rooms_count',
        'released_rooms_count',
        'status',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MiceContract::class, 'mice_contract_id');
    }
}

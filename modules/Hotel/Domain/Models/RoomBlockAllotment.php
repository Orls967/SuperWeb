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

    protected $guarded = ['signature_hash'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MiceContract::class, 'mice_contract_id');
    }
}

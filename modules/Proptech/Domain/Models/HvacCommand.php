<?php

namespace Modules\Proptech\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HvacCommand extends Model
{
    protected $table = 'prp_hvac_commands';

    protected $guarded = [];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(BuildingZone::class, 'zone_id');
    }
}

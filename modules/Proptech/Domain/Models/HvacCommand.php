<?php

declare(strict_types=1);

namespace Modules\Proptech\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HvacCommand extends Model
{
    protected $table = 'prp_hvac_commands';

    protected $fillable = [
        'zone_id',
        'command_type',
        'target_value',
        'trigger_reason',
        'estimated_kwh_saved',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(BuildingZone::class, 'zone_id');
    }
}

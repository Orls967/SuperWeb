<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationCargoTransition extends Model
{
    public $timestamps = false;

    protected $table = 'avi_cargo_transitions';

    protected $fillable = [
        'consignment_id',
        'from_status',
        'to_status',
        'trigger',
        'actor_id',
        'notes',
        'created_at',
    ];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(AviationCargoConsignment::class, 'consignment_id');
    }
}

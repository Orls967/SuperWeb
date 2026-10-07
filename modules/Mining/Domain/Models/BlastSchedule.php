<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlastSchedule extends Model
{
    protected $table = 'min_blast_schedules';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'blast_code',
        'pit_id',
        'scheduled_at',
        'blast_coord_lat',
        'blast_coord_lng',
        'safety_radius_meters',
        'holes_count',
        'explosives_kg',
        'oversize_fragmentation_percent',
        'status',
    ];

    public function pit(): BelongsTo
    {
        return $this->belongsTo(MiningPit::class, 'pit_id');
    }
}

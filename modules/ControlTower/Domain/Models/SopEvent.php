<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SopEvent extends Model
{
    public $timestamps = false;

    protected $table = 'sct_sop_events';

    protected $fillable = [
        'sop_cycle_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SopCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SopCycle::class, 'sop_cycle_id');
    }
}

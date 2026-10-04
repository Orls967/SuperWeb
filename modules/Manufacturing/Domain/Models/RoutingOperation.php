<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu operasi pada routing: work center, setup/run, instruksi, titik inspeksi. */
class RoutingOperation extends Model
{
    protected $table = 'mfg_routing_operations';

    protected $fillable = [
        'routing_id', 'work_center_id', 'sequence', 'name', 'setup_minutes',
        'run_minutes_per_unit', 'work_instructions', 'inspection_point',
    ];

    protected $casts = [
        'sequence' => 'integer', 'setup_minutes' => 'integer',
        'run_minutes_per_unit' => 'integer', 'inspection_point' => 'boolean',
    ];

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    /** Menit total untuk kuantitas tertentu. */
    public function minutesFor(int $qty): int
    {
        return $this->setup_minutes + ($this->run_minutes_per_unit * max(1, $qty));
    }
}

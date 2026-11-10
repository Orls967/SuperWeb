<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model CoreSpineGap
 *
 * Mencatat celah nomor urut pada aggregate stream.
 *
 * @property int $id
 * @property string $aggregate_type
 * @property string $aggregate_id
 * @property int $gap_start
 * @property int $gap_end
 * @property string $status
 * @property string $detected_at
 * @property string|null $resolved_at
 */
class CoreSpineGap extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_IGNORED = 'ignored';

    protected $table = 'core_spine_gaps';

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'gap_start',
        'gap_end',
        'status',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'gap_start' => 'integer',
        'gap_end' => 'integer',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}

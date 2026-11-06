<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Rencana Pemulihan Black-Start Microgrid.
 *
 * @property int $id
 * @property string $plan_code
 * @property int $microgrid_id
 * @property string $status
 * @property int $total_components_count
 * @property int $total_estimated_seconds
 * @property array $step_sequence_json
 * @property array|null $execution_log_json
 * @property string $certificate_hash
 */
final class BlackStartPlan extends Model
{
    protected $table = 'egy_black_start_plans';

    protected $fillable = [
        'plan_code',
        'microgrid_id',
        'status',
        'total_components_count',
        'total_estimated_seconds',
        'step_sequence_json',
        'execution_log_json',
        'certificate_hash',
    ];

    protected $casts = [
        'microgrid_id' => 'integer',
        'total_components_count' => 'integer',
        'total_estimated_seconds' => 'integer',
        'step_sequence_json' => 'array',
        'execution_log_json' => 'array',
    ];

    /**
     * @return BelongsTo<Microgrid, $this>
     */
    public function microgrid(): BelongsTo
    {
        return $this->belongsTo(Microgrid::class, 'microgrid_id');
    }
}

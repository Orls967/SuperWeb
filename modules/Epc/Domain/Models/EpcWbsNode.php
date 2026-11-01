<?php

declare(strict_types=1);

namespace Modules\Epc\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class EpcWbsNode extends Model
{
    use HasUuids;

    protected $table = 'epc_wbs_nodes';

    protected $fillable = [
        'project_id',
        'wbs_code',
        'task_name',
        'work_package',
        'weight_percentage',
        'budget_allocation_idr',
        'actual_cost_incurred_idr',
        'completion_percentage',
        'status',
    ];

    protected $casts = [
        'weight_percentage' => 'decimal:2',
        'budget_allocation_idr' => 'integer',
        'actual_cost_incurred_idr' => 'integer',
        'completion_percentage' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (EpcWbsNode $node): void {
            if ($node->completion_percentage !== null) {
                $val = (float) $node->completion_percentage;
                if ($val < 0.0 || $val > 100.0) {
                    throw new InvalidArgumentException("WBS node completion percentage must be between 0 and 100, got: {$val}");
                }
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(EpcProject::class, 'project_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Plm\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabNotebook extends Model
{
    use HasUuids;

    protected $table = 'plm_lab_notebooks';

    protected $fillable = [
        'project_id',
        'experiment_code',
        'title',
        'formula_payload_encrypted',
        'stability_test_result',
        'sensory_score',
    ];

    protected $casts = [
        'sensory_score' => 'decimal:1',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PlmProject::class, 'project_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\ProServices\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProServicesRevenueRecognition extends Model
{
    protected $table = 'psv_revenue_recognitions';

    protected $fillable = [
        'recognition_code',
        'project_id',
        'cumulative_cost_idr',
        'total_estimated_cost_idr',
        'cumulative_recognized_idr',
        'period_recognized_idr',
        'certificate_sha256',
        'idempotency_key',
    ];

    protected $casts = [
        'project_id' => 'integer',
        'cumulative_cost_idr' => 'integer',
        'total_estimated_cost_idr' => 'integer',
        'cumulative_recognized_idr' => 'integer',
        'period_recognized_idr' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProServicesProject::class, 'project_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Proptech\Domain\Models\Bim;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwinIssue extends Model
{
    protected $table = 'prp_twin_issues';

    protected $fillable = [
        'issue_code',
        'component_id',
        'issue_type',
        'severity',
        'description',
        'facility_work_order_id',
        'status',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(TwinComponent::class, 'component_id');
    }
}

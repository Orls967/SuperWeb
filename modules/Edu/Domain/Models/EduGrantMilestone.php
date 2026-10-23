<?php

declare(strict_types=1);

namespace Modules\Edu\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduGrantMilestone extends Model
{
    protected $table = 'edu_grant_milestones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'milestone_code',
        'grant_uuid',
        'milestone_title',
        'disbursement_amount_minor',
        'disbursed_at',
        'status',
    ];

    protected $casts = [
        'disbursement_amount_minor' => 'integer',
        'disbursed_at' => 'datetime',
    ];

    public function grant(): BelongsTo
    {
        return $this->belongsTo(EduResearchGrant::class, 'grant_uuid', 'id');
    }
}

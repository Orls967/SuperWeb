<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SybDpsComplianceReview extends Model
{
    protected $table = 'syb_dps_compliance_reviews';

    protected $fillable = [
        'review_ref',
        'period_label',
        'review_type',
        'reviewer_name',
        'verdict',
        'findings',
        'recommendations',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }
}

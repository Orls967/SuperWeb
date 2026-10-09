<?php

declare(strict_types=1);

namespace Modules\International\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JointVenture extends Model
{
    protected $table = 'intl_joint_ventures';

    protected $guarded = [];

    protected $casts = [
        'local_share_percent' => 'float',
        'foreign_share_percent' => 'float',
        'total_committed_capital_idr' => 'integer',
        'paid_in_capital_idr' => 'integer',
        'minority_veto_rights' => 'boolean',
    ];

    public function foreignEntity(): BelongsTo
    {
        return $this->belongsTo(ForeignEntity::class, 'foreign_entity_id');
    }
}

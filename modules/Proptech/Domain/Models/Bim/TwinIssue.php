<?php

namespace Modules\Proptech\Domain\Models\Bim;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwinIssue extends Model
{
    protected $table = 'prp_twin_issues';

    protected $guarded = [];

    public function component(): BelongsTo
    {
        return $this->belongsTo(TwinComponent::class, 'component_id');
    }
}

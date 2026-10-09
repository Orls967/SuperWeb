<?php

declare(strict_types=1);

namespace Modules\Partner\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DueDiligence extends Model
{
    use HasUuids;

    protected $table = 'ptn_due_diligences';

    protected $fillable = [
        'partner_id', 'score', 'checklist', 'status', 'approval_id', 'notes',
    ];

    protected $casts = [
        'score' => 'integer',
        'checklist' => 'array',
        'approval_id' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}

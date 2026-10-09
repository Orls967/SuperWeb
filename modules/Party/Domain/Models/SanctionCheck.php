<?php

declare(strict_types=1);

namespace Modules\Party\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Party\Domain\Enums\SanctionCheckStatus;

class SanctionCheck extends Model
{
    use HasUuids;

    protected $table = 'pty_sanctions_checks';

    protected $fillable = [
        'party_id', 'trigger', 'status', 'hits', 'match_score',
        'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'status' => SanctionCheckStatus::class,
        'hits' => 'array',
        'match_score' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function isClear(): bool
    {
        return $this->status === SanctionCheckStatus::Clear;
    }
}

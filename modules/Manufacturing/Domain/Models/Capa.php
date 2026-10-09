<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** CAPA: tindakan korektif/preventif dengan tenggat & efektivitas. */
class Capa extends Model
{
    use HasUuids;

    protected $table = 'mfg_capas';

    protected $fillable = [
        'ncr_id', 'kind', 'action', 'due_date', 'effectiveness', 'status',
        'verification_note', 'completed_at',
    ];

    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(Ncr::class, 'ncr_id');
    }

    /** Tandai overdue bila lewat tenggat & belum done/verified. */
    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_date !== null && $this->due_date->isPast();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybIstisnaMilestone extends Model
{
    protected $table = 'syb_istisna_milestones';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'istisna_id',
        'milestone_name',
        'cost_incurred_cents',
        'progress_percentage',
        'inspection_note',
        'verified_at',
        'verifier_ref',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'cost_incurred_cents' => 'integer',
        'progress_percentage' => 'integer',
        'verified_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<SybIstisnaContract, $this>
     */
    public function istisnaContract(): BelongsTo
    {
        return $this->belongsTo(SybIstisnaContract::class, 'istisna_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Insurance\Domain\Enums\BordereauStatus;
use Modules\Party\Contracts\Party;

class ReinsuranceBordereau extends Model
{
    protected $table = 'ins_rein_bordereaux';

    /** @var list<string> */
    protected $fillable = [
        'number',
        'treaty_id',
        'reinsurer_party_id',
        'kind',
        'period_ym',
        'status',
        'line_count',
        'total_ceded_idr',
        'total_commission_idr',
        'total_reinstatement_idr',
        'reverses_id',
        'sent_at',
        'acknowledged_at',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => BordereauStatus::class,
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /** @return HasMany<ReinsuranceBordereauLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ReinsuranceBordereauLine::class, 'bordereau_id');
    }

    /** @return BelongsTo<ReinsuranceTreaty, $this> */
    public function treaty(): BelongsTo
    {
        return $this->belongsTo(ReinsuranceTreaty::class, 'treaty_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function reinsurer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'reinsurer_party_id');
    }
}

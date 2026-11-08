<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class HtlEventProposal extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'htl_event_proposals';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['signature_hash'];

    protected $casts = [
        'timelock_expires_at' => 'datetime',
        'gross_margin_percent' => 'float',
    ];

    /**
     * @return BelongsTo<HtlMiceLead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(HtlMiceLead::class, 'lead_id');
    }

    /**
     * @return HasOne<MiceContract, $this>
     */
    public function contract(): HasOne
    {
        return $this->hasOne(MiceContract::class, 'proposal_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TakafulParticipant extends Model
{
    protected $table = 'ins_tk_participants';

    protected $fillable = [
        'participant_party_id',
        'policy_number',
        'fund_id',
        'plan_type',
        'contribution_annual_idr',
        'tabarru_portion_bps',
        'ujrah_portion_bps',
        'sum_covered_idr',
        'status',
        'effective_date',
        'expiry_date',
    ];

    protected $casts = [
        'contribution_annual_idr' => 'integer',
        'tabarru_portion_bps' => 'integer',
        'ujrah_portion_bps' => 'integer',
        'sum_covered_idr' => 'integer',
        'effective_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(TakafulFund::class, 'fund_id');
    }
}

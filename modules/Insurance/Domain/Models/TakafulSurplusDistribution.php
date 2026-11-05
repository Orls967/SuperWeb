<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TakafulSurplusDistribution extends Model
{
    protected $table = 'ins_tk_surplus_distributions';

    protected $fillable = [
        'fund_id',
        'fiscal_year',
        'net_contributions_idr',
        'net_claims_idr',
        'technical_reserve_idr',
        'gross_surplus_idr',
        'participant_share_idr',
        'contingency_reserve_idr',
        'mudharabah_operator_idr',
        'calculation_certificate',
        'distributed_at',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'net_contributions_idr' => 'integer',
        'net_claims_idr' => 'integer',
        'technical_reserve_idr' => 'integer',
        'gross_surplus_idr' => 'integer',
        'participant_share_idr' => 'integer',
        'contingency_reserve_idr' => 'integer',
        'mudharabah_operator_idr' => 'integer',
        'distributed_at' => 'datetime',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(TakafulFund::class, 'fund_id');
    }
}

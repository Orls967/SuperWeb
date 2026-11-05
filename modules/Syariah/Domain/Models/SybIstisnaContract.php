<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SybIstisnaContract extends Model
{
    protected $table = 'syb_istisna_contracts';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'lc_id',
        'contract_number',
        'manufacturer_party_ref',
        'specification_summary',
        'total_price_cents',
        'budgeted_cost_cents',
        'actual_cost_incurred_cents',
        'recognized_revenue_cents',
        'progress_percent',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'total_price_cents' => 'integer',
        'budgeted_cost_cents' => 'integer',
        'actual_cost_incurred_cents' => 'integer',
        'recognized_revenue_cents' => 'integer',
        'progress_percent' => 'integer',
    ];

    /**
     * @return BelongsTo<SybLetterOfCredit, $this>
     */
    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(SybLetterOfCredit::class, 'lc_id');
    }

    /**
     * @return HasMany<SybIstisnaMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(SybIstisnaMilestone::class, 'istisna_id');
    }
}

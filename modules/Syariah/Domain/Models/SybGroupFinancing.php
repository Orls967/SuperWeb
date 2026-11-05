<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SybGroupFinancing extends Model
{
    protected $table = 'syb_group_financings';

    protected $fillable = [
        'group_code',
        'cooperative_code',
        'group_name',
        'leader_party_ref',
        'total_members',
        'total_facility_amount_idr',
        'outstanding_balance_idr',
        'shariah_contract',
        'profit_nisbah_shohibul_maal_bps',
        'group_liability_active',
        'status',
    ];

    protected $casts = [
        'total_members' => 'integer',
        'total_facility_amount_idr' => 'integer',
        'outstanding_balance_idr' => 'integer',
        'profit_nisbah_shohibul_maal_bps' => 'integer',
        'group_liability_active' => 'boolean',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(SybBmtCooperative::class, 'cooperative_code', 'cooperative_code');
    }

    public function members(): HasMany
    {
        return $this->hasMany(SybGroupMember::class, 'group_code', 'group_code');
    }
}

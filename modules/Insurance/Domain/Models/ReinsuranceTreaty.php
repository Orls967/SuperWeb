<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Insurance\Domain\Enums\TreatyStatus;
use Modules\Insurance\Domain\Enums\TreatyType;

class ReinsuranceTreaty extends Model
{
    protected $table = 'ins_rein_treaties';

    /** @var list<string> */
    protected $fillable = [
        'treaty_code',
        'type',
        'name',
        'line_category',
        'status',
        'inception_date',
        'expiry_date',
        'quota_share_bps',
        'surplus_retention_idr',
        'surplus_lines',
        'attachment_idr',
        'limit_idr',
        'reinstatement_rates_bps',
        'layer_premium_idr',
        'commission_bps',
        'sl_attachment_lr_bps',
        'sl_limit_lr_bps',
        'pending_status',
        'approval_ref',
        'premium_booked_idr',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => TreatyType::class,
            'status' => TreatyStatus::class,
            'pending_status' => TreatyStatus::class,
            'inception_date' => 'date',
            'expiry_date' => 'date',
            'reinstatement_rates_bps' => 'array',
        ];
    }

    /** @return HasMany<ReinsuranceTreatyPanel, $this> */
    public function panel(): HasMany
    {
        return $this->hasMany(ReinsuranceTreatyPanel::class, 'treaty_id')->orderBy('id');
    }

    /** @return HasMany<ReinsuranceTreatyHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(ReinsuranceTreatyHistory::class, 'treaty_id')->orderBy('id');
    }
}

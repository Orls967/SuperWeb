<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SybScfFacility extends Model
{
    protected $table = 'syb_scf_facilities';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'facility_number',
        'buyer_party_ref',
        'supplier_party_ref',
        'limit_amount_cents',
        'utilized_amount_cents',
        'margin_rate_bps',
        'max_tenor_days',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'limit_amount_cents' => 'integer',
        'utilized_amount_cents' => 'integer',
        'margin_rate_bps' => 'integer',
        'max_tenor_days' => 'integer',
    ];

    /**
     * @return HasMany<SybScfInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SybScfInvoice::class, 'facility_id');
    }
}

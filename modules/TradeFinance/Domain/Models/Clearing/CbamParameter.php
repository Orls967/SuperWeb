<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Models\Clearing;

use Illuminate\Database\Eloquent\Model;

/** Parameter CBAM berversi — S2 (belum dikonfirmasi pemilik untuk produksi). */
class CbamParameter extends Model
{
    protected $table = 'tf_cbam_parameters';

    protected $fillable = [
        'version', 'price_eur_cents_per_tco2e', 'eur_to_idr_rate', 'label', 'source_label', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'price_eur_cents_per_tco2e' => 'integer',
        'eur_to_idr_rate' => 'integer',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];
}

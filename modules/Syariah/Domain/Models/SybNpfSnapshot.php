<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SybNpfSnapshot extends Model
{
    protected $table = 'syb_npf_snapshots';

    protected $fillable = [
        'classification_ref',
        'contract_ref',
        'customer_ref',
        'product_type',
        'dpd',
        'collectibility',
        'collectibility_name',
        'outstanding_principal_minor',
        'remaining_margin_minor',
        'provision_rate_bps',
        'provision_amount_minor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'dpd' => 'integer',
            'collectibility' => 'integer',
            'outstanding_principal_minor' => 'integer',
            'remaining_margin_minor' => 'integer',
            'provision_rate_bps' => 'integer',
            'provision_amount_minor' => 'integer',
        ];
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(SybNpfClassification::class, 'classification_ref', 'classification_ref');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EPharmacyOrderItem extends Model
{
    protected $table = 'hsp_epharmacy_order_items';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_idr' => 'integer',
            'subtotal_idr' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EPharmacyOrder::class, 'order_id');
    }
}

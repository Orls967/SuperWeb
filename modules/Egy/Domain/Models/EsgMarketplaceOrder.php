<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EsgMarketplaceOrder extends Model
{
    use HasFactory;

    protected $table = 'egy_esg_marketplace_orders';

    protected $fillable = [
        'order_number',
        'listing_id',
        'buyer_party_id',
        'purchased_quantity',
        'unit_price_minor',
        'total_amount_idr',
        'ledger_transaction_id',
        'redemption_code',
        'claim_status',
        'certificate_hash',
    ];

    protected $casts = [
        'listing_id' => 'integer',
        'buyer_party_id' => 'integer',
        'purchased_quantity' => 'integer',
        'unit_price_minor' => 'integer',
        'total_amount_idr' => 'integer',
        'ledger_transaction_id' => 'integer',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(EsgMarketplaceListing::class, 'listing_id');
    }
}

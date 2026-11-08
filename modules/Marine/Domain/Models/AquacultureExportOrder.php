<?php

declare(strict_types=1);

namespace Modules\Marine\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AquacultureExportOrder extends Model
{
    protected $table = 'mar_export_orders';

    protected $fillable = [
        'order_number',
        'buyer_party_id',
        'destination_country_code',
        'destination_port_unlocode',
        'incoterm',
        'total_ordered_weight_kg',
        'total_contract_value_minor_cents',
        'payment_instrument',
        'letter_of_credit_number',
        'issuing_bank_swift',
        'status',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_party_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(ExportReeferShipment::class, 'export_order_id');
    }

    public function escrowSettlements(): HasMany
    {
        return $this->hasMany(AquacultureEscrowSettlement::class, 'export_order_id');
    }
}

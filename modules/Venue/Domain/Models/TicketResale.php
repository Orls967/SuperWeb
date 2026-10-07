<?php

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketResale extends Model
{
    protected $table = 'ven_ticket_resales';

    protected $fillable = [
        'resale_code',
        'ticket_id',
        'seller_user_id',
        'buyer_user_id',
        'original_face_value_idr',
        'resale_price_idr',
        'platform_fee_idr',
        'entertainment_tax_idr',
        'transfer_count',
        'new_ticket_hash',
        'status',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTicket::class, 'ticket_id');
    }
}

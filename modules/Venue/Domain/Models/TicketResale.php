<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use App\Models\User;
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
        'promoter_royalty_idr',
        'seller_payout_idr',
        'tax_pbjt_idr',
        'transfer_count',
        'new_ticket_hash',
        'status',
        'escrow_held_at',
        'settled_at',
    ];

    protected $casts = [
        'original_face_value_idr' => 'integer',
        'resale_price_idr' => 'integer',
        'platform_fee_idr' => 'integer',
        'entertainment_tax_idr' => 'integer',
        'promoter_royalty_idr' => 'integer',
        'seller_payout_idr' => 'integer',
        'tax_pbjt_idr' => 'integer',
        'transfer_count' => 'integer',
        'escrow_held_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(EntertainmentTicket::class, 'ticket_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function getFaceValueIdrAttribute(): int
    {
        return (int) ($this->original_face_value_idr ?? 0);
    }

    public function setFaceValueIdrAttribute(int $value): void
    {
        $this->attributes['original_face_value_idr'] = $value;
    }

    public function getPbjtTaxIdrAttribute(): int
    {
        return (int) ($this->tax_pbjt_idr ?: $this->entertainment_tax_idr ?: 0);
    }

    public function setPbjtTaxIdrAttribute(int $value): void
    {
        $this->attributes['tax_pbjt_idr'] = $value;
        $this->attributes['entertainment_tax_idr'] = $value;
    }

    public function transitionTo(string $newStatus): void
    {
        $this->status = $newStatus;
    }
}

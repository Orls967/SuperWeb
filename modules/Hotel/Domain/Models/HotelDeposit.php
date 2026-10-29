<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelDeposit extends Model
{
    use HasFactory;

    protected $table = 'htl_deposits';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'property_id',
        'reservation_id',
        'guest_user_id',
        'amount_idr',
        'applied_amount_idr',
        'refunded_amount_idr',
        'payment_method',
        'status',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_idr' => 'integer',
            'applied_amount_idr' => 'integer',
            'refunded_amount_idr' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(HotelProperty::class, 'property_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(HotelReservation::class, 'reservation_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function getRemainingAmountIdrAttribute(): int
    {
        return max(0, (int) $this->amount_idr - (int) $this->applied_amount_idr - (int) $this->refunded_amount_idr);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Vending\Domain\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendingTransaction extends Model
{
    protected $table = 'ven_vending_transactions';

    protected $fillable = [
        'tx_code',
        'vending_unit_id',
        'item_code',
        'qty',
        'price_idr',
        'cogs_idr',
        'payment_method',
        'biometric_token_hash',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'vending_unit_id' => 'integer',
        'qty' => 'integer',
        'price_idr' => 'integer',
        'cogs_idr' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('VendingTransaction records are append-only.');
        });

        static::deleting(function (): void {
            throw new DomainException('VendingTransaction records are append-only.');
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(VendingUnit::class, 'vending_unit_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Ret\Domain\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Domain\Traits\HasUuid;

class RetCashbackTransaction extends Model
{
    use HasUuid;

    protected $table = 'ret_cashback_transactions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'cashback_code',
        'customer_id',
        'order_id',
        'reference_id',
        'source_domain',
        'source_line',
        'amount_minor',
        'transaction_amount_minor',
        'cashback_pct',
        'cashback_earned_minor',
        'type',
        'status',
        'expires_at',
        'idempotency_key',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'transaction_amount_minor' => 'integer',
        'cashback_earned_minor' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            throw new DomainException('Cashback transactions are append-only facts and cannot be updated.');
        });

        static::deleting(function (self $model): void {
            throw new DomainException('Cashback transactions are append-only facts and cannot be deleted.');
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Crypto\Domain\Enums\TradeSide;
use Modules\Crypto\Domain\Enums\TradeStatus;

class CryptoTrade extends Model
{
    protected $table = 'crypto_trades';

    protected $fillable = [
        'uuid',
        'user_id',
        'asset_id',
        'quote_id',
        'side',
        'quantity',
        'price_idr',
        'gross_idr',
        'fee_idr',
        'ledger_transaction_id',
        'status',
        'created_at',
    ];

    protected $casts = [
        'side' => TradeSide::class,
        'status' => TradeStatus::class,
        'quantity' => 'string',
        'price_idr' => 'string',
        'gross_idr' => 'string',
        'fee_idr' => 'string',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'asset_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(CryptoQuote::class, 'quote_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }
}

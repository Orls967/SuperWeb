<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SybUniversalWalletTransaction extends Model
{
    protected $table = 'syb_universal_wallet_transactions';

    protected $fillable = [
        'transaction_ref',
        'order_reference',
        'customer_account_ref',
        'merchant_code',
        'business_line_code',
        'gross_amount_minor',
        'admin_fee_minor',
        'net_merchant_amount_minor',
        'is_halal_verified',
        'ledger_journal_ref',
        'status',
        'rejection_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount_minor' => 'integer',
            'admin_fee_minor' => 'integer',
            'net_merchant_amount_minor' => 'integer',
            'is_halal_verified' => 'boolean',
            'metadata' => 'array',
        ];
    }
}

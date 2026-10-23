<?php

declare(strict_types=1);

namespace Modules\Banking\Contracts;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Domain\Traits\HasUuid;

/**
 * Cross-module contract for LedgerAccount model.
 */
class LedgerAccount extends Model
{
    use HasUuid;

    protected $table = 'bank_ledger_accounts';

    protected $fillable = [
        'uuid',
        'code',
        'owner_type',
        'owner_id',
        'asset_code',
        'kind',
        'name',
        'allow_negative',
        'cached_balance',
        'is_frozen',
    ];

    public function canCover(BigDecimal|string|int $amount): bool
    {
        if ($this->allow_negative) {
            return true;
        }

        $needed = $amount instanceof BigDecimal ? $amount : BigDecimal::of((string) $amount);
        $current = BigDecimal::of($this->cached_balance ?: '0');

        return $current->isGreaterThanOrEqualTo($needed);
    }
}

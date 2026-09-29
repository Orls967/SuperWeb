<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Shared\Domain\Traits\HasUuid;
use Modules\Shared\Domain\ValueObjects\Money;

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

    protected $casts = [
        'allow_negative' => 'boolean',
        'is_frozen' => 'boolean',
        'cached_balance' => 'string',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }

    public function money(): Money
    {
        return Money::of($this->asset_code, $this->cached_balance ?: '0');
    }

    public function canCover(BigDecimal|string|int|float $amount): bool
    {
        if ($this->allow_negative) {
            return true;
        }

        $needed = $amount instanceof BigDecimal ? $amount : BigDecimal::of((string) $amount);
        $current = BigDecimal::of($this->cached_balance ?: '0');

        return $current->isGreaterThanOrEqualTo($needed);
    }
}

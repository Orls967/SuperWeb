<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\WalletPin;
use Modules\Shared\Domain\ValueObjects\Money;

trait HasLedgerAccounts
{
    public function walletAccount(string $asset = 'IDR'): LedgerAccount
    {
        $asset = strtoupper(trim($asset));

        return LedgerAccount::firstOrCreate(
            [
                'code' => "wallet:user:{$this->id}:{$asset}",
            ],
            [
                'uuid' => (string) Str::uuid(),
                'owner_type' => self::class,
                'owner_id' => $this->id,
                'asset_code' => $asset,
                'kind' => AccountKind::WALLET->value,
                'name' => "Dompet {$asset} - {$this->name}",
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public function walletBalance(string $asset = 'IDR'): Money
    {
        $account = $this->walletAccount($asset);

        return Money::of($asset, $account->cached_balance ?: '0');
    }

    public function walletPin(): HasOne
    {
        return $this->hasOne(WalletPin::class, 'user_id');
    }

    public function hasPin(): bool
    {
        return $this->walletPin()->exists();
    }

    public function isPinLocked(): bool
    {
        $pin = $this->walletPin;

        return $pin !== null && $pin->isLocked();
    }
}

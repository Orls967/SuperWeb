<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Models;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Finance\Domain\Enums\InstallmentStatus;
use Modules\Finance\Domain\Enums\LoanStatus;
use Modules\Shared\Domain\Exceptions\InvalidStateTransition;
use Modules\Shared\Domain\Traits\HasUuid;
use Modules\Store\Domain\Models\Order;

class Loan extends Model
{
    use HasFactory, HasUuid;

    /** Ambang LTV pembukaan pinjaman. */
    public const MAX_LTV_AT_OPEN = 0.50;

    /** Ambang LTV yang memicu margin call. */
    public const MARGIN_CALL_LTV = 0.70;

    /** Ambang LTV yang memicu likuidasi otomatis. */
    public const LIQUIDATION_LTV = 0.80;

    /** Denda keterlambatan per hari. */
    public const DAILY_PENALTY_RATE = 0.001;

    protected $table = 'fin_loans';

    protected $fillable = [
        'uuid',
        'user_id',
        'order_id',
        'principal',
        'down_payment',
        'interest_rate_annual',
        'tenor_months',
        'collateral_asset_id',
        'collateral_qty',
        'ltv_at_open',
        'outstanding_principal',
        'status',
        'opened_at',
        'closed_at',
        'margin_called_at',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'integer',
            'down_payment' => 'integer',
            'tenor_months' => 'integer',
            'outstanding_principal' => 'integer',
            // Kolateral disimpan presisi tinggi: selalu diperlakukan sebagai string
            'collateral_qty' => 'string',
            'status' => LoanStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'margin_called_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function collateralAsset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'collateral_asset_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'loan_id')->orderBy('sequence');
    }

    public function transitionTo(LoanStatus $next): self
    {
        if (! $this->status->canTransitionTo($next)) {
            throw InvalidStateTransition::fromTo($this->status, $next, 'Loan');
        }

        $this->status = $next;
        $this->save();

        return $this;
    }

    public function collateralQty(): BigDecimal
    {
        return BigDecimal::of($this->collateral_qty ?: '0');
    }

    /**
     * Nilai pasar kolateral pada harga tertentu.
     */
    public function collateralValue(BigDecimal $price): BigDecimal
    {
        return $this->collateralQty()->multipliedBy($price);
    }

    /**
     * Loan-to-Value: sisa pokok dibagi nilai pasar kolateral.
     */
    public function currentLtv(BigDecimal $price): float
    {
        $value = $this->collateralValue($price);

        if ($value->isZero() || $value->isNegative()) {
            return 1.0;
        }

        return (float) (string) BigDecimal::of((string) $this->outstanding_principal)
            ->dividedBy($value, 6, RoundingMode::HalfUp);
    }

    /**
     * Kolateral minimal agar LTV pembukaan tidak melebihi 50%.
     */
    public static function requiredCollateralQty(int $principal, BigDecimal $price, int $decimals = 18): BigDecimal
    {
        if ($price->isZero()) {
            return BigDecimal::zero();
        }

        return BigDecimal::of((string) $principal)
            ->dividedBy($price->multipliedBy((string) self::MAX_LTV_AT_OPEN), $decimals, RoundingMode::Up);
    }

    public function nextUnpaidInstallment(): ?Installment
    {
        return $this->installments()
            ->whereIn('status', [InstallmentStatus::Scheduled->value, InstallmentStatus::Overdue->value])
            ->orderBy('sequence')
            ->first();
    }

    public function totalInterest(): int
    {
        return (int) $this->installments()->sum('interest_part');
    }

    public function paidInstallmentsCount(): int
    {
        return $this->installments()->where('status', InstallmentStatus::Paid->value)->count();
    }

    public function getFormattedPrincipalAttribute(): string
    {
        return 'Rp '.number_format($this->principal, 0, ',', '.');
    }

    public function getFormattedOutstandingAttribute(): string
    {
        return 'Rp '.number_format($this->outstanding_principal, 0, ',', '.');
    }
}

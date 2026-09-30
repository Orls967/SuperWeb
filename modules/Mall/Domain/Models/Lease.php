<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mall\Domain\Enums\DepositStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\Traits\HasUuid;
use Modules\Shared\Domain\ValueObjects\Money;

class Lease extends Model implements Payable
{
    use HasUuid;

    protected $table = 'mall_leases';

    protected $fillable = [
        'uuid',
        'lease_number',
        'property_id',
        'unit_id',
        'tenant_id',
        'rent_model',
        'start_date',
        'end_date',
        'fit_out_start_date',
        'fit_out_days',
        'billing_day',
        'grace_days',
        'penalty_rate_daily_percent',
        'base_monthly_rent',
        'service_charge_monthly',
        'annual_escalation_percent',
        'revenue_share_percent',
        'security_deposit_amount',
        'deposit_status',
        'status',
        'activated_at',
        'terminated_at',
        'termination_reason',
    ];

    protected $casts = [
        'rent_model' => RentModel::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'fit_out_start_date' => 'date',
        'fit_out_days' => 'integer',
        'billing_day' => 'integer',
        'grace_days' => 'integer',
        'penalty_rate_daily_percent' => 'float',
        'base_monthly_rent' => 'integer',
        'service_charge_monthly' => 'integer',
        'annual_escalation_percent' => 'float',
        'revenue_share_percent' => 'float',
        'security_deposit_amount' => 'integer',
        'deposit_status' => DepositStatus::class,
        'status' => LeaseStatus::class,
        'activated_at' => 'datetime',
        'terminated_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function salesReports(): HasMany
    {
        return $this->hasMany(TenantSalesReport::class, 'lease_id');
    }

    public function utilityReadings(): HasMany
    {
        return $this->hasMany(UtilityReading::class, 'lease_id');
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class, 'lease_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'lease_id');
    }

    /**
     * Hitung nilai sewa bulanan untuk tahun ke-N kontrak (memperhitungkan eskalasi tahunan).
     */
    public function calculateMonthlyRent(int $yearNumber = 1): int
    {
        if ($yearNumber <= 1) {
            return $this->base_monthly_rent;
        }

        $rate = 1.0 + ($this->annual_escalation_percent / 100.0);
        $factor = pow($rate, $yearNumber - 1);

        return (int) round($this->base_monthly_rent * $factor);
    }

    /**
     * Tentukan tahun ke berapa kontrak saat ini berjalan berdasarkan tanggal mulai.
     */
    public function currentLeaseYear(): int
    {
        $start = Carbon::parse($this->start_date)->startOfDay();
        $now = Carbon::now()->startOfDay();

        if ($now->lessThan($start)) {
            return 1;
        }

        $yearsPassed = $start->diffInYears($now);

        return (int) ($yearsPassed + 1);
    }

    /**
     * Hitung sewa bulanan berjalan saat ini.
     */
    public function currentMonthlyRent(): int
    {
        return $this->calculateMonthlyRent($this->currentLeaseYear());
    }

    /**
     * Total tagihan bulanan reguler (sewa berjalan + service charge).
     */
    public function totalMonthlyBill(): int
    {
        return $this->currentMonthlyRent() + $this->service_charge_monthly;
    }

    /**
     * Cek apakah kontrak mendekati tanggal kedaluwarsa (< N hari).
     */
    public function isExpiredSoon(int $days = 90): bool
    {
        if ($this->status !== LeaseStatus::ACTIVE) {
            return false;
        }

        $end = Carbon::parse($this->end_date)->startOfDay();
        $now = Carbon::now()->startOfDay();

        if ($end->lessThan($now)) {
            return true;
        }

        return $now->diffInDays($end) <= $days;
    }

    /**
     * Cek apakah tenant masih dalam masa fit-out (renovasi bebas sewa).
     */
    public function isInFitOut(): bool
    {
        $fitOutStart = $this->fit_out_start_date ? Carbon::parse($this->fit_out_start_date) : Carbon::parse($this->start_date);
        $fitOutEnd = (clone $fitOutStart)->addDays($this->fit_out_days);

        return Carbon::now()->between($fitOutStart, $fitOutEnd);
    }

    // --- Payable Contract Implementation (for Security Deposit) ---

    public function payableAmount(): Money
    {
        return Money::idr($this->security_deposit_amount);
    }

    public function payableDescription(): string
    {
        return "Deposit Jaminan Sewa Unit {$this->unit?->unit_number} - {$this->tenant?->brand_name} (#{$this->lease_number})";
    }

    public function payerId(): int
    {
        return (int) ($this->tenant?->user_id ?? 1);
    }

    public function revenueSplits(): array
    {
        // Deposit sewa bukan pendapatan instan, melainkan titipan liabilitas
        return [
            'liability:mall:tenant_deposit:IDR' => Money::idr($this->security_deposit_amount),
        ];
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        $this->update([
            'deposit_status' => DepositStatus::HELD,
            'status' => LeaseStatus::ACTIVE,
            'activated_at' => now(),
        ]);

        $this->unit?->update(['status' => UnitStatus::LEASED]);
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        $this->update([
            'deposit_status' => DepositStatus::REFUNDED,
            'status' => LeaseStatus::TERMINATED,
            'terminated_at' => now(),
        ]);

        $this->unit?->update(['status' => UnitStatus::AVAILABLE]);
    }
}

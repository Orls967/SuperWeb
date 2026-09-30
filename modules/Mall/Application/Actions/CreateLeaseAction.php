<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Mall\Domain\Enums\DepositStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Exceptions\UnitAlreadyLeasedException;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;

class CreateLeaseAction
{
    public function handle(
        int $propertyId,
        int $unitId,
        int $tenantId,
        string|RentModel $rentModel,
        string $startDate,
        string $endDate,
        ?int $baseMonthlyRent = null,
        ?int $serviceChargeMonthly = null,
        int $fitOutDays = 30,
        int $billingDay = 1,
        int $graceDays = 7,
        float $penaltyRateDailyPercent = 0.10,
        float $annualEscalationPercent = 5.00,
        ?float $revenueSharePercent = null,
        int $securityDepositMonths = 3
    ): Lease {
        $property = Property::findOrFail($propertyId);
        $unit = Unit::where('property_id', $property->id)->findOrFail($unitId);
        $tenant = Tenant::findOrFail($tenantId);

        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        if (Carbon::parse($start)->greaterThanOrEqualTo(Carbon::parse($end))) {
            throw new InvalidArgumentException('Tanggal mulai sewa harus lebih awal daripada tanggal berakhir.');
        }

        // 1. Anti-Overlap Validation: Unit tidak boleh memiliki sewa aktif pada periode yang sama
        if (! $unit->isAvailableBetween($start, $end)) {
            throw new UnitAlreadyLeasedException(
                "Unit {$unit->unit_number} di {$property->name} telah memiliki kontrak sewa pada rentang tanggal {$start} s/d {$end}."
            );
        }

        $baseRent = $baseMonthlyRent ?? $unit->estimatedBaseRent();
        $serviceCharge = $serviceChargeMonthly ?? $unit->estimatedServiceCharge();
        $depositAmount = $baseRent * $securityDepositMonths;

        $modelEnum = $rentModel instanceof RentModel ? $rentModel : RentModel::from($rentModel);

        return DB::transaction(function () use (
            $property,
            $unit,
            $tenant,
            $modelEnum,
            $start,
            $end,
            $fitOutDays,
            $billingDay,
            $graceDays,
            $penaltyRateDailyPercent,
            $baseRent,
            $serviceCharge,
            $annualEscalationPercent,
            $revenueSharePercent,
            $depositAmount
        ) {
            $year = date('Y');
            $code = strtoupper(Str::random(4));
            $leaseNumber = "LSE-{$property->code}-{$year}-{$code}";

            $lease = Lease::create([
                'uuid' => (string) Str::uuid(),
                'lease_number' => $leaseNumber,
                'property_id' => $property->id,
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'rent_model' => $modelEnum,
                'start_date' => $start,
                'end_date' => $end,
                'fit_out_start_date' => $start,
                'fit_out_days' => $fitOutDays,
                'billing_day' => $billingDay,
                'grace_days' => $graceDays,
                'penalty_rate_daily_percent' => $penaltyRateDailyPercent,
                'base_monthly_rent' => $baseRent,
                'service_charge_monthly' => $serviceCharge,
                'annual_escalation_percent' => $annualEscalationPercent,
                'revenue_share_percent' => $revenueSharePercent,
                'security_deposit_amount' => $depositAmount,
                'deposit_status' => DepositStatus::UNPAID,
                'status' => LeaseStatus::DRAFT,
            ]);

            // Jika status unit saat ini available, ubah jadi reserved
            if ($unit->status === UnitStatus::AVAILABLE) {
                $unit->update(['status' => UnitStatus::RESERVED]);
            }

            return $lease;
        });
    }
}

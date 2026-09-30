<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Models\Lease;

class RenewLeaseAction
{
    public function __construct(
        private readonly CreateLeaseAction $createLeaseAction
    ) {}

    public function handle(
        Lease $oldLease,
        string $newEndDate,
        ?float $annualEscalationPercent = null
    ): Lease {
        if (! in_array($oldLease->status, [LeaseStatus::ACTIVE, LeaseStatus::DRAFT], true)) {
            throw new InvalidArgumentException("Kontrak berstatus {$oldLease->status->value} tidak dapat diperpanjang.");
        }

        $newStartDate = Carbon::parse($oldLease->end_date)->addDay()->toDateString();
        $parsedNewEnd = Carbon::parse($newEndDate)->toDateString();

        if (Carbon::parse($newStartDate)->greaterThanOrEqualTo(Carbon::parse($parsedNewEnd))) {
            throw new InvalidArgumentException('Tanggal akhir perpanjangan harus lebih besar dari tanggal akhir kontrak sebelumnya.');
        }

        $escalation = $annualEscalationPercent ?? $oldLease->annual_escalation_percent;

        // Hitung tarif sewa baru dengan eskalasi
        $newBaseRent = $oldLease->calculateMonthlyRent($oldLease->currentLeaseYear() + 1);

        return $this->createLeaseAction->handle(
            propertyId: $oldLease->property_id,
            unitId: $oldLease->unit_id,
            tenantId: $oldLease->tenant_id,
            rentModel: $oldLease->rent_model,
            startDate: $newStartDate,
            endDate: $parsedNewEnd,
            baseMonthlyRent: $newBaseRent,
            serviceChargeMonthly: $oldLease->service_charge_monthly,
            fitOutDays: 0, // Perpanjangan tidak memerlukan masa fit out
            billingDay: $oldLease->billing_day,
            graceDays: $oldLease->grace_days,
            penaltyRateDailyPercent: $oldLease->penalty_rate_daily_percent,
            annualEscalationPercent: $escalation,
            revenueSharePercent: $oldLease->revenue_share_percent,
            securityDepositMonths: 3
        );
    }
}

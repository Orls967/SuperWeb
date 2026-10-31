<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\CloudKitchen\Domain\Models\CateringPayrollDeduction;
use Modules\CloudKitchen\Domain\Models\CateringSubscription;

class CateringSubscriptionService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Subscribe employee to corporate catering meal plan.
     */
    public function subscribe(array $data, string $idempotencyKey): CateringSubscription
    {
        if (empty($idempotencyKey)) {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        $userId = (int) $data['user_id'];
        $startDate = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : Carbon::now();
        $durationMonths = (int) ($data['duration_months'] ?? 1);
        $endDate = $startDate->copy()->addMonths($durationMonths);

        $subCode = 'SUB-'.strtoupper(substr(hash('sha256', "sub:{$userId}:{$idempotencyKey}"), 0, 10));

        return CateringSubscription::firstOrCreate(
            ['subscription_code' => $subCode],
            [
                'user_id' => $userId,
                'package_name' => $data['package_name'],
                'daily_quota' => $data['daily_quota'] ?? 1,
                'used_quota_today' => 0,
                'monthly_fee_idr' => $data['monthly_fee_idr'],
                'daily_meal_price_idr' => $data['daily_meal_price_idr'],
                'starts_at' => $startDate->toDateString(),
                'expires_at' => $endDate->toDateString(),
                'status' => 'active',
            ]
        );
    }

    /**
     * Handle hcm.payroll.posted.v1 domain event and execute catering deduction.
     *
     * @param  array{employee_id: int, payroll_period: string, idempotency_key: string}  $eventPayload
     * @return array{deducted_idr: int, status: string}
     */
    public function handlePayrollPostedEvent(array $eventPayload): array
    {
        $idempotencyKey = $eventPayload['idempotency_key'];
        $existing = CateringPayrollDeduction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return ['deducted_idr' => (int) $existing->amount_idr, 'status' => 'DEDUCTED'];
        }

        $employeeId = (int) $eventPayload['employee_id'];
        $sub = CateringSubscription::where('user_id', $employeeId)
            ->where('status', 'active')
            ->first();

        if (! $sub) {
            return ['deducted_idr' => 0, 'status' => 'NO_ACTIVE_SUBSCRIPTION'];
        }

        $deductionAmount = (int) $sub->monthly_fee_idr;

        DB::transaction(function () use ($deductionAmount, $idempotencyKey, $employeeId, $sub, $eventPayload): void {
            CateringPayrollDeduction::create([
                'subscription_id' => $sub->id,
                'payroll_period' => $eventPayload['payroll_period'] ?? date('Y-m'),
                'amount_idr' => $deductionAmount,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->ledgerService->post(new PostingDTO(
                type: 'hcm_catering_deduction',
                description: "HCM Payroll Catering Deduction Period for User {$employeeId}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('hcm:meals_deduction:IDR', $deductionAmount)
                    ->credit('ckt:catering_revenue:IDR', $deductionAmount)
                    ->toEntries('IDR'),
                referenceType: 'catering_subscription',
                referenceId: (string) $sub->id
            ));
        });

        return ['deducted_idr' => $deductionAmount, 'status' => 'DEDUCTED'];
    }

    public static function getTotalDeductedIdr(): int
    {
        return (int) CateringPayrollDeduction::sum('amount_idr');
    }
}

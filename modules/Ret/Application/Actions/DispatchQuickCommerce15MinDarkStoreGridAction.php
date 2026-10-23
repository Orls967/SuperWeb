<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class DispatchQuickCommerce15MinDarkStoreGridAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Dispatches 15-minute quick commerce dark store deliveries within 3km grid, compensates SLA breaches,
     * manages reusable packaging deposits, and verifies crowdshipping bounds.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $dispatchCode = (string) ($params['dispatch_code'] ?? 'QC-DSP-'.hash('sha256', (string) ($params['dark_store_id'] ?? 'default')));
            $darkStoreId = (string) ($params['dark_store_id'] ?? 'DS-GRID-01');

            // Radius constraint: Quick commerce dark stores must serve within 3.0 km
            $deliveryDistanceKm = (int) ($params['delivery_distance_km'] ?? 2);
            $maxAllowedRadiusKm = (int) ($params['max_allowed_radius_km'] ?? 3);

            if ($deliveryDistanceKm > $maxAllowedRadiusKm) {
                throw new DomainException("Quick commerce dispatch rejected: Delivery distance {$deliveryDistanceKm} km exceeds maximum dark store grid radius {$maxAllowedRadiusKm} km");
            }

            // SLA Promise & Delay compensation
            $promisedDeliveryMinutes = (int) ($params['promised_delivery_minutes'] ?? 15);
            $actualDeliveryMinutes = (int) ($params['actual_delivery_minutes'] ?? 14);

            $slaBreached = $actualDeliveryMinutes > $promisedDeliveryMinutes;
            $slaCompensationMinor = 0;

            if ($slaBreached) {
                $slaCompensationMinor = (int) ($params['delay_voucher_amount_minor'] ?? 25000);

                if ($slaCompensationMinor > 0) {
                    $idempotencyKey = hash('sha256', "RET:SLA-BREACH:{$dispatchCode}:{$slaCompensationMinor}");
                    $this->ledgerService->post(new PostingDTO(
                        type: 'RETAIL_SLA_BREACH_COMPENSATION',
                        description: "Quick commerce SLA delay voucher compensation for {$dispatchCode}",
                        idempotencyKey: $idempotencyKey,
                        entries: Posting::lines()
                            ->debit('ret:sla_compensation_expense:IDR', $slaCompensationMinor)
                            ->credit('ret:customer_credit_liability:IDR', $slaCompensationMinor)
                            ->toEntries('IDR'),
                        referenceType: 'RET_QC_SLA',
                        referenceId: $dispatchCode,
                    ));
                }
            }

            // Reusable packaging deposit & reverse logistics loop
            $packagingMode = (string) ($params['packaging_mode'] ?? 'ISSUE'); // 'ISSUE' | 'RETURN'
            $containerDepositMinor = (int) ($params['packaging_deposit_minor'] ?? 10000);

            if ($packagingMode === 'ISSUE' && $containerDepositMinor > 0) {
                $pkgKey = hash('sha256', "RET:PKG-ISSUE:{$dispatchCode}:{$containerDepositMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'RETAIL_PACKAGING_DEPOSIT_RECEIPT',
                    description: "Reusable container packaging deposit received {$dispatchCode}",
                    idempotencyKey: $pkgKey,
                    entries: Posting::lines()
                        ->debit('ret:packaging_deposit_cash:IDR', $containerDepositMinor)
                        ->credit('ret:packaging_deposit_liability:IDR', $containerDepositMinor)
                        ->toEntries('IDR'),
                    referenceType: 'RET_PACKAGING_DEPOSIT',
                    referenceId: $dispatchCode,
                ));
            } elseif ($packagingMode === 'RETURN' && $containerDepositMinor > 0) {
                $pkgKey = hash('sha256', "RET:PKG-RETURN:{$dispatchCode}:{$containerDepositMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'RETAIL_PACKAGING_DEPOSIT_REFUND',
                    description: "Reusable container packaging deposit refunded {$dispatchCode}",
                    idempotencyKey: $pkgKey,
                    entries: Posting::lines()
                        ->debit('ret:packaging_deposit_liability:IDR', $containerDepositMinor)
                        ->credit('ret:packaging_deposit_cash:IDR', $containerDepositMinor)
                        ->toEntries('IDR'),
                    referenceType: 'RET_PACKAGING_REFUND',
                    referenceId: $dispatchCode,
                ));
            }

            // Crowdshipping bounds validation
            $orderValueMinor = (int) ($params['order_value_minor'] ?? 150000);
            $crowdshipperFeeMinor = (int) ($params['crowdshipper_fee_minor'] ?? 15000);

            if ($crowdshipperFeeMinor > $orderValueMinor) {
                throw new DomainException("Crowdshipping fee {$crowdshipperFeeMinor} cannot exceed gross order value {$orderValueMinor}");
            }

            $result = [
                'dispatch_code' => $dispatchCode,
                'dark_store_id' => $darkStoreId,
                'delivery_distance_km' => $deliveryDistanceKm,
                'promised_minutes' => $promisedDeliveryMinutes,
                'actual_minutes' => $actualDeliveryMinutes,
                'sla_breached' => $slaBreached,
                'sla_compensation_minor' => $slaCompensationMinor,
                'packaging_mode' => $packagingMode,
                'container_deposit_minor' => $containerDepositMinor,
                'crowdshipper_fee_minor' => $crowdshipperFeeMinor,
                'dispatch_status' => 'DISPATCHED',
            ];

            $this->audit(
                action: 'ret.qcommerce.dispatched',
                newValues: [
                    'dispatch_code' => $dispatchCode,
                    'dark_store_id' => $darkStoreId,
                    'sla_breached' => $slaBreached ? 'YES' : 'NO',
                    'compensation_minor' => $slaCompensationMinor,
                ],
                correlationId: "ret_qc_{$dispatchCode}",
                impactType: 'quick_commerce_dispatch'
            );

            $this->outbox(
                eventType: 'QuickCommerceDarkStoreOrderDispatched',
                payload: [
                    'dispatch_code' => $dispatchCode,
                    'dark_store_id' => $darkStoreId,
                    'sla_breached' => $slaBreached,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:QC:{$dispatchCode}")
            );

            return $result;
        });
    }
}

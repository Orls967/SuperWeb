<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;

class RateAndBillTelecomCdrBatchAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): IotConnectivityInvoice
    {
        $ownerEntityId = (string) $params['owner_entity_id'];
        $consumedMb = (float) $params['total_consumed_mb'];
        $deviceCount = (int) ($params['active_device_count'] ?? 10);
        $period = (string) ($params['billing_period'] ?? Carbon::now()->format('Y-m'));
        $ratePerMb = (int) ($params['rate_per_mb_minor'] ?? 50);

        $totalChargeMinor = (int) round($consumedMb * $ratePerMb);

        return DB::transaction(function () use ($ownerEntityId, $consumedMb, $deviceCount, $period, $totalChargeMinor) {
            $invoiceNum = 'INV-CDR-'.strtoupper(substr(hash('sha256', "{$ownerEntityId}:{$period}:{$totalChargeMinor}"), 0, 10));

            $invoice = IotConnectivityInvoice::create([
                'id' => (string) Str::uuid(),
                'invoice_number' => $invoiceNum,
                'owner_entity_id' => $ownerEntityId,
                'billing_period' => $period,
                'active_device_count' => $deviceCount,
                'total_consumed_mb' => $consumedMb,
                'total_charge_minor' => $totalChargeMinor,
            ]);

            if ($totalChargeMinor > 0) {
                $idempotencyKey = hash('sha256', "CDR-BILL:{$invoiceNum}:{$totalChargeMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'TELECOM_CDR_BATCH_BILLING',
                    description: "CDR batch rating and billing for {$ownerEntityId} ({$invoiceNum})",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('tlx:telecom_receivable:IDR', $totalChargeMinor)
                        ->credit('tlx:iot_service_revenue:IDR', $totalChargeMinor)
                        ->toEntries('IDR'),
                    referenceType: 'TLX_CDR_INVOICE',
                    referenceId: $invoiceNum,
                ));
            }

            $this->audit(
                action: 'tlx_cdr_batch_billed',
                auditable: $invoice,
                newValues: ['invoice_number' => $invoiceNum, 'total_minor' => $totalChargeMinor],
                impactType: 'telecom_rating',
            );

            return $invoice;
        });
    }
}

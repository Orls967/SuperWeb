<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Application\DTOs\AlliancePartnerTransferDTO;
use Modules\Hotel\Domain\Algorithms\PartnerPointConversionCertificate;
use Modules\Hotel\Domain\Algorithms\PartnerPointConverter;
use Modules\Hotel\Domain\Models\HtlAlliancePartner;
use Modules\Hotel\Domain\Models\HtlAllianceTransferLog;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class HotelPartnerPointTransferSaga
{
    public function __construct(
        protected ?LedgerService $ledgerService = null,
        protected ?PartnerPointConverter $converter = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
        $this->converter = $converter ?? new PartnerPointConverter;
    }

    public function execute(
        string $travelPassId,
        string $partnerCode,
        string $memberPartnerAccount,
        int $pointsToTransfer,
        bool $forceFail = false
    ): AlliancePartnerTransferDTO {
        $sagaId = 'SAGA-ALN-'.Str::uuid()->toString();

        return DB::transaction(function () use (
            $travelPassId,
            $partnerCode,
            $memberPartnerAccount,
            $pointsToTransfer,
            $forceFail,
            $sagaId
        ) {
            $pass = HtlTravelPass::where('id', $travelPassId)->lockForUpdate()->firstOrFail();

            if ($pass->pts_balance < $pointsToTransfer) {
                throw new RuntimeException("Saldo poin tidak mencukupi: Saldo {$pass->pts_balance} PTS, diminta {$pointsToTransfer} PTS");
            }

            $partner = HtlAlliancePartner::where('partner_code', $partnerCode)->first();
            $ratio = $partner ? (float) $partner->base_exchange_ratio : 10.0;
            $feePct = $partner ? (float) $partner->transfer_fee_percentage : 0.035;

            // 1. Calculate conversion
            $cert = $this->converter->convert(
                partnerCode: $partnerCode,
                pointsToConvert: $pointsToTransfer,
                exchangeRatio: $ratio,
                transferFeePercent: $feePct,
                pointValuationIdr: 100
            );

            // 2. Debit member pass
            $pass->decrement('pts_balance', $pointsToTransfer);

            // 3. Create log
            $log = HtlAllianceTransferLog::create([
                'id' => (string) Str::uuid(),
                'travel_pass_id' => $pass->id,
                'partner_code' => $partnerCode,
                'member_partner_account' => $memberPartnerAccount,
                'points_debited' => $pointsToTransfer,
                'partner_units_credited' => $cert->partnerUnitsCredited,
                'transfer_fee_idr' => $cert->transferFeeIdr,
                'net_payable_idr' => $cert->netPayableToPartnerIdr,
                'status' => 'PROCESSING',
                'saga_id' => $sagaId,
                'idempotency_key' => 'HTL-ALN-TX-'.$sagaId,
                'certificate_hash' => $cert->signatureHash,
            ]);

            // 4. Ledger posting: Debit points liability, Credit partner payable & transfer fee revenue
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::HTL_ALLIANCE_PTS_TRANSFER->value,
                description: "Transfer {$pointsToTransfer} PTS to {$partnerCode} account {$memberPartnerAccount}",
                idempotencyKey: "HTL-ALN-LEDGER-{$log->id}",
                entries: Posting::lines()
                    ->debit('htl:loyalty_points_liability:IDR', $cert->totalLiabilityDebitedIdr)
                    ->credit('htl:partner_points_payable:IDR', $cert->netPayableToPartnerIdr)
                    ->credit('htl:transfer_fee_revenue:IDR', $cert->transferFeeIdr)
                    ->toEntries('IDR'),
                referenceType: 'ALLIANCE_TRANSFER',
                referenceId: $log->id,
            ));

            // 5. External Partner Transmission Step
            if ($forceFail) {
                // Compensate rollback!
                return $this->compensate($pass, $log, $cert, 'Partner API Timeout / Reject');
            }

            $log->update(['status' => 'SUCCESS']);

            return new AlliancePartnerTransferDTO(
                transactionId: $log->id,
                travelPassId: $pass->id,
                partnerCode: $partnerCode,
                memberPartnerAccount: $memberPartnerAccount,
                pointsDebited: $pointsToTransfer,
                partnerUnitsCredited: $cert->partnerUnitsCredited,
                transferFeeIdr: $cert->transferFeeIdr,
                netPayableIdr: $cert->netPayableToPartnerIdr,
                status: 'SUCCESS',
                certificateHash: $cert->signatureHash
            );
        });
    }

    protected function compensate(
        HtlTravelPass $pass,
        HtlAllianceTransferLog $log,
        PartnerPointConversionCertificate $cert,
        string $reason
    ): AlliancePartnerTransferDTO {
        // Rollback points to member
        $pass->increment('pts_balance', $cert->pointsConverted);

        // Reverse ledger posting
        $this->ledgerService->post(new PostingDTO(
            type: HotelTransactionTypes::HTL_ALLIANCE_PTS_ROLLBACK->value,
            description: "Rollback transfer {$cert->pointsConverted} PTS to {$log->partner_code} reason: {$reason}",
            idempotencyKey: "HTL-ALN-REVERSE-{$log->id}",
            entries: Posting::lines()
                ->debit('htl:partner_points_payable:IDR', $cert->netPayableToPartnerIdr)
                ->debit('htl:transfer_fee_revenue:IDR', $cert->transferFeeIdr)
                ->credit('htl:loyalty_points_liability:IDR', $cert->totalLiabilityDebitedIdr)
                ->toEntries('IDR'),
            referenceType: 'ALLIANCE_TRANSFER_REVERSAL',
            referenceId: $log->id,
        ));

        $log->update(['status' => 'COMPENSATED']);

        return new AlliancePartnerTransferDTO(
            transactionId: $log->id,
            travelPassId: $pass->id,
            partnerCode: $log->partner_code,
            memberPartnerAccount: $log->member_partner_account,
            pointsDebited: 0,
            partnerUnitsCredited: 0,
            transferFeeIdr: 0,
            netPayableIdr: 0,
            status: 'COMPENSATED',
            certificateHash: $cert->signatureHash
        );
    }
}

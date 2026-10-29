<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\BpjsClaim;
use Modules\Hospital\Ledger\HospitalTransactionTypes;
use Modules\Shared\Domain\Attributes\NotAControl;

class AdjudicateBpjsClaimAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    public function execute(
        BpjsClaim $claim,
        #[NotAControl('Simulasi verifikasi status persetujuan klaim BPJS')]
        bool $approved = true,
        ?string $rejectionReason = null
    ): BpjsClaim {
        return DB::transaction(function () use ($claim, $approved, $rejectionReason) {
            $now = now();

            if ($approved) {
                $claim->update([
                    'status' => 'APPROVED',
                    'adjudicated_at' => $now,
                ]);

                if ($claim->invoice && $claim->invoice->encounter) {
                    $claim->invoice->encounter->update(['status' => 'SETTLED']);
                }

                return $claim->fresh();
            }

            // Jika klaim ditolak (rejection):
            $claim->update([
                'status' => 'REJECTED',
                'rejection_reason' => $rejectionReason ?? 'Klaim ditolak oleh verifikator BPJS',
                'adjudicated_at' => $now,
            ]);

            // Jurnal pembalik piutang BPJS:
            // Debit Beban Klaim Ditolak, Kredit Piutang BPJS
            $this->ledgerService->post(new PostingDTO(
                type: HospitalTransactionTypes::HSP_OUTPATIENT_BPJS_REJECT->value,
                description: "Jurnal pembalik klaim BPJS ditolak berkas {$claim->claim_code}",
                idempotencyKey: "HSP-REJ-{$claim->id}",
                entries: Posting::lines()
                    ->debit('hsp:bpjs_rejected_claim:IDR', $claim->ina_cbg_tariff_idr)
                    ->credit('hsp:bpjs_receivable:IDR', $claim->ina_cbg_tariff_idr)
                    ->toEntries('IDR'),
                referenceType: 'HSP_BPJS_CLAIM',
                referenceId: (string) $claim->id,
            ));

            return $claim->fresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\LabCatalog;
use Modules\Hospital\Domain\Models\LabResult;
use Modules\Hospital\Domain\Models\PathologistVerificationLog;

/**
 * Layanan Verifikasi Medis & Pengesahan Digital Patolog Klinik (Fase 105).
 */
class PathologistVerificationService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Memverifikasi dan melepaskan status CRITICAL_HOLD dengan tanda tangan digital patolog.
     */
    public function verifyAndRelease(
        LabResult $result,
        User $pathologist,
        ?string $interpretation = null,
        ?string $signatureHash = null
    ): LabResult {
        return DB::transaction(function () use (
            $result,
            $pathologist,
            $interpretation,
            $signatureHash
        ) {
            $fresh = $result->fresh();

            if ($fresh->status === 'PATHOLOGIST_VERIFIED') {
                return $fresh;
            }

            $sigHash = $signatureHash ?? hash('sha256', 'PATHOLOGIST:'.$pathologist->id.':'.$fresh->id.':'.now()->toIso8601String());

            // Catat log verifikasi patolog
            PathologistVerificationLog::create([
                'specimen_id' => $fresh->specimen_id,
                'test_code' => $fresh->test_code,
                'pathologist_id' => $pathologist->id,
                'digital_signature_hash' => $sigHash,
                'clinical_interpretation' => $interpretation,
                'signed_at' => now(),
            ]);

            $fresh->update([
                'status' => 'PATHOLOGIST_VERIFIED',
                'verified_by_pathologist_hash' => $sigHash,
            ]);

            // Akui pendapatan laboratorium setelah diverifikasi patolog
            $catalogItem = LabCatalog::where('test_code', $fresh->test_code)->first();
            $feeIdr = $catalogItem ? (int) $catalogItem->price_idr : 120_000;

            if ($feeIdr > 0) {
                $this->recognizeLabRevenue($fresh, $feeIdr);
            }

            return $fresh;
        });
    }

    /**
     * Menjurnal pendapatan laboratorium ke buku besar setelah pengesahan patolog.
     */
    protected function recognizeLabRevenue(LabResult $result, int $amountIdr): void
    {
        $this->ledgerService->post(new PostingDTO(
            type: 'LAB_TEST_BILLING',
            description: "Lab revenue recognized for certified result {$result->result_code} ({$result->test_code})",
            idempotencyKey: "HSP-LAB-{$result->result_code}",
            entries: Posting::lines()
                ->debit('hsp:lab_receivable:IDR', $amountIdr)
                ->credit('hsp:lab_revenue:IDR', $amountIdr)
                ->toEntries('IDR'),
            referenceType: 'LAB_RESULT',
            referenceId: (string) $result->id,
        ));
    }
}

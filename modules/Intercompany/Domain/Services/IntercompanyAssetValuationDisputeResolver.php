<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Services;

use InvalidArgumentException;

/**
 * Penyelesai Sengketa Penilaian & Eliminasi Transfer Aset Tetap Intra-Grup (PSAK 16 / PMK 172).
 *
 * Mengelola evaluasi kepatuhan harga transfer pengalihan aset tetap antar-entitas,
 * rekonsiliasi nilai buku fiskal vs komersial (Pasal 10 UU PPh), eliminasi keuntungan/kerugian
 * intra-grup yang belum terealisasi dalam laporan keuangan konsolidasi, serta penyesuaian
 * beban penyusutan konsolidasi di masa mendatang.
 * Seluruh kalkulasi beroperasi pada bilangan bulat (integer minor units sen / basis points) tanpa float.
 */
class IntercompanyAssetValuationDisputeResolver
{
    /**
     * Evaluasi transaksi pengalihan aset tetap dan tentukan jurnal penyesuaian konsolidasi.
     *
     * @param array{
     *     transfer_code: string,
     *     asset_tag: string,
     *     source_entity_code: string,
     *     target_entity_code: string,
     *     net_book_value_minor: int,
     *     accumulated_depreciation_minor: int,
     *     agreed_transfer_price_minor: int,
     *     independent_appraisal_value_minor?: int,
     *     remaining_useful_life_months: int,
     *     tax_rate_bps?: int,
     *     is_tax_neutral_reorganization?: bool
     * } $data
     * @return array{
     *     evaluation_id: string,
     *     transfer_code: string,
     *     asset_tag: string,
     *     source_entity: string,
     *     target_entity: string,
     *     commercial_gain_loss_minor: int,
     *     fiscal_gain_loss_minor: int,
     *     appraisal_variance_minor: int,
     *     is_arms_length_compliant: bool,
     *     deferred_tax_adjustment_minor: int,
     *     monthly_depreciation_adjustment_minor: int,
     *     consolidation_elimination_journals: array<int, array{
     *         journal_code: string,
     *         debit_account: string,
     *         credit_account: string,
     *         amount_minor: int,
     *         memo: string
     *     }>,
     *     dispute_resolution_status: string,
     *     audit_hash: string
     * }
     */
    public function evaluateAssetTransfer(array $data): array
    {
        $code = $data['transfer_code'];
        $assetTag = $data['asset_tag'];
        $source = strtoupper($data['source_entity_code']);
        $target = strtoupper($data['target_entity_code']);
        $nbv = (int) $data['net_book_value_minor'];
        $accumDepr = (int) $data['accumulated_depreciation_minor'];
        $price = (int) $data['agreed_transfer_price_minor'];
        $appraisal = (int) ($data['independent_appraisal_value_minor'] ?? $price);
        $lifeMonths = max(1, (int) $data['remaining_useful_life_months']);
        $taxRateBps = $data['tax_rate_bps'] ?? 2200; // PPh Badan 22.00%
        $isNeutral = (bool) ($data['is_tax_neutral_reorganization'] ?? false);

        if ($source === $target) {
            throw new InvalidArgumentException("Entitas pengirim ({$source}) dan penerima ({$target}) tidak boleh sama.");
        }

        if ($nbv < 0 || $price < 0) {
            throw new InvalidArgumentException('Nilai buku bersih dan harga transfer tidak boleh bernilai negatif.');
        }

        // 1. Keuntungan/Kerugian Komersial pada Entitas Pengirim
        $commercialGainLoss = $price - $nbv;

        // 2. Keuntungan/Kerugian Fiskal
        // Jika reorganisasi netral pajak (tax-neutral merger/spin-off PMK 52/2017): menggunakan nilai sisa buku (gain = 0)
        $fiscalGainLoss = $isNeutral ? 0 : $commercialGainLoss;

        // 3. Uji Kewajaran Nilai Pasar (Arm's Length Appraisal Test)
        // Toleransi selisih harga transfer vs penilaian independen adalah 500 bps (5.00%)
        $appraisalVariance = $price - $appraisal;
        $maxTolerance = intdiv($appraisal * 500, 10000);
        $isCompliant = abs($appraisalVariance) <= $maxTolerance;

        // 4. Pajak Tangguhan atas Perbedaan Temporer Aset
        $deferredTax = 0;
        if ($commercialGainLoss !== 0) {
            $deferredTax = intdiv(abs($commercialGainLoss) * $taxRateBps, 10000);
        }

        // 5. Penyesuaian Beban Penyusutan Konsolidasian Bulanan
        // Keuntungan intra-grup yang dieliminasi harus dibalik dari beban penyusutan entitas penerima
        $monthlyDeprAdj = ($commercialGainLoss !== 0) ? intdiv($commercialGainLoss, $lifeMonths) : 0;

        $journals = [];
        $idx = 1;

        if ($commercialGainLoss > 0) {
            // Eliminasi Laba Intra-Grup atas Pengalihan Aset Tetap
            $journals[] = [
                'journal_code' => sprintf('AST-ELIM-GAIN-%s-%03d', $code, $idx++),
                'debit_account' => 'INTERCOMPANY_GAIN_ON_ASSET_DISPOSAL',
                'credit_account' => 'CONSOLIDATED_FIXED_ASSETS_COST',
                'amount_minor' => $commercialGainLoss,
                'memo' => "Eliminasi keuntungan intra-grup pengalihan aset {$assetTag} dari {$source} ke {$target}",
            ];

            // Pengakuan Aset Pajak Tangguhan atas eliminasi laba
            if ($deferredTax > 0) {
                $journals[] = [
                    'journal_code' => sprintf('AST-ELIM-DTA-%s-%03d', $code, $idx++),
                    'debit_account' => 'DEFERRED_TAX_ASSET',
                    'credit_account' => 'INCOME_TAX_EXPENSE',
                    'amount_minor' => $deferredTax,
                    'memo' => "Pengakuan aset pajak tangguhan atas keuntungan belum terealisasi aset {$assetTag}",
                ];
            }
        } elseif ($commercialGainLoss < 0) {
            // Eliminasi Kerugian Intra-Grup atas Pengalihan Aset Tetap
            $lossAmount = abs($commercialGainLoss);
            $journals[] = [
                'journal_code' => sprintf('AST-ELIM-LOSS-%s-%03d', $code, $idx++),
                'debit_account' => 'CONSOLIDATED_FIXED_ASSETS_COST',
                'credit_account' => 'INTERCOMPANY_LOSS_ON_ASSET_DISPOSAL',
                'amount_minor' => $lossAmount,
                'memo' => "Eliminasi kerugian intra-grup pengalihan aset {$assetTag} dari {$source} ke {$target}",
            ];
        }

        $status = $isCompliant
            ? 'RESOLVED_ARMS_LENGTH_VALIDATED'
            : 'DISPUTE_APPRAISAL_VARIANCE_EXCEEDED';

        $nowStr = gmdate('Y-m-d\TH:i:s\Z');
        $hashSeed = sprintf(
            '%s|%s|%s|%d|%d|%d|%s',
            $code,
            $assetTag,
            $source,
            $nbv,
            $price,
            $commercialGainLoss,
            $status
        );
        $auditHash = hash('sha256', $hashSeed);

        return [
            'evaluation_id' => "EVAL-AST-{$code}",
            'transfer_code' => $code,
            'asset_tag' => $assetTag,
            'source_entity' => $source,
            'target_entity' => $target,
            'commercial_gain_loss_minor' => $commercialGainLoss,
            'fiscal_gain_loss_minor' => $fiscalGainLoss,
            'appraisal_variance_minor' => $appraisalVariance,
            'is_arms_length_compliant' => $isCompliant,
            'deferred_tax_adjustment_minor' => $deferredTax,
            'monthly_depreciation_adjustment_minor' => $monthlyDeprAdj,
            'consolidation_elimination_journals' => $journals,
            'dispute_resolution_status' => $status,
            'audit_hash' => $auditHash,
        ];
    }
}

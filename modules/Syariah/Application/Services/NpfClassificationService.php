<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Domain\Algorithms\Islamic\NpfClassifier;
use Modules\Shared\Domain\Algorithms\Islamic\NpfClassifierCertificateVerifier;
use Modules\Syariah\Domain\Models\SybNpfClassification;
use Modules\Syariah\Domain\Models\SybNpfSnapshot;
use Modules\Syariah\Ledger\SyariahAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

final class NpfClassificationService
{
    public function __construct(
        private readonly ShariahLedgerPoster $ledgerPoster,
    ) {}

    /**
     * Mengevaluasi portofolio pembiayaan syariah dan menetapkan CKPN secara deterministik.
     *
     * @param  array<int, array{contract_ref: string, customer_ref: string, product_type: string, dpd: int, outstanding_principal_minor: int, remaining_margin_minor: int}>  $contractInputs
     */
    public function evaluateAndClassify(
        string $periodLabel,
        array $contractInputs,
        string $evaluatedBy = 'SYSTEM'
    ): SybNpfClassification {
        $result = NpfClassifier::classifyPortfolio($contractInputs);

        NpfClassifierCertificateVerifier::verify($result);

        $classificationRef = 'NPF-'.Carbon::now()->format('Ym').'-'.hash('sha256', $periodLabel.microtime());
        $classificationRef = substr($classificationRef, 0, 32);

        return DB::transaction(function () use ($classificationRef, $periodLabel, $result, $contractInputs, $evaluatedBy) {
            SyariahAccounts::ensureAccountsExist();

            $record = SybNpfClassification::create([
                'classification_ref' => $classificationRef,
                'evaluation_date' => Carbon::now()->toDateString(),
                'period_label' => $periodLabel,
                'total_contracts' => $result->totalContracts,
                'total_portfolio_minor' => $result->totalPortfolioPrincipalMinor,
                'performing_principal_minor' => $result->performingPrincipalMinor,
                'non_performing_principal_minor' => $result->nonPerformingPrincipalMinor,
                'total_required_provision_minor' => $result->totalRequiredProvisionMinor,
                'npf_gross_bps' => $result->npfGrossRatioBps,
                'npf_net_bps' => $result->npfNetRatioBps,
                'is_compliant' => $result->isCompliantWithPrudentialLimit,
                'certificate_hash' => $result->certificateHash,
                'evaluated_by' => $evaluatedBy,
            ]);

            // Simpan snapshot per kontrak
            foreach ($result->classifiedItems as $idx => $item) {
                $raw = $contractInputs[$idx] ?? [];
                SybNpfSnapshot::create([
                    'classification_ref' => $classificationRef,
                    'contract_ref' => $item['contract_ref'],
                    'customer_ref' => (string) ($raw['customer_ref'] ?? 'CUST-GENERIC'),
                    'product_type' => (string) ($raw['product_type'] ?? 'MURABAHAH'),
                    'dpd' => $item['dpd'],
                    'collectibility' => $item['collectibility'],
                    'collectibility_name' => $item['collectibility_name'],
                    'outstanding_principal_minor' => $item['principal_minor'],
                    'remaining_margin_minor' => (int) ($raw['remaining_margin_minor'] ?? 0),
                    'provision_rate_bps' => $item['provision_rate_bps'],
                    'provision_amount_minor' => $item['provision_amount_minor'],
                    'status' => 'ACTIVE',
                ]);
            }

            // Posting penyesuaian CKPN jika ada cadangan yang harus dibukukan
            if ($result->totalRequiredProvisionMinor > 0) {
                $this->ledgerPoster->postSimple(
                    type: SyariahTransactionTypes::SYB_NPF_PROVISION_ADD,
                    reference: $classificationRef,
                    description: "Penyisihan cadangan CKPN syariah periode {$periodLabel} NPF Gross {$result->npfGrossRatioBps} bps",
                    entries: [
                        [
                            'account_code' => 'syb:npf_provision_expense:IDR',
                            'amount' => $result->totalRequiredProvisionMinor,
                        ],
                        [
                            'account_code' => 'syb:npf_allowance_reserve:IDR',
                            'amount' => -$result->totalRequiredProvisionMinor,
                        ],
                    ]
                );
            }

            return $record;
        });
    }
}

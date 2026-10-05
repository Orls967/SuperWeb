<?php

declare(strict_types=1);

namespace Modules\International\Application\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Contracts\Ledger;
use Modules\International\Domain\Models\ForeignEntity;
use Modules\International\Domain\Models\JointVenture;
use Modules\International\Domain\Models\OemContract;
use Modules\International\Domain\Models\TaxTreaty;
use Modules\International\Domain\Models\TechnologyLicense;
use Modules\International\Domain\Models\TechTransfer;

class InternationalService
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {
        $this->ledger = $ledger ?? app(Ledger::class);
    }

    /**
     * 51.1 Registrasi Master Entitas Mitra Asing
     */
    public function registerForeignEntity(
        string $code,
        string $legalName,
        string $jurisdictionCountry,
        string $regNumber,
        string $functionalCurrency = 'USD',
        string $arbitration = 'SIAC',
        bool $hasApostille = true,
        bool $amlScreened = true,
        string $taxResidence = 'SG'
    ): ForeignEntity {
        return ForeignEntity::create([
            'entity_code' => strtoupper($code),
            'legal_name' => $legalName,
            'jurisdiction_country' => strtoupper($jurisdictionCountry),
            'registration_number' => $regNumber,
            'functional_currency' => strtoupper($functionalCurrency),
            'arbitration_jurisdiction' => strtoupper($arbitration),
            'has_apostille' => $hasApostille,
            'aml_screened' => $amlScreened,
            'tax_residence_country' => strtoupper($taxResidence),
            'status' => 'active',
        ]);
    }

    /**
     * 51.2 Joint Venture Management & Setoran Modal (Capital Calls)
     */
    public function establishJointVenture(
        ForeignEntity $entity,
        string $name,
        string $jvType,
        float $localSharePercent,
        float $foreignSharePercent,
        int $committedCapitalIdr,
        bool $minorityVetoRights = true
    ): JointVenture {
        if (abs(($localSharePercent + $foreignSharePercent) - 100.0) > 0.01) {
            throw new InvalidArgumentException('Total porsi saham JV harus tepat 100%.');
        }

        return JointVenture::create([
            'jv_code' => 'JV-'.strtoupper(Str::random(8)),
            'name' => $name,
            'foreign_entity_id' => $entity->id,
            'jv_type' => $jvType,
            'local_share_percent' => $localSharePercent,
            'foreign_share_percent' => $foreignSharePercent,
            'total_committed_capital_idr' => $committedCapitalIdr,
            'paid_in_capital_idr' => 0,
            'minority_veto_rights' => $minorityVetoRights,
            'status' => 'active',
        ]);
    }

    public function recordCapitalCall(JointVenture $jv, int $amountIdr): JointVenture
    {
        $newPaid = $jv->paid_in_capital_idr + $amountIdr;
        if ($newPaid > $jv->total_committed_capital_idr) {
            throw new InvalidArgumentException('Setoran modal melampaui komitmen modal JV.');
        }

        $jv->update(['paid_in_capital_idr' => $newPaid]);

        return $jv;
    }

    /**
     * 51.3 Lisensi HKI & Perhitungan Royalti Otomatis
     */
    public function registerTechnologyLicense(
        ForeignEntity $entity,
        string $title,
        string $licenseType,
        float $royaltyRatePercent,
        int $minimumAnnualGuaranteeIdr,
        string $startDate,
        string $expiryDate,
        string $territory = 'INDONESIA',
        bool $isExclusive = false
    ): TechnologyLicense {
        return TechnologyLicense::create([
            'license_code' => 'LIC-'.strtoupper(Str::random(8)),
            'title' => $title,
            'foreign_entity_id' => $entity->id,
            'license_type' => $licenseType,
            'territory' => $territory,
            'is_exclusive' => $isExclusive,
            'royalty_rate_percent' => $royaltyRatePercent,
            'minimum_annual_guarantee_idr' => $minimumAnnualGuaranteeIdr,
            'start_date' => $startDate,
            'expiry_date' => $expiryDate,
            'status' => 'active',
        ]);
    }

    public function calculateRoyalty(TechnologyLicense $license, int $netSalesIdr): array
    {
        $calculatedRoyalty = (int) floor(($netSalesIdr * $license->royalty_rate_percent) / 100);
        $finalRoyalty = max($calculatedRoyalty, (int) floor($license->minimum_annual_guarantee_idr / 12)); // bulanan

        return [
            'license_code' => $license->license_code,
            'net_sales_idr' => $netSalesIdr,
            'rate_percent' => $license->royalty_rate_percent,
            'calculated_royalty_idr' => $calculatedRoyalty,
            'payable_royalty_idr' => $finalRoyalty,
        ];
    }

    /**
     * 51.4 OEM/ODM Contract Manufacturing
     */
    public function registerOemContract(
        ForeignEntity $entity,
        string $type,
        string $designName,
        int $tollingFeePerUnitIdr,
        string $qaStandard = 'ISO9001'
    ): OemContract {
        return OemContract::create([
            'contract_number' => 'OEM-'.strtoupper(Str::random(8)),
            'foreign_entity_id' => $entity->id,
            'type' => strtoupper($type),
            'product_design_name' => $designName,
            'tolling_fee_per_unit_idr' => $tollingFeePerUnitIdr,
            'consignment_materials_tracked' => true,
            'nda_signed' => true,
            'qa_standard' => $qaStandard,
            'status' => 'active',
        ]);
    }

    /**
     * 51.5 Alih Teknologi & Milestone Delivery
     */
    public function createTechTransfer(
        ForeignEntity $entity,
        string $title,
        int $totalValueIdr
    ): TechTransfer {
        return TechTransfer::create([
            'transfer_code' => 'TT-'.strtoupper(Str::random(8)),
            'package_title' => $title,
            'foreign_entity_id' => $entity->id,
            'current_milestone' => 'BLUEPRINT_HANDOVER',
            'total_value_idr' => $totalValueIdr,
            'accepted_value_idr' => 0,
            'derivative_ip_co_owned' => true,
            'signoff_completed' => false,
            'status' => 'in_progress',
        ]);
    }

    public function acceptTransferMilestone(TechTransfer $tt, string $milestone, int $milestoneValueIdr): TechTransfer
    {
        $newAccepted = $tt->accepted_value_idr + $milestoneValueIdr;
        $isCompleted = ($newAccepted >= $tt->total_value_idr);

        $tt->update([
            'current_milestone' => $milestone,
            'accepted_value_idr' => $newAccepted,
            'signoff_completed' => $isCompleted,
            'status' => $isCompleted ? 'completed' : 'in_progress',
        ]);

        return $tt;
    }

    /**
     * 51.7 Tax Treaty (P3B) Withholding Tax Calculator
     */
    public function registerTaxTreaty(
        string $countryCode,
        string $countryName,
        float $royaltyRate,
        float $interestRate,
        float $dividendRate,
        float $servicesRate,
        float $domesticWhtRate = 20.00
    ): TaxTreaty {
        return TaxTreaty::updateOrCreate(
            ['country_code' => strtoupper($countryCode)],
            [
                'country_name' => $countryName,
                'standard_wht_rate' => $domesticWhtRate,
                'treaty_royalty_rate' => $royaltyRate,
                'treaty_interest_rate' => $interestRate,
                'treaty_dividend_rate' => $dividendRate,
                'treaty_services_rate' => $servicesRate,
                'dgt_form_required' => true,
            ]
        );
    }

    public function calculateWithholdingTax(
        int $grossAmountIdr,
        string $incomeType,
        string $countryCode,
        bool $hasValidDgt = true
    ): array {
        $treaty = TaxTreaty::where('country_code', strtoupper($countryCode))->first();

        $effectiveRate = 20.00; // Standar PPh 26 default bila tanpa P3B / DGT tidak valid

        if ($treaty && $hasValidDgt) {
            $effectiveRate = match (strtolower($incomeType)) {
                'royalty' => $treaty->treaty_royalty_rate,
                'interest' => $treaty->treaty_interest_rate,
                'dividend' => $treaty->treaty_dividend_rate,
                'service' => $treaty->treaty_services_rate,
                default => $treaty->standard_wht_rate,
            };
        }

        $whtAmountIdr = (int) floor(($grossAmountIdr * $effectiveRate) / 100);
        $netPayoutIdr = $grossAmountIdr - $whtAmountIdr;

        return [
            'gross_amount_idr' => $grossAmountIdr,
            'income_type' => $incomeType,
            'country_code' => $countryCode,
            'has_valid_dgt' => $hasValidDgt,
            'effective_rate_percent' => $effectiveRate,
            'wht_tax_idr' => $whtAmountIdr,
            'net_payout_idr' => $netPayoutIdr,
        ];
    }

    /**
     * 51.9 Audit Kerja Sama Internasional
     */
    public function auditInternational(): array
    {
        $entities = ForeignEntity::count();
        $jvs = JointVenture::count();
        $licenses = TechnologyLicense::count();
        $oems = OemContract::count();
        $transfers = TechTransfer::count();

        // Invariant: JV share must be 100%
        $invalidJvShares = JointVenture::whereRaw('ABS(local_share_percent + foreign_share_percent - 100) > 0.01')->count();

        // Invariant: paid in capital <= committed capital
        $invalidCapitals = JointVenture::whereColumn('paid_in_capital_idr', '>', 'total_committed_capital_idr')->count();

        // Invariant: tech transfer accepted <= total
        $invalidTransfers = TechTransfer::whereColumn('accepted_value_idr', '>', 'total_value_idr')->count();

        $discrepancyCount = $invalidJvShares + $invalidCapitals + $invalidTransfers;

        return [
            'status' => ($discrepancyCount === 0) ? 'OK' : 'DISCREPANCY',
            'discrepancy_count' => $discrepancyCount,
            'entity_count' => $entities,
            'jv_count' => $jvs,
            'license_count' => $licenses,
            'oem_count' => $oems,
            'tech_transfer_count' => $transfers,
        ];
    }
}

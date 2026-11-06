<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

/**
 * Laporan Inventarisasi Gas Rumah Kaca (GRK) terverifikasi korporasi.
 */
final class GhgInventoryReportDTO
{
    /**
     * @param  array<string, int>  $scope1DetailsKgCo2e
     * @param  array<string, int>  $scope2DetailsKgCo2e
     * @param  array<string, int>  $scope3DetailsKgCo2e
     */
    public function __construct(
        public readonly string $reportingPeriod,
        public readonly string $entityIdentifier,
        public readonly int $grossScope1EmissionsKgCo2e,
        public readonly int $grossScope2LocationEmissionsKgCo2e,
        public readonly int $recDeductionsKgCo2e,
        public readonly int $netScope2MarketEmissionsKgCo2e,
        public readonly int $grossScope3EmissionsKgCo2e,
        public readonly int $carbonOffsetsRetiredKgCo2e,
        public readonly int $netGroupCarbonBalanceKgCo2e,
        public readonly array $scope1DetailsKgCo2e,
        public readonly array $scope2DetailsKgCo2e,
        public readonly array $scope3DetailsKgCo2e,
        public readonly string $auditChecksumSha256,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reporting_period' => $this->reportingPeriod,
            'entity_identifier' => $this->entityIdentifier,
            'scope_1_direct_kg' => $this->grossScope1EmissionsKgCo2e,
            'scope_2_location_kg' => $this->grossScope2LocationEmissionsKgCo2e,
            'rec_deductions_kg' => $this->recDeductionsKgCo2e,
            'scope_2_market_kg' => $this->netScope2MarketEmissionsKgCo2e,
            'scope_3_indirect_kg' => $this->grossScope3EmissionsKgCo2e,
            'carbon_offsets_retired_kg' => $this->carbonOffsetsRetiredKgCo2e,
            'net_carbon_balance_kg' => $this->netGroupCarbonBalanceKgCo2e,
            'scope_1_breakdown' => $this->scope1DetailsKgCo2e,
            'scope_2_breakdown' => $this->scope2DetailsKgCo2e,
            'scope_3_breakdown' => $this->scope3DetailsKgCo2e,
            'audit_checksum_sha256' => $this->auditChecksumSha256,
        ];
    }
}

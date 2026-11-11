<?php

declare(strict_types=1);

namespace Modules\Shared\Contracts;

/**
 * Cross-module contract for vendor credit exposure tranche reporting (W8-D57).
 *
 * Implemented by business modules (Procurement, Logistics, TradeFinance, etc.)
 * to allow Core Access & Exposure Aggregator to compute consolidated counterparty risk
 * without creating circular domain dependencies.
 */
interface VendorExposureProviderInterface
{
    /**
     * Get the machine name of the originating business module.
     */
    public function getModuleIdentifier(): string;

    /**
     * Collect active exposure tranches for a specific vendor cluster code or tenant.
     *
     * @param  string  $vendorClusterCode  Corporate family or supplier cluster tax identifier
     * @param  string|null  $tenantId  Optional tenant scope filter
     * @return list<array{
     *     module: string,
     *     reference: string,
     *     amount_idr: int,
     *     status: string,
     *     currency?: string,
     *     counterparty_name?: string,
     *     maturity_date?: ?string,
     *     is_contingent?: bool
     * }>
     */
    public function collectExposureTranches(string $vendorClusterCode, ?string $tenantId = null): array;
}

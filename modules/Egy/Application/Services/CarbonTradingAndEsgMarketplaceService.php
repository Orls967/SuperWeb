<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Modules\Egy\Domain\Models\CarbonOrder;
use Modules\Egy\Domain\Models\CarbonProject;
use Modules\Egy\Domain\Models\CarbonTrade;
use Modules\Egy\Domain\Models\CbamDeclaration;
use Modules\Egy\Domain\Models\EsgMarketplaceListing;
use Modules\Egy\Domain\Models\EsgMarketplaceOrder;
use Modules\Egy\Domain\Models\RecCertificate;

/**
 * Service orkestrasi untuk pelaporan, monitoring, dan analisis data pasar karbon & ESG.
 */
final class CarbonTradingAndEsgMarketplaceService
{
    /**
     * @return array<string, mixed>
     */
    public function getCarbonExchangeOverview(): array
    {
        $projects = CarbonProject::with('batches')->get();
        $bids = CarbonOrder::where('side', 'BUY')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy('limit_price_minor_per_ton', 'desc')
            ->take(20)
            ->get();

        $asks = CarbonOrder::where('side', 'SELL')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy('limit_price_minor_per_ton', 'asc')
            ->take(20)
            ->get();

        $recentTrades = CarbonTrade::latest()->take(20)->get();
        $totalTradedKg = (int) CarbonTrade::sum('matched_kg_co2e');
        $totalTradedValueIdr = (int) CarbonTrade::sum('total_amount_idr');

        return [
            'projects' => $projects,
            'bids' => $bids,
            'asks' => $asks,
            'recent_trades' => $recentTrades,
            'total_traded_kg' => $totalTradedKg,
            'total_traded_value_idr' => $totalTradedValueIdr,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecInventoryOverview(): array
    {
        $certificates = RecCertificate::latest()->paginate(25);
        $totalMintedWh = (int) RecCertificate::sum('energy_wh');
        $totalRetiredWh = (int) RecCertificate::where('status', 'retired')->sum('energy_wh');
        $activeInventoryWh = max(0, $totalMintedWh - $totalRetiredWh);

        return [
            'certificates' => $certificates,
            'total_minted_wh' => $totalMintedWh,
            'total_retired_wh' => $totalRetiredWh,
            'active_inventory_wh' => $activeInventoryWh,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCbamDeclarationsOverview(): array
    {
        $declarations = CbamDeclaration::latest()->paginate(25);
        $totalProductKg = (int) CbamDeclaration::sum('product_tonnage_kg');
        $totalEmbeddedCo2eKg = (int) CbamDeclaration::sum('gross_embedded_emissions_kg_co2e');
        $totalLiabilityIdr = (int) CbamDeclaration::sum('total_cbam_liability_idr');

        return [
            'declarations' => $declarations,
            'total_product_kg' => $totalProductKg,
            'total_embedded_co2e_kg' => $totalEmbeddedCo2eKg,
            'total_liability_idr' => $totalLiabilityIdr,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEsgMarketplaceOverview(): array
    {
        $listings = EsgMarketplaceListing::with('orders')->where('status', 'active')->get();
        $recentOrders = EsgMarketplaceOrder::with('listing')->latest()->take(25)->get();
        $totalOrdersCount = EsgMarketplaceOrder::count();
        $totalRevenueIdr = (int) EsgMarketplaceOrder::sum('total_amount_idr');

        return [
            'listings' => $listings,
            'recent_orders' => $recentOrders,
            'total_orders_count' => $totalOrdersCount,
            'total_revenue_idr' => $totalRevenueIdr,
        ];
    }

    /**
     * Format data matriks ke format CSV audit.
     *
     * @param  array<string>  $headers
     * @param  array<array<mixed>>  $rows
     */
    public function formatAsCsv(array $headers, array $rows): string
    {
        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return '';
        }

        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv !== false ? $csv : '';
    }
}

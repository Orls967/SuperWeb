<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Services;

use Modules\Mall\Contracts\TenantSalesProvider;
use Modules\Mall\Domain\Enums\SalesReportSource;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\TenantSalesReport;

class TenantSalesService
{
    /**
     * @var array<TenantSalesProvider>
     */
    protected array $providers = [];

    /**
     * @param  iterable<TenantSalesProvider>  $providers
     */
    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }
    }

    public function registerProvider(TenantSalesProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Sinkronisasi data omzet dari provider terintegrasi (misal Resto / Bengkel).
     */
    public function syncMonthlySales(Lease $lease, string $periodMonth): ?TenantSalesReport
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($lease)) {
                $netSales = $provider->getMonthlySales($lease, $periodMonth);
                $txCount = $provider->getTransactionCount($lease, $periodMonth);

                return TenantSalesReport::updateOrCreate(
                    [
                        'lease_id' => $lease->id,
                        'period_month' => $periodMonth,
                    ],
                    [
                        'tenant_id' => $lease->tenant_id,
                        'gross_sales' => $netSales,
                        'net_sales' => $netSales,
                        'transaction_count' => $txCount,
                        'source' => SalesReportSource::INTEGRATED,
                        'reported_at' => now(),
                        'verified_at' => now(),
                        'notes' => 'Otomatis ditarik dari integrasi sistem internal.',
                    ]
                );
            }
        }

        return TenantSalesReport::where('lease_id', $lease->id)
            ->where('period_month', $periodMonth)
            ->first();
    }

    /**
     * Catat laporan omzet manual dari portal tenant.
     */
    public function recordManualSales(
        Lease $lease,
        string $periodMonth,
        int $grossSales,
        int $netSales,
        int $txCount = 0,
        ?string $notes = null
    ): TenantSalesReport {
        return TenantSalesReport::updateOrCreate(
            [
                'lease_id' => $lease->id,
                'period_month' => $periodMonth,
            ],
            [
                'tenant_id' => $lease->tenant_id,
                'gross_sales' => $grossSales,
                'net_sales' => $netSales,
                'transaction_count' => $txCount,
                'source' => SalesReportSource::MANUAL,
                'reported_at' => now(),
                'notes' => $notes,
            ]
        );
    }
}

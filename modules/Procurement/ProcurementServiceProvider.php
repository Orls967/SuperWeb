<?php

declare(strict_types=1);

namespace Modules\Procurement;

use Illuminate\Support\ServiceProvider;
use Modules\Procurement\Application\Services\MrpRequisitionProposer;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Application\Services\ReceivingService;
use Modules\Procurement\Console\Commands\AuditProcurementCommand;
use Modules\Procurement\Console\Commands\ScanVmiReorderCommand;
use Modules\Procurement\Contracts\MrpRequisitionProposer as MrpRequisitionProposerContract;
use Modules\Procurement\Infrastructure\Access\ProcurementVendorExposureProvider;
use Modules\Procurement\Infrastructure\Api\PurchaseOrderResourceProvider;
use Modules\Procurement\Infrastructure\Edi\ProcurementEdiInvoiceHandler;
use Modules\Procurement\Infrastructure\Retention\ProcurementPoVersionRetentionProvider;
use Modules\Procurement\Infrastructure\Tenancy\ProcurementTenantScopedModelProvider;
use Modules\Procurement\Infrastructure\Treasury\ProcurementFxMonetaryItemFeed;
use Modules\Shared\Application\MenuRegistry;

class ProcurementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProcurementService::class);
        $this->app->singleton(ReceivingService::class);
        $this->app->bind(MrpRequisitionProposerContract::class, MrpRequisitionProposer::class);

        $this->app->tag(PurchaseOrderResourceProvider::class, 'core.api_resource_provider');
        $this->app->tag(ProcurementEdiInvoiceHandler::class, 'core.edi_document_handler');
        $this->app->tag(ProcurementTenantScopedModelProvider::class, 'core.tenant_scoped_model_provider');
        $this->app->tag(ProcurementPoVersionRetentionProvider::class, 'core.retention_archive_provider');
        $this->app->tag(ProcurementVendorExposureProvider::class, 'core.vendor_exposure_provider');
        $this->app->tag(ProcurementFxMonetaryItemFeed::class, 'treasury.fx_monetary_item_feed');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views/procurement', 'procurement');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([AuditProcurementCommand::class, ScanVmiReorderCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Procurement',
            route: 'procurement.dashboard',
            icon: 'shopping-cart',
            roles: ['admin', 'procurement'],
            order: 47,
            group: 'Supply Chain',
            activePattern: 'procurement*',
        );
    }
}

<?php

declare(strict_types=1);

namespace Modules\Distribution;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Distribution\Application\Services\DistributionFulfilmentService;
use Modules\Distribution\Application\Services\DistributionService;
use Modules\Distribution\Console\Commands\AuditDistributionCommand;
use Modules\Distribution\Contracts\OpenDemandProvider;
use Modules\Distribution\Domain\Models\Distributor;
use Modules\Distribution\Infrastructure\Adapters\OpenDemandProviderAdapter;
use Modules\Distribution\Infrastructure\Api\DistOrderResourceProvider;
use Modules\Distribution\Infrastructure\Bulk\HetPriceBulkImportTarget;
use Modules\Distribution\Infrastructure\Edi\DistributionEdiOrderHandler;
use Modules\Distribution\Infrastructure\Tenancy\DistributionTenantScopedModelProvider;
use Modules\Distribution\Policies\DistributorPolicy;
use Modules\Shared\Application\MenuRegistry;

class DistributionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DistributionService::class);
        $this->app->singleton(DistributionFulfilmentService::class);
        $this->app->bindIf(OpenDemandProvider::class, OpenDemandProviderAdapter::class);

        $this->app->tag(DistOrderResourceProvider::class, 'core.api_resource_provider');
        $this->app->tag(DistributionEdiOrderHandler::class, 'core.edi_document_handler');
        $this->app->tag(HetPriceBulkImportTarget::class, 'core.bulk_import_target');
        $this->app->tag(DistributionTenantScopedModelProvider::class, 'core.tenant_scoped_model_provider');
    }

    public function boot(): void
    {
        Gate::policy(Distributor::class, DistributorPolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/distribution')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/distribution', 'distribution');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditDistributionCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Distributor',
            route: 'distribution.index',
            icon: 'users',
            roles: ['admin', 'distributor', 'procurement', 'auditor'],
            order: 56,
            group: 'Logistik',
            activePattern: 'distribution*',
        );
    }
}

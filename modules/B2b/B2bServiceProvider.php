<?php

declare(strict_types=1);

namespace Modules\B2b;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\B2b\Console\Commands\AuditB2bCommand;
use Modules\B2b\Infrastructure\Api\WholesaleCatalogResourceProvider;
use Modules\B2b\Infrastructure\Bulk\B2bCatalogBulkImportTarget;

class B2bServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            AuditB2bCommand::class,
        ]);

        $this->app->tag(WholesaleCatalogResourceProvider::class, 'core.api_resource_provider');
        $this->app->tag(B2bCatalogBulkImportTarget::class, 'core.bulk_import_target');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'b2b');

        if (file_exists(__DIR__.'/routes/web.php')) {
            Route::middleware('web')
                ->group(__DIR__.'/routes/web.php');
        }
    }
}

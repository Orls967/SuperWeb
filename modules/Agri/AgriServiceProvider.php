<?php

declare(strict_types=1);

namespace Modules\Agri;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Agri\Application\Services\AgriFarmerLoanOffsetService;
use Modules\Agri\Application\Services\AgriNdviIndexReaderService;
use Modules\Agri\Application\Services\HarvestLotSourceService;
use Modules\Agri\Console\Commands\AuditAgriCommand;
use Modules\Agri\Contracts\FarmerLoanOffset;
use Modules\Agri\Contracts\HarvestLotSource;
use Modules\Agri\Contracts\NdviIndexReader;

class AgriServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FarmerLoanOffset::class, AgriFarmerLoanOffsetService::class);
        $this->app->bind(HarvestLotSource::class, HarvestLotSourceService::class);
        $this->app->bind(NdviIndexReader::class, AgriNdviIndexReaderService::class);

        $this->commands([
            AuditAgriCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'agri');

        if (file_exists(__DIR__.'/routes/web.php')) {
            Route::middleware('web')
                ->group(__DIR__.'/routes/web.php');
        }
    }
}

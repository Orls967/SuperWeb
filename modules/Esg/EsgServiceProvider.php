<?php

declare(strict_types=1);

namespace Modules\Esg;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Esg\Application\Services\CarbonCreditIssuerService;
use Modules\Esg\Application\Services\CarbonCreditRetirementRecorderService;
use Modules\Esg\Application\Services\EmissionBenefitRecorderService;
use Modules\Esg\Application\Services\VerifiedEmissionReaderService;
use Modules\Esg\Console\Commands\AuditEsgCommand;
use Modules\Esg\Contracts\CarbonCreditIssuer;
use Modules\Esg\Contracts\CarbonCreditRetirementRecorder;
use Modules\Esg\Contracts\EmissionBenefitRecorder;
use Modules\Esg\Contracts\VerifiedEmissionReader;

class EsgServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CarbonCreditIssuer::class, CarbonCreditIssuerService::class);
        $this->app->singleton(CarbonCreditRetirementRecorder::class, CarbonCreditRetirementRecorderService::class);
        $this->app->singleton(EmissionBenefitRecorder::class, EmissionBenefitRecorderService::class);
        $this->app->singleton(VerifiedEmissionReader::class, VerifiedEmissionReaderService::class);

        $this->commands([
            AuditEsgCommand::class,
        ]);

        $this->app->tag(\Modules\Esg\Infrastructure\Tenancy\EsgTenantScopedModelProvider::class, 'core.tenant_scoped_model_provider');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'esg');

        if (file_exists(__DIR__.'/routes/web.php')) {
            Route::middleware('web')
                ->group(__DIR__.'/routes/web.php');
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Edu;

use Illuminate\Support\ServiceProvider;
use Modules\Edu\Application\Services\AcademyAndCertificationService;
use Modules\Edu\Console\Commands\EduAuditCommand;
use Modules\Shared\Application\MenuRegistry;

class EduServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AcademyAndCertificationService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'edu');

        if ($this->app->runningInConsole()) {
            $this->commands([
                EduAuditCommand::class,
            ]);
        }

        if ($this->app->bound(MenuRegistry::class)) {
            $this->app->make(MenuRegistry::class)->addItem(
                label: 'Pendidikan & Talent',
                route: 'education.index',
                icon: 'academic-cap',
                roles: ['instructor', 'edu_admin', 'cert_officer', 'corp_lnd'],
                order: 160,
            );
        }
    }
}

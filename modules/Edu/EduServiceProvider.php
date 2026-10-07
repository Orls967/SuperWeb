<?php

declare(strict_types=1);

namespace Modules\Edu;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;

class EduServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings if needed
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

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

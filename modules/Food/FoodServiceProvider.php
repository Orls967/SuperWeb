<?php

declare(strict_types=1);

namespace Modules\Food;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Food\Console\Commands\AuditFoodCommand;

class FoodServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            AuditFoodCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'food');

        if (file_exists(__DIR__.'/routes/web.php')) {
            Route::middleware('web')
                ->group(__DIR__.'/routes/web.php');
        }
    }
}

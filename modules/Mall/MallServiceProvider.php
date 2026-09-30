<?php

declare(strict_types=1);

namespace Modules\Mall;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;
use Modules\Mall\Domain\Models\Zone;
use Modules\Shared\Application\MenuRegistry;

class MallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'mall');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        Relation::morphMap([
            'mall_property' => Property::class,
            'mall_zone' => Zone::class,
            'mall_unit' => Unit::class,
            'mall_tenant' => Tenant::class,
            'mall_lease' => Lease::class,
        ]);

        if ($this->app->bound(MenuRegistry::class)) {
            $registry = $this->app->make(MenuRegistry::class);

            $registry->addItem(
                label: 'Site Plan & Unit',
                route: 'mall.site-plan.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>',
                roles: ['admin', 'mall_manager', 'leasing_agent'],
                order: 70,
                group: 'Properti (Duta Mall)',
                activePattern: 'mall/site-plan*'
            );

            $registry->addItem(
                label: 'Kontrak Sewa (Leasing)',
                route: 'mall.leases.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>',
                roles: ['admin', 'mall_manager', 'leasing_agent'],
                order: 71,
                group: 'Properti (Duta Mall)',
                activePattern: 'mall/leases*'
            );

            $registry->addItem(
                label: 'Tenant Mitra Mall',
                route: 'mall.tenants.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>',
                roles: ['admin', 'mall_manager', 'leasing_agent'],
                order: 72,
                group: 'Properti (Duta Mall)',
                activePattern: 'mall/tenants*'
            );

            $registry->addItem(
                label: 'Direktori Toko Publik',
                route: 'mall.directory.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>',
                roles: ['admin', 'mall_manager', 'leasing_agent', 'customer'],
                order: 73,
                group: 'Properti (Duta Mall)',
                activePattern: 'mall/directory*'
            );
        }
    }
}

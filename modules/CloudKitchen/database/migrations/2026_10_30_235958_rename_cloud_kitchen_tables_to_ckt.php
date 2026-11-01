<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resto_ck_kitchens') && ! Schema::hasTable('ckt_kitchens')) {
            Schema::rename('resto_ck_kitchens', 'ckt_kitchens');
        }

        if (Schema::hasTable('resto_catering_subscriptions') && ! Schema::hasTable('ckt_catering_subscriptions')) {
            Schema::rename('resto_catering_subscriptions', 'ckt_catering_subscriptions');
        }

        if (Schema::hasTable('resto_ck_catering_orders') && ! Schema::hasTable('ckt_catering_orders')) {
            Schema::rename('resto_ck_catering_orders', 'ckt_catering_orders');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ckt_catering_orders') && ! Schema::hasTable('resto_ck_catering_orders')) {
            Schema::rename('ckt_catering_orders', 'resto_ck_catering_orders');
        }

        if (Schema::hasTable('ckt_catering_subscriptions') && ! Schema::hasTable('resto_catering_subscriptions')) {
            Schema::rename('ckt_catering_subscriptions', 'resto_catering_subscriptions');
        }

        if (Schema::hasTable('ckt_kitchens') && ! Schema::hasTable('resto_ck_kitchens')) {
            Schema::rename('ckt_kitchens', 'resto_ck_kitchens');
        }
    }
};

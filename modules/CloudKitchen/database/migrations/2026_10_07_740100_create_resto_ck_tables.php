<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resto_ck_kitchens')) {
            Schema::create('resto_ck_kitchens', function (Blueprint $table) {
                $table->id();
                $table->string('kitchen_code')->unique();
                $table->string('name');
                $table->string('type'); // satellite, central, licensed_outlet
                $table->unsignedInteger('hourly_capacity')->default(100);
                $table->unsignedInteger('current_hourly_load')->default(0);
                $table->decimal('service_radius_km', 5, 2)->default(10.0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('resto_catering_subscriptions')) {
            Schema::create('resto_catering_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('subscription_code')->unique();
                $table->foreignId('user_id')->constrained('users');
                $table->string('package_name'); // lunch_5d, full_board_7d
                $table->unsignedInteger('daily_quota')->default(1);
                $table->unsignedInteger('used_quota_today')->default(0);
                $table->unsignedBigInteger('monthly_fee_idr');
                $table->unsignedBigInteger('daily_meal_price_idr');
                $table->date('starts_at');
                $table->date('expires_at');
                $table->string('status')->default('active'); // active, suspended, cancelled
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('resto_ck_catering_orders')) {
            Schema::create('resto_ck_catering_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_code')->unique();
                $table->foreignId('subscription_id')->constrained('resto_catering_subscriptions')->cascadeOnDelete();
                $table->foreignId('kitchen_id')->constrained('resto_ck_kitchens');
                $table->date('delivery_date');
                $table->string('meal_slot'); // lunch, dinner
                $table->string('status')->default('dispatched'); // dispatched, delivered, refunded_closed
                $table->unsignedBigInteger('meal_cost_idr');
                $table->string('idempotency_key')->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resto_ck_catering_orders');
        Schema::dropIfExists('resto_catering_subscriptions');
        Schema::dropIfExists('resto_ck_kitchens');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. mall_properties
        Schema::create('mall_properties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('address');
            $table->string('city', 100);
            $table->integer('total_floors')->default(4);
            $table->decimal('gla_sqm', 10, 2)->default(50000.00); // Gross Leasable Area
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. mall_zones
        Schema::create('mall_zones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('floor', 20); // LG, GF, L1, L2, L3
            $table->string('color_code', 20)->default('#10b981');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 3. mall_units
        Schema::create('mall_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('mall_zones')->nullOnDelete();
            $table->string('unit_number', 30);
            $table->string('floor', 20);
            $table->decimal('area_sqm', 8, 2);
            $table->unsignedBigInteger('base_rent_rate_per_sqm')->default(250000); // Rp/sqm/bulan
            $table->unsignedBigInteger('service_charge_per_sqm')->default(85000); // Rp/sqm/bulan
            $table->string('status', 30)->default('available'); // available, reserved, leased, maintenance
            $table->json('coordinates')->nullable(); // {"x": 10, "y": 20, "w": 30, "h": 20}
            $table->timestamps();

            $table->unique(['property_id', 'unit_number']);
            $table->index(['property_id', 'floor', 'status']);
        });

        // 4. mall_tenants
        Schema::create('mall_tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('company_name', 150);
            $table->string('brand_name', 150);
            $table->string('pic_name', 100);
            $table->string('pic_phone', 30);
            $table->string('pic_email', 100);
            $table->string('npwp', 30)->nullable();
            $table->string('category', 30)->default('fnb'); // fnb, fashion, automotive, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. mall_leases
        Schema::create('mall_leases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('lease_number', 50)->unique();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('mall_units')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('mall_tenants')->cascadeOnDelete();
            $table->string('rent_model', 30)->default('fixed'); // fixed, revenue_share, greater_of
            $table->date('start_date');
            $table->date('end_date');
            $table->date('fit_out_start_date')->nullable();
            $table->integer('fit_out_days')->default(30);
            $table->integer('billing_day')->default(1);
            $table->integer('grace_days')->default(7);
            $table->decimal('penalty_rate_daily_percent', 5, 2)->default(0.10);
            $table->unsignedBigInteger('base_monthly_rent')->default(0);
            $table->unsignedBigInteger('service_charge_monthly')->default(0);
            $table->decimal('annual_escalation_percent', 5, 2)->default(5.00);
            $table->decimal('revenue_share_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('security_deposit_amount')->default(0);
            $table->string('deposit_status', 30)->default('unpaid'); // unpaid, held, partially_applied, refunded
            $table->string('status', 30)->default('draft'); // draft, active, suspended, terminated, expired
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_leases');
        Schema::dropIfExists('mall_tenants');
        Schema::dropIfExists('mall_units');
        Schema::dropIfExists('mall_zones');
        Schema::dropIfExists('mall_properties');
    }
};

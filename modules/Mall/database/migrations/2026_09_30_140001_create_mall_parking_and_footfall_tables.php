<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. mall_parking_zones
        Schema::create('mall_parking_zones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->string('vehicle_type', 20)->default('car'); // car, motorcycle, truck
            $table->integer('total_capacity')->default(100);
            $table->integer('current_occupancy')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['property_id', 'code']);
        });

        // 2. mall_parking_tariffs
        Schema::create('mall_parking_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->string('vehicle_type', 20); // car, motorcycle, truck
            $table->integer('grace_period_minutes')->default(15);
            $table->unsignedBigInteger('first_hour_rate')->default(5000);
            $table->unsignedBigInteger('subsequent_hour_rate')->default(3000);
            $table->unsignedBigInteger('max_daily_rate')->default(50000);
            $table->unsignedBigInteger('lost_ticket_penalty')->default(50000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['property_id', 'vehicle_type']);
        });

        // 3. mall_parking_members
        Schema::create('mall_parking_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('mall_tenants')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('core_vehicles')->nullOnDelete();
            $table->string('member_number', 50)->unique();
            $table->string('plate_number', 20);
            $table->string('vehicle_type', 20)->default('car');
            $table->unsignedBigInteger('monthly_price')->default(150000);
            $table->boolean('auto_renew')->default(true);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('active'); // active, expired, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plate_number', 'status']);
        });

        // 4. mall_parking_sessions
        Schema::create('mall_parking_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('ticket_number', 50)->unique();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->foreignId('parking_zone_id')->constrained('mall_parking_zones')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('mall_parking_members')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('core_vehicles')->nullOnDelete();
            $table->string('plate_number', 20);
            $table->string('vehicle_type', 20)->default('car');
            $table->string('entry_gate', 50)->default('Gate Masuk 1');
            $table->string('exit_gate', 50)->nullable();
            $table->timestamp('entry_time');
            $table->timestamp('exit_time')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->unsignedBigInteger('base_fee')->default(0);
            $table->unsignedBigInteger('penalty_fee')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->foreignId('validated_by_tenant_id')->nullable()->constrained('mall_tenants')->nullOnDelete();
            $table->string('validation_reference', 100)->nullable();
            // Jumlah jam pertama yang ditanggung tenant; nominalnya dihitung saat kendaraan keluar
            $table->unsignedTinyInteger('validation_free_hours')->default(0);
            $table->unsignedBigInteger('validation_spend_amount')->default(0);
            // Nominal validasi ditagihkan ke tenant lewat invoice bulanan (null = belum ditagih)
            $table->foreignId('validation_invoice_id')->nullable()->constrained('mall_invoices')->nullOnDelete();
            $table->unsignedBigInteger('total_fee')->default(0);
            $table->string('payment_method', 30)->nullable(); // cash, wallet, member_free, tenant_free
            $table->string('payment_status', 30)->default('unpaid'); // unpaid, paid, waived
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->boolean('is_lost_ticket')->default(false);
            $table->string('status', 30)->default('active'); // active, completed, lost
            $table->timestamps();

            $table->index(['property_id', 'status', 'entry_time']);
            $table->index(['plate_number', 'status']);
            // Menagihkan validasi parkir ke tenant per periode tanpa memindai seluruh tabel
            $table->index(['validated_by_tenant_id', 'validation_invoice_id', 'exit_time'], 'mall_parking_validation_billing_idx');
        });

        // 5. mall_footfall_counts
        Schema::create('mall_footfall_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('mall_properties')->cascadeOnDelete();
            $table->date('date');
            $table->integer('hour'); // 0 - 23
            $table->string('gate_name', 50);
            $table->integer('in_count')->default(0);
            $table->integer('out_count')->default(0);
            $table->timestamps();

            $table->unique(['property_id', 'date', 'hour', 'gate_name']);
            $table->index(['property_id', 'date']);
        });

        // 6. Parameter validasi parkir per kontrak sewa:
        //    tenant menanggung N jam pertama bila pelanggan berbelanja minimal X rupiah.
        Schema::table('mall_leases', function (Blueprint $table) {
            $table->unsignedTinyInteger('parking_validation_hours')->default(0)->after('revenue_share_percent');
            $table->unsignedBigInteger('parking_validation_min_spend')->default(0)->after('parking_validation_hours');
        });
    }

    public function down(): void
    {
        Schema::table('mall_leases', function (Blueprint $table) {
            $table->dropColumn(['parking_validation_hours', 'parking_validation_min_spend']);
        });

        Schema::dropIfExists('mall_footfall_counts');
        Schema::dropIfExists('mall_parking_sessions');
        Schema::dropIfExists('mall_parking_members');
        Schema::dropIfExists('mall_parking_tariffs');
        Schema::dropIfExists('mall_parking_zones');
    }
};

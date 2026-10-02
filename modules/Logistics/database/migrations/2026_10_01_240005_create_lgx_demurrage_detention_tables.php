<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_dd_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('lgx_locations')->cascadeOnDelete(); // null = semua lokasi
            $table->string('kind', 16); // demurrage, detention
            $table->string('size_type', 8)->nullable(); // null = semua ukuran
            $table->unsignedSmallInteger('free_days')->default(3);
            $table->unsignedBigInteger('rate_per_day_idr');
            $table->unsignedSmallInteger('escalation_after_days')->nullable(); // hari berbayar ke-N+1 memakai tarif eskalasi
            $table->unsignedBigInteger('escalated_rate_per_day_idr')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'is_active']);
        });

        Schema::table('lgx_invoices', function (Blueprint $table) {
            $table->string('kind', 16)->default('freight')->after('invoice_number'); // freight, dd
            $table->index(['shipper_id', 'billing_period', 'kind']);
        });

        Schema::create('lgx_container_dwells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('lgx_containers')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained('lgx_shipments')->cascadeOnDelete();
            $table->foreignId('shipper_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->foreignId('tariff_id')->nullable()->constrained('lgx_dd_tariffs')->nullOnDelete();
            $table->string('kind', 16);
            $table->string('status', 8)->default('open'); // open, closed
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('billable_days')->default(0);
            $table->unsignedBigInteger('accrued_amount_idr')->default(0);
            $table->date('last_accrued_on')->nullable(); // tanggal lokal lokasi
            $table->foreignId('invoice_id')->nullable()->constrained('lgx_invoices')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'kind']);
            $table->index(['shipper_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_container_dwells');
        Schema::table('lgx_invoices', function (Blueprint $table) {
            $table->dropIndex(['shipper_id', 'billing_period', 'kind']);
            $table->dropColumn('kind');
        });
        Schema::dropIfExists('lgx_dd_tariffs');
    }
};

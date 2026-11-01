<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egy_smart_meters', function (Blueprint $table) {
            $table->unsignedBigInteger('modulus')->nullable()->after('tariff_category');
            $table->string('device_token', 64)->nullable()->after('modulus');
        });

        Schema::table('egy_meter_readings', function (Blueprint $table) {
            $table->timestamp('reading_at')->nullable()->after('meter_id');
            $table->unsignedBigInteger('cumulative_wh')->default(0)->after('reading_at');
            $table->unsignedBigInteger('delta_wh')->default(0)->after('cumulative_wh');
            $table->string('interval_status', 32)->default('RAW')->after('delta_wh');
            $table->boolean('is_wbp')->default(false)->after('is_peak_hour');
            $table->unique(['meter_id', 'reading_at'], 'egy_meter_readings_meter_reading_at_unique');
        });

        Schema::create('egy_utility_invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->string('window_type', 16); // WBP, LWBP
            $table->unsignedBigInteger('wh_consumed');
            $table->unsignedBigInteger('rate_per_kwh_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();

            $table->index('invoice_id');
        });

        Schema::table('egy_utility_consolidated_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('total_wh')->default(0)->after('billing_period');
        });
    }

    public function down(): void
    {
        Schema::table('egy_utility_consolidated_invoices', function (Blueprint $table) {
            $table->dropColumn('total_wh');
        });

        Schema::dropIfExists('egy_utility_invoice_lines');

        Schema::table('egy_meter_readings', function (Blueprint $table) {
            $table->dropUnique('egy_meter_readings_meter_reading_at_unique');
            $table->dropColumn(['reading_at', 'cumulative_wh', 'delta_wh', 'interval_status', 'is_wbp']);
        });

        Schema::table('egy_smart_meters', function (Blueprint $table) {
            $table->dropColumn(['modulus', 'device_token']);
        });
    }
};

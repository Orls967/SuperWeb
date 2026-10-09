<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_master_golden_records', function (Blueprint $table) {
            $table->id();
            $table->string('master_domain'); // customer, vendor, product, asset, chart_of_accounts, location, employee (456.1)
            $table->string('golden_id')->unique();
            $table->json('canonical_data');
            $table->string('survivorship_rule')->default('most_recent_authoritative'); // 456.1, 456.4
            $table->boolean('is_merged')->default(false);
            $table->string('merged_into_golden_id')->nullable(); // 456.2, 456.5 reversible merge
            $table->json('pre_merge_backup_state')->nullable(); // 456.5 reversible state
            $table->timestamps();
        });

        Schema::create('int_master_data_propagations', function (Blueprint $table) {
            $table->id();
            $table->string('propagation_code')->unique();
            $table->string('golden_id');
            $table->string('downstream_consumer_system'); // CRM, ERP, WMS, BILLING (456.2, 456.6)
            $table->boolean('is_propagated')->default(false);
            $table->integer('retry_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_master_data_propagations');
        Schema::dropIfExists('int_master_golden_records');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 153.1: Critical items multi-sourcing registry
        Schema::create('scm_critical_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->string('item_name');
            $table->string('category');
            $table->boolean('is_critical')->default(true);
            $table->integer('min_supplier_count')->default(2);
            $table->boolean('is_single_sourced')->default(false);
            $table->timestamps();
        });

        Schema::create('scm_item_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('item_code');
            $table->string('supplier_code');
            $table->string('region_code'); // APAC, EMEA, AMERICAS
            $table->decimal('allocation_pct', 5, 2)->default(50.00);
            $table->boolean('is_qualified')->default(true);
            $table->timestamps();
            $table->unique(['item_code', 'supplier_code']);
        });

        // 153.2: Geopolitical risk feeds & disruption war room
        Schema::create('scm_geopolitical_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_code')->unique();
            $table->string('region_code');
            $table->string('event_type'); // SANCTION, PORT_BLOCKADE, TARIFF_WAR
            $table->string('severity'); // MEDIUM, HIGH, SEVERE
            $table->text('blast_radius_desc');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 153.3: Strategic buffer stocks
        Schema::create('scm_strategic_buffers', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->integer('base_safety_stock');
            $table->integer('risk_adjusted_buffer');
            $table->decimal('carrying_cost_idr', 18, 2);
            $table->boolean('treasury_approved')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scm_strategic_buffers');
        Schema::dropIfExists('scm_geopolitical_events');
        Schema::dropIfExists('scm_item_suppliers');
        Schema::dropIfExists('scm_critical_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fsh_collections', function (Blueprint $table) {
            $table->id();
            $table->string('collection_code', 64)->unique();
            $table->string('name', 128);
            $table->string('season', 32); // SS26, FW26, RESORT26
            $table->unsignedSmallInteger('year');
            $table->string('status', 32)->default('DRAFT'); // DRAFT, APPROVED, IN_PRODUCTION, COMPLETED
            $table->timestamps();
        });

        Schema::create('fsh_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('fsh_collections')->cascadeOnDelete();
            $table->string('sample_code', 64)->unique();
            $table->string('article_name', 128);
            $table->string('tech_pack_ref', 64)->nullable();
            $table->string('approval_status', 32)->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fsh_buy_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('fsh_collections')->cascadeOnDelete();
            $table->string('plan_code', 64)->unique();
            $table->unsignedInteger('target_total_units');
            $table->unsignedBigInteger('budget_idr');
            $table->string('status', 32)->default('OPEN');
            $table->timestamps();
        });

        Schema::create('fsh_production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buy_plan_id')->constrained('fsh_buy_plans')->cascadeOnDelete();
            $table->foreignId('sample_id')->constrained('fsh_samples')->cascadeOnDelete();
            $table->string('po_number', 64)->unique();
            $table->string('factory_name', 128);
            $table->unsignedInteger('total_ordered_units');
            $table->unsignedBigInteger('total_cost_idr');
            $table->string('status', 32)->default('ISSUED'); // ISSUED, IN_PRODUCTION, DELIVERED
            $table->timestamps();
        });

        Schema::create('fsh_production_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('fsh_production_orders')->cascadeOnDelete();
            $table->string('size_code', 16); // S, M, L, XL
            $table->string('color_code', 32); // NAVY, BLACK, WHITE
            $table->unsignedInteger('allocated_units');
            $table->timestamps();

            $table->index(['production_order_id', 'size_code', 'color_code'], 'fsh_alloc_po_size_color_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fsh_production_allocations');
        Schema::dropIfExists('fsh_production_orders');
        Schema::dropIfExists('fsh_buy_plans');
        Schema::dropIfExists('fsh_samples');
        Schema::dropIfExists('fsh_collections');
    }
};

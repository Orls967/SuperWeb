<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_supply_chain_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_code')->unique();
            $table->string('category_tier'); // strategic, tactical, operational (461.2)
            $table->boolean('dual_sourcing_required')->default(false); // 461.1 policy
            $table->date('strategy_reviewed_at'); // 461.1, 461.4 annual review
            $table->timestamps();
        });

        Schema::create('int_supply_chain_sourcing_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_code')->unique();
            $table->string('category_code');
            $table->integer('qualified_suppliers_count');
            $table->boolean('policy_deviation_flagged')->default(false); // 461.5 edge case
            $table->string('status')->default('compliant'); // compliant, deviation_flagged
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_supply_chain_sourcing_events');
        Schema::dropIfExists('int_supply_chain_categories');
    }
};

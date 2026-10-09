<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_supply_chain_scorecards', function (Blueprint $table) {
            $table->id();
            $table->string('scorecard_code')->unique();
            $table->string('domain'); // supplier, manufacturing, warehouse, logistics, channel, customer
            $table->string('period'); // e.g. 2026-Q3
            $table->decimal('otif_rate', 5, 2); // on-time-in-full %
            $table->decimal('defect_ppm', 10, 2);
            $table->string('data_source_metric'); // 414.4 trace to source
            $table->boolean('target_met')->default(true);
            $table->timestamps();
        });

        Schema::create('ops_supply_corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_id')->constrained('ops_supply_chain_scorecards')->cascadeOnDelete();
            $table->string('action_code')->unique();
            $table->text('root_cause');
            $table->text('countermeasure');
            $table->string('owner');
            $table->boolean('effectiveness_verified')->default(false); // 414.4
            $table->string('status')->default('open'); // open, verified, closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_supply_corrective_actions');
        Schema::dropIfExists('ops_supply_chain_scorecards');
    }
};

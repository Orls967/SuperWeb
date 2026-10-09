<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_control_tower_decision_loops', function (Blueprint $table) {
            $table->id();
            $table->string('decision_code')->unique();
            $table->string('recommendation_title');
            $table->boolean('requires_delegated_approval')->default(true);
            $table->boolean('approval_granted')->default(false);
            $table->boolean('decision_executed')->default(false); // 388.2, 388.4, 388.6 Risk
            $table->string('approval_escalation_tier')->default('TIER_1'); // 388.5 Edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_control_tower_decision_loops');
    }
};

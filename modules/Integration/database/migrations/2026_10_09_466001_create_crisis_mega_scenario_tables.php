<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_crisis_mega_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('crisis_code')->unique();
            $table->json('concurrent_shocks'); // flood, blackout, cyber, health, commodity (466.1)
            $table->boolean('sandbox_isolated')->default(true); // 466.6 risk (data baseline protection)
            $table->decimal('money_invariant_variance', 15, 2)->default(0.00); // 466.2 must hold = 0
            $table->decimal('stock_invariant_variance', 15, 2)->default(0.00);
            $table->decimal('rto_achieved_minutes', 10, 2);
            $table->boolean('escalated_to_war_room')->default(false); // 466.5 edge case
            $table->string('status')->default('active'); // active, recovered, war_room_escalated
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_crisis_mega_scenarios');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_app_mini_app_registry', function (Blueprint $table) {
            $table->id();
            $table->string('mini_app_code')->unique();
            $table->string('partner_id')->index();
            $table->string('app_name');
            $table->string('sandbox_status')->default('CERTIFIED'); // CERTIFIED, CRASHED_ISOLATED (315.5)
            $table->decimal('platform_revenue_share_pct', 5, 2)->default(15.00); // 315.1 & 315.4
            $table->decimal('partner_revenue_share_pct', 5, 2)->default(85.00); // 315.4 Sum = 100%
            $table->boolean('data_portability_exit_clause_agreed')->default(true); // 315.6 Anti-lock-in
            $table->boolean('is_isolated_on_failure')->default(true); // 315.5 Edge case
            $table->timestamps();
        });

        Schema::create('super_app_session_handoffs', function (Blueprint $table) {
            $table->id();
            $table->string('handoff_token')->unique();
            $table->string('user_id')->index();
            $table->string('source_app_code');
            $table->string('target_mini_app_code');
            $table->boolean('user_consent_granted')->default(false); // 315.2 Consent-aware
            $table->json('scoped_context_payload'); // 315.2 & 315.4 Scope preservation
            $table->boolean('is_consumed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_app_session_handoffs');
        Schema::dropIfExists('super_app_mini_app_registry');
    }
};

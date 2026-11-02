<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ins_policy_sequences')) {
            Schema::create('ins_policy_sequences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('ins_products')->cascadeOnDelete();
                $table->unsignedSmallInteger('year');
                $table->unsignedBigInteger('last_sequence')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'year']);
            });
        }

        if (! Schema::hasTable('ins_policy_earnings')) {
            Schema::create('ins_policy_earnings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('policy_id')->constrained('ins_policies')->cascadeOnDelete();
                $table->unsignedInteger('day_index')->default(1);
                $table->date('earning_date');
                $table->unsignedBigInteger('daily_amount_idr');
                $table->unsignedBigInteger('cumulative_earned_idr');
                $table->string('idempotency_key')->unique();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['policy_id', 'day_index']);
                $table->index(['policy_id', 'earning_date']);
            });
        }

        if (! Schema::hasTable('ins_parametric_oracles')) {
            Schema::create('ins_parametric_oracles', function (Blueprint $table) {
                $table->id();
                $table->string('oracle_code')->unique();
                $table->string('name');
                $table->string('source_type'); // BMKG_WEATHER, FLIGHT_RADAR, IOT_TELEMATICS
                $table->boolean('is_active')->default(true);
                $table->json('api_endpoint_config')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_parametric_oracles');
        Schema::dropIfExists('ins_policy_earnings');
        Schema::dropIfExists('ins_policy_sequences');
    }
};

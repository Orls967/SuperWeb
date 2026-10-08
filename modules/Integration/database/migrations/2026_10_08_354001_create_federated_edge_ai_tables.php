<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edge_ai_device_fleet_rollouts', function (Blueprint $table) {
            $table->id();
            $table->string('rollout_code')->unique();
            $table->string('device_group'); // MINING_HAUL_FLEET, SMELTER_IOT_SENSORS
            $table->string('model_version');
            $table->string('prior_version');
            $table->boolean('privacy_check_passed')->default(false); // 354.2, 354.4, 354.6 Risk
            $table->boolean('is_rolled_back')->default(false);
            $table->timestamps();
        });

        Schema::create('edge_ai_inference_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_code')->unique();
            $table->string('device_id');
            $table->boolean('model_execution_error')->default(false);
            $table->boolean('deterministic_failsafe_engaged')->default(false); // 354.3, 354.4, 354.5 Edge case
            $table->boolean('central_alert_sent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edge_ai_inference_events');
        Schema::dropIfExists('edge_ai_device_fleet_rollouts');
    }
};

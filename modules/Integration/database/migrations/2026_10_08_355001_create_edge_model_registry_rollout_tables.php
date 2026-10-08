<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edge_model_registry_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_code')->unique();
            $table->string('site_name'); // VENUE, MINE, HOSPITAL, WAREHOUSE
            $table->string('hardware_class'); // ARM64_JETSON, X86_EDGE_SERVER
            $table->string('installed_model_version');
            $table->boolean('is_online')->default(true);
            $table->timestamps();
        });

        Schema::create('edge_device_staged_rollouts', function (Blueprint $table) {
            $table->id();
            $table->string('rollout_code')->unique();
            $table->string('device_code')->index();
            $table->string('target_model_version');
            $table->string('prior_model_version');
            $table->boolean('is_hardware_compatible')->default(true); // 355.4 & 355.5
            $table->boolean('automatic_rollback_executed')->default(false); // 355.5 Edge case
            $table->boolean('update_queued_offline')->default(false); // 355.4 & 355.6
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edge_device_staged_rollouts');
        Schema::dropIfExists('edge_model_registry_devices');
    }
};

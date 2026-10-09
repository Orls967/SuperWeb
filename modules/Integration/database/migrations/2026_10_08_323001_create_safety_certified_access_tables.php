<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_certified_access_permits', function (Blueprint $table) {
            $table->id();
            $table->string('permit_code')->unique();
            $table->string('worker_id')->index();
            $table->string('site_code');
            $table->string('area_or_equipment');
            $table->boolean('has_valid_certification')->default(false);
            $table->boolean('is_certification_expired')->default(false); // 323.1 & 323.4 Expiry-aware
            $table->boolean('site_induction_completed')->default(false);
            $table->boolean('supervisor_signed_off')->default(false);
            $table->boolean('is_emergency_permit')->default(false); // 323.5 Edge case
            $table->boolean('emergency_post_review_completed')->default(false); // 323.5
            $table->boolean('access_granted')->default(false); // 323.4
            $table->timestamps();
        });

        Schema::create('safety_stop_work_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code')->unique();
            $table->string('reporter_worker_id')->index();
            $table->string('site_code');
            $table->string('hazard_description');
            $table->boolean('restart_authorized')->default(false);
            $table->boolean('retaliatory_action_prevented')->default(true); // 323.3 & 323.4 Protection
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_stop_work_incidents');
        Schema::dropIfExists('safety_certified_access_permits');
    }
};

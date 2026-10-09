<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_personnel_credential_authorizations', function (Blueprint $table) {
            $table->id();
            $table->string('credential_code')->unique();
            $table->string('worker_id');
            $table->string('role_domain'); // HEALTHCARE, AVIATION, MINING, ENERGY
            $table->boolean('is_active')->default(true); // 384.1 & 384.4
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('global_operational_task_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('assignment_code')->unique();
            $table->string('worker_id');
            $table->string('credential_code')->index();
            $table->boolean('assignment_permitted')->default(true);
            $table->boolean('in_progress_safe_handoff_initiated')->default(false); // 384.4 & 384.5 Edge case
            $table->string('handoff_to_worker_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_operational_task_assignments');
        Schema::dropIfExists('global_personnel_credential_authorizations');
    }
};

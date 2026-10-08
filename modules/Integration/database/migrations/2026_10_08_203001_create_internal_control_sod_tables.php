<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 203.2: Segregation of Duties (SoD) Conflict Detection Matrix
        Schema::create('erm_sod_conflict_checks', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('domain_code');
            $table->json('assigned_roles');
            $table->boolean('has_sod_conflict')->default(false);
            $table->string('conflicting_roles_detail')->nullable();
            $table->string('remediation_status')->default('RESOLVED'); // RESOLVED, COMPENSATING_CONTROL_APPLIED
            $table->timestamps();
        });

        // 203.4: Segregation of Privileged Access & Break-glass Procedure Audits
        Schema::create('erm_breakglass_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_code')->unique();
            $table->string('admin_user_id');
            $table->string('emergency_action');
            $table->text('emergency_justification');
            $table->boolean('audited_by_compliance')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erm_breakglass_logs');
        Schema::dropIfExists('erm_sod_conflict_checks');
    }
};

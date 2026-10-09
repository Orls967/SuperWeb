<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_policy_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('policy_code')->unique();
            $table->string('policy_domain'); // PRICING, CREDIT, SAFETY, RETENTION, RBAC
            $table->integer('version')->default(1);
            $table->string('precedence_tier')->default('GLOBAL_DEFAULT'); // CONTRACT_SPECIFIC, REGIONAL_LAW, GLOBAL_DEFAULT (290.4 & 290.6)
            $table->boolean('simulation_test_passed')->default(false); // 290.1 & 290.5 Untested cannot activate
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('gov_policy_breakglass_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('override_token')->unique();
            $table->string('policy_code')->index();
            $table->string('initiator_id');
            $table->string('secondary_approver_id')->nullable(); // 290.3 Dual approval
            $table->boolean('is_ledger_invariant_bypass_attempt')->default(false); // 290.3 & 290.8 Strictly prohibited
            $table->dateTime('expires_at'); // 290.3 & 290.7 Auto-expiry
            $table->boolean('is_revoked')->default(false);
            $table->boolean('post_incident_reviewed')->default(false); // 290.7 Mandatory post-review
            $table->timestamps();
        });

        Schema::create('gov_policy_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision_trace_id')->unique();
            $table->string('policy_code')->index();
            $table->string('evaluation_context_json');
            $table->string('decision_outcome'); // ALLOW, DENY, DENY_AMBIGUOUS_FAIL_CLOSED (290.6)
            $table->string('applied_precedence_tier');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_policy_decisions');
        Schema::dropIfExists('gov_policy_breakglass_overrides');
        Schema::dropIfExists('gov_policy_catalog');
    }
};

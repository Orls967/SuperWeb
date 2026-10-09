<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_release_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('rc_version')->unique(); // e.g. v300.0.0-RC1
            $table->string('git_commit_sha');
            $table->boolean('all_audits_healthy')->default(false); // 299.6
            $table->boolean('security_review_cleared')->default(false);
            $table->boolean('runbooks_verified')->default(false);
            $table->boolean('api_docs_drift_clean')->default(false); // 299.4 & 299.6 drift check
            $table->boolean('is_rc_accepted')->default(false);
            $table->timestamps();
        });

        Schema::create('platform_documentation_drift_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_code')->unique();
            $table->string('documented_feature_key');
            $table->boolean('codebase_implementation_verified')->default(true); // 299.6 Edge case
            $table->boolean('drift_detected')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_documentation_drift_audits');
        Schema::dropIfExists('platform_release_candidates');
    }
};

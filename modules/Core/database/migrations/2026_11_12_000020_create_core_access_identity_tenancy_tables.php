<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_access_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_code', 64)->unique();
            $table->string('name', 128);
            $table->string('target_resource', 128);
            $table->string('effect', 16)->default('ALLOW'); // ALLOW / DENY
            $table->json('dimensions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['target_resource', 'is_active']);
        });

        Schema::create('core_access_grants', function (Blueprint $table) {
            $table->id();
            $table->string('grant_code', 64)->unique();
            $table->string('tenant_id', 64);
            $table->foreign('tenant_id')->references('id')->on('core_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('resource', 128);
            $table->string('action', 64);
            $table->string('status', 32)->default('requested'); // 8 statuses
            $table->json('scope_dimensions')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->integer('grant_version')->default(1);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index(['resource', 'action', 'status']);
        });

        Schema::create('core_access_grant_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained('core_access_grants')->cascadeOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('trigger_action', 64);
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transitioned_at');
            $table->index(['grant_id', 'transitioned_at']);
        });

        Schema::create('core_identity_links', function (Blueprint $table) {
            $table->id();
            $table->string('link_code', 64)->unique();
            $table->string('tenant_id', 64)->nullable();
            $table->foreign('tenant_id')->references('id')->on('core_tenants')->nullOnDelete();
            $table->string('subject_type', 32)->default('CUSTOMER'); // CUSTOMER / VENDOR
            $table->unsignedBigInteger('source_entity_id');
            $table->unsignedBigInteger('target_entity_id');
            $table->string('linkage_hash', 64);
            $table->integer('match_score')->default(0);
            $table->boolean('source_consent')->default(false);
            $table->boolean('target_consent')->default(false);
            $table->string('status', 32)->default('BLOCKED_NO_CONSENT');
            $table->boolean('is_active')->default(false);
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'linkage_hash']);
            $table->index(['source_entity_id', 'target_entity_id']);
        });

        Schema::create('core_vendor_exposure_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('snapshot_code', 64)->unique();
            $table->string('vendor_cluster_code', 64);
            $table->bigInteger('total_exposure_idr')->default(0);
            $table->bigInteger('group_credit_limit_idr')->default(0);
            $table->integer('active_tranche_count')->default(0);
            $table->boolean('is_limit_breached')->default(false);
            $table->json('module_breakdown')->nullable();
            $table->string('integrity_sha256', 64);
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->index(['vendor_cluster_code', 'calculated_at'], 'cves_cluster_calc_idx');
        });

        Schema::create('core_access_recertification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 64)->unique();
            $table->string('title', 128);
            $table->string('scope', 64)->nullable();
            $table->timestamp('deadline');
            $table->string('status', 32)->default('IN_PROGRESS'); // IN_PROGRESS / COMPLETED
            $table->timestamps();
        });

        Schema::create('core_access_recertification_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('core_access_recertification_campaigns')->cascadeOnDelete();
            $table->foreignId('grant_id')->constrained('core_access_grants')->cascadeOnDelete();
            $table->string('response', 32)->default('PENDING'); // PENDING / CERTIFIED / REVOKE_REQUESTED / AUTO_REVOKED
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['campaign_id', 'response']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_access_recertification_items');
        Schema::dropIfExists('core_access_recertification_campaigns');
        Schema::dropIfExists('core_vendor_exposure_snapshots');
        Schema::dropIfExists('core_identity_links');
        Schema::dropIfExists('core_access_grant_transitions');
        Schema::dropIfExists('core_access_grants');
        Schema::dropIfExists('core_access_policies');
    }
};

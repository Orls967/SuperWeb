<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_tenants', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->unsignedBigInteger('numeric_slot')->nullable()->index();
            $table->string('entity_uuid', 36)->nullable()->index();
            $table->string('party_uuid', 36)->nullable()->index();
            $table->boolean('is_sandbox')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('core_tenant_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tenant_id', 64);
            $table->foreign('tenant_id')->references('id')->on('core_tenants')->cascadeOnDelete();
            $table->json('allowed_entities')->nullable();
            $table->json('allowed_parties')->nullable();
            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id'], 'uniq_ctm_user_tenant');
        });

        Schema::create('core_tenant_scope_bypasses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tenant_id', 64)->nullable();
            $table->foreign('tenant_id')->references('id')->on('core_tenants')->nullOnDelete();
            $table->string('ability', 80);
            $table->string('model_class', 160);
            $table->string('reason', 255);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('core_api_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreign('tenant_id')->references('id')->on('core_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('legal_entity_name', 160);
            $table->string('tier', 20)->default('SILVER');
            $table->unsignedInteger('capacity')->default(60);
            $table->unsignedInteger('refill_per_sec')->default(1);
            $table->unsignedBigInteger('daily_quota')->default(10000);
            $table->bigInteger('bucket_milli_tokens')->default(60000);
            $table->unsignedBigInteger('bucket_last_ms')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sandbox')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active'], 'idx_cac_tenant_active');
        });

        Schema::create('core_api_client_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->constrained('core_api_clients')->cascadeOnDelete();
            $table->foreignId('rotated_from_id')->nullable()->constrained('core_api_client_keys')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('token_ref')->nullable();
            $table->string('key_prefix', 24)->index();
            $table->string('key_digest', 64)->unique();
            $table->json('abilities');
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('core_api_idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreign('tenant_id')->references('id')->on('core_tenants')->cascadeOnDelete();
            $table->string('client_code', 64);
            $table->string('route_name', 120);
            $table->string('http_method', 10);
            $table->string('idempotency_key', 128);
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->longText('response_body');
            $table->json('headers')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['client_code', 'route_name', 'http_method', 'idempotency_key'], 'uniq_api_idemp_key');
        });

        Schema::create('core_api_usage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->constrained('core_api_clients')->cascadeOnDelete();
            $table->string('tenant_id', 64)->index();
            $table->string('route_uri', 160);
            $table->string('http_method', 10);
            $table->unsignedSmallInteger('http_status');
            $table->boolean('allowed');
            $table->unsignedInteger('retry_after_ms')->default(0);
            $table->timestamp('occurred_at')->index();

            $table->index(['api_client_id', 'occurred_at'], 'idx_api_usage_client_at');
        });

        Schema::create('core_api_usage_rollups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->constrained('core_api_clients')->cascadeOnDelete();
            $table->string('bucket_hour', 16);
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('allowed_requests')->default(0);
            $table->unsignedInteger('throttled_requests')->default(0);
            $table->unsignedInteger('error_requests')->default(0);
            $table->timestamps();

            $table->unique(['api_client_id', 'bucket_hour'], 'uniq_api_rollup_hour');
        });

        Schema::create('core_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreign('tenant_id')->references('id')->on('core_tenants')->cascadeOnDelete();
            $table->foreignId('api_client_id')->nullable()->constrained('core_api_clients')->nullOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('target_url', 255);
            $table->json('event_topics');
            $table->text('secret_encrypted');
            $table->text('previous_secret_encrypted')->nullable();
            $table->timestamp('previous_secret_expires_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->string('circuit_state', 16)->default('closed');
            $table->timestamp('circuit_opened_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('core_webhook_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64)->index();
            $table->foreignId('webhook_id')->constrained('core_webhooks')->cascadeOnDelete();
            $table->foreignId('event_delivery_id')->nullable()->constrained('core_event_deliveries')->nullOnDelete();
            $table->string('outbound_event_ref', 64);
            $table->string('event_type', 120);
            $table->unsignedBigInteger('timestamp_unix');
            $table->longText('payload_json');
            $table->string('signature_header', 255);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_excerpt')->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempt_count')->default(1);
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();
        });

        Schema::create('core_edi_interchanges', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreign('tenant_id')->references('id')->on('core_tenants')->cascadeOnDelete();
            $table->string('control_ref', 64);
            $table->string('standard', 16);
            $table->string('message_type', 16);
            $table->string('sender_ref', 64);
            $table->string('receiver_ref', 64);
            $table->string('document_number', 64)->default('');
            $table->string('status', 24)->default('received');
            $table->longText('raw_payload');
            $table->longText('canonical_json')->nullable();
            $table->text('contrl_payload')->nullable();
            $table->string('handler_module', 48)->nullable();
            $table->string('handler_idempotency_key', 128)->nullable();
            $table->string('target_reference', 64)->nullable();
            $table->json('error_codes')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->string('cert_hash', 64)->nullable();
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'idx_cei_tenant_status');
        });

        Schema::create('core_edi_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('edi_interchange_id')->constrained('core_edi_interchanges')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->string('reason', 255)->nullable();
            $table->string('handler_module', 48)->nullable();
            $table->timestamp('transitioned_at');
            $table->timestamps();
        });

        Schema::create('core_bulk_import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreign('tenant_id')->references('id')->on('core_tenants')->cascadeOnDelete();
            $table->foreignId('approval_id')->nullable()->constrained('core_approvals')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_key', 48);
            $table->string('source_filename', 160);
            $table->string('file_path', 255);
            $table->string('error_csv_path', 255)->nullable();
            $table->string('status', 24)->default('validated');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('last_processed_row')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('core_bulk_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bulk_import_job_id')->constrained('core_bulk_import_jobs')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('reason', 255);
            $table->text('raw_row_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['bulk_import_job_id', 'row_number'], 'idx_cbie_job_row');
        });

        Schema::create('core_bulk_export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->foreign('tenant_id')->references('id')->on('core_tenants')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_key', 48);
            $table->string('status', 24)->default('completed');
            $table->unsignedInteger('total_rows')->default(0);
            $table->string('zip_path', 255)->nullable();
            $table->string('file_sha256', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_bulk_export_jobs');
        Schema::dropIfExists('core_bulk_import_errors');
        Schema::dropIfExists('core_bulk_import_jobs');
        Schema::dropIfExists('core_edi_transitions');
        Schema::dropIfExists('core_edi_interchanges');
        Schema::dropIfExists('core_webhook_dispatches');
        Schema::dropIfExists('core_webhooks');
        Schema::dropIfExists('core_api_usage_rollups');
        Schema::dropIfExists('core_api_usage_events');
        Schema::dropIfExists('core_api_idempotency_records');
        Schema::dropIfExists('core_api_client_keys');
        Schema::dropIfExists('core_api_clients');
        Schema::dropIfExists('core_tenant_scope_bypasses');
        Schema::dropIfExists('core_tenant_memberships');
        Schema::dropIfExists('core_tenants');
    }
};

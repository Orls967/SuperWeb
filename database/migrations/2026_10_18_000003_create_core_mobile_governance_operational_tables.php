<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_mobile_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('device_uuid', 64)->unique();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->name('fk_c_mob_dev_user');
            $table->string('role_scope', 32);
            $table->string('app_version', 20);
            $table->string('platform', 20);
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedBigInteger('sync_cursor')->default(0);
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            $table->index(['role_scope', 'platform']);
        });

        Schema::create('core_mobile_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 128)->unique();
            $table->string('device_uuid', 64)->index();
            $table->string('role_scope', 32);
            $table->string('event_type', 64);
            $table->json('payload');
            $table->timestamp('occurred_offline_at');
            $table->timestamp('server_received_at');
            $table->string('conflict_status', 20)->default('APPLIED');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'conflict_status']);
        });

        Schema::create('core_mobile_epod_records', function (Blueprint $table): void {
            $table->id();
            $table->string('epod_code', 64)->unique();
            $table->string('shipment_ref', 64)->index();
            $table->string('driver_ref', 64);
            $table->string('recipient_name', 100);
            $table->string('recipient_role', 50)->nullable();
            $table->string('photo_proof_hash', 64);
            $table->string('signature_hash', 64);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('delivered_offline_at');
            $table->timestamp('synced_at');
            $table->longText('signature_payload_b64');
            $table->boolean('is_tampered')->default(false);
            $table->timestamps();
        });

        Schema::create('core_mobile_field_actions', function (Blueprint $table): void {
            $table->id();
            $table->string('action_code', 64)->unique();
            $table->string('role_scope', 32);
            $table->string('action_type', 50);
            $table->string('reference_id', 64);
            $table->string('operator_name', 100);
            $table->string('status', 20)->default('COMMITTED');
            $table->json('details');
            $table->timestamps();

            $table->index(['role_scope', 'action_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_mobile_field_actions');
        Schema::dropIfExists('core_mobile_epod_records');
        Schema::dropIfExists('core_mobile_sync_events');
        Schema::dropIfExists('core_mobile_devices');
    }
};

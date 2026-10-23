<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_dr_drill_executions', function (Blueprint $table): void {
            $table->id();
            $table->string('drill_uuid', 64)->unique();
            $table->string('drill_type', 40);
            $table->string('primary_region', 30);
            $table->string('target_dr_region', 30);
            $table->integer('rpo_seconds');
            $table->integer('rto_seconds');
            $table->string('status', 30)->default('SUCCESS');
            $table->integer('divergence_count')->default(0);
            $table->string('checksum_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->name('fk_c_dr_drill_user');
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['drill_type', 'status']);
            $table->index(['primary_region', 'target_dr_region']);
        });

        Schema::create('core_kms_key_rotations', function (Blueprint $table): void {
            $table->id();
            $table->string('key_alias', 64);
            $table->integer('key_version');
            $table->string('status', 30)->default('ACTIVE');
            $table->string('encryption_algorithm', 50)->default('AES-256-GCM');
            $table->string('key_fingerprint', 64);
            $table->string('rotation_reason', 255)->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->name('fk_c_kms_rot_user');
            $table->timestamp('rotated_at');
            $table->timestamps();

            $table->index(['key_alias', 'status']);
            $table->unique(['key_alias', 'key_version']);
        });

        Schema::create('core_data_sovereignty_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('jurisdiction_code', 10)->unique();
            $table->string('regulation_reference', 100);
            $table->string('mandatory_storage_region', 30);
            $table->boolean('pii_encryption_required')->default(true);
            $table->boolean('cross_border_transfer_allowed')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_data_sovereignty_policies');
        Schema::dropIfExists('core_kms_key_rotations');
        Schema::dropIfExists('core_dr_drill_executions');
    }
};

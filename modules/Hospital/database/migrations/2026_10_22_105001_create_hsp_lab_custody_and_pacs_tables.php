<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hsp_lab_custody_transfers')) {
            Schema::create('hsp_lab_custody_transfers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('specimen_id')->constrained('hsp_lab_specimens')->cascadeOnDelete();
                $table->integer('hop_number');
                $table->string('from_handler', 64);
                $table->string('to_handler', 64);
                $table->string('prev_hash', 64);
                $table->string('custody_hash', 64);
                $table->timestamp('transferred_at');
                $table->timestamps();

                $table->index(['specimen_id', 'hop_number']);
            });
        }

        if (! Schema::hasTable('hsp_lab_critical_ranges')) {
            Schema::create('hsp_lab_critical_ranges', function (Blueprint $table): void {
                $table->id();
                $table->string('test_code', 32)->unique();
                $table->decimal('panic_low', 8, 2)->nullable();
                $table->decimal('panic_high', 8, 2)->nullable();
                $table->string('notification_protocol', 64)->default('CRITICAL_HOLD_AND_PAGE');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hsp_imaging_series')) {
            Schema::create('hsp_imaging_series', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('study_id')->constrained('hsp_imaging_studies')->cascadeOnDelete();
                $table->string('series_instance_uid', 64)->unique();
                $table->string('modality', 16);
                $table->integer('slice_count')->default(1);
                $table->string('storage_uri', 255);
                $table->timestamps();

                $table->index(['study_id', 'modality']);
            });
        }

        if (! Schema::hasTable('hsp_inter_branch_clearings')) {
            Schema::create('hsp_inter_branch_clearings', function (Blueprint $table): void {
                $table->id();
                $table->string('clearing_code', 32)->unique();
                $table->foreignId('from_branch_id')->constrained('hsp_pharmacy_branches')->cascadeOnDelete();
                $table->foreignId('to_branch_id')->constrained('hsp_pharmacy_branches')->cascadeOnDelete();
                $table->string('reference_type', 32);
                $table->string('reference_id', 64);
                $table->bigInteger('amount_idr');
                $table->string('settlement_status', 32)->default('PENDING');
                $table->timestamps();

                $table->index(['from_branch_id', 'to_branch_id', 'settlement_status'], 'hsp_branch_clearing_settle_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hsp_inter_branch_clearings');
        Schema::dropIfExists('hsp_imaging_series');
        Schema::dropIfExists('hsp_lab_critical_ranges');
        Schema::dropIfExists('hsp_lab_custody_transfers');
    }
};

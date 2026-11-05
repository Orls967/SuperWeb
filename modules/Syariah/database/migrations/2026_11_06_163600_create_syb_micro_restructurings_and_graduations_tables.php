<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_micro_restructurings', function (Blueprint $table) {
            $table->id();
            $table->string('restruct_code', 32)->unique();
            $table->string('facility_code', 32)->index();
            $table->string('reason', 255);
            $table->string('loss_type', 32); // NON_NEGLIGENCE_LOSS, MORAL_HAZARD_NEGLIGENCE
            $table->integer('previous_nisbah_bps');
            $table->integer('new_nisbah_bps');
            $table->integer('previous_tenor_months');
            $table->integer('new_tenor_months');
            $table->bigInteger('restructured_principal_idr');
            $table->boolean('moral_hazard_detected')->default(false);
            $table->string('approved_by', 64)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('syb_mustahik_graduations', function (Blueprint $table) {
            $table->id();
            $table->string('graduation_code', 32)->unique();
            $table->string('party_ref', 64)->index();
            $table->string('current_stage', 32); // MUSTAHIK_PRASEJAHTERA, MIKRO_PEMULA, MIKRO_BERTUMBUH, MUZAKKI_MANDIRI
            $table->string('target_stage', 32);
            $table->bigInteger('omzet_baseline_idr');
            $table->bigInteger('omzet_current_idr');
            $table->integer('growth_rate_bps');
            $table->string('field_officer_verifier', 64);
            $table->string('amil_supervisor_verifier', 64)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('status', 16)->default('PENDING'); // PENDING, GRADUATED, REJECTED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_mustahik_graduations');
        Schema::dropIfExists('syb_micro_restructurings');
    }
};

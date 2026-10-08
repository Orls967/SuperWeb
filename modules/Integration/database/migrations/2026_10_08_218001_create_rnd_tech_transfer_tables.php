<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 218.1 & 218.5: Stage-gate R&D governance where advancement requires preceding gate signoff
        Schema::create('ops_rnd_stage_gates', function (Blueprint $table) {
            $table->id();
            $table->string('project_code');
            $table->string('stage_name'); // DISCOVERY, LAB_EXPERIMENT, PILOT_LINE, MASS_RELEASE
            $table->integer('stage_order'); // 1, 2, 3, 4
            $table->boolean('is_signed_off')->default(false);
            $table->string('signed_off_by')->nullable();
            $table->unique(['project_code', 'stage_name']);
            $table->timestamps();
        });

        // 218.2: Intellectual Property management with freedom-to-operate (FTO) review
        Schema::create('ops_rnd_ip_assets', function (Blueprint $table) {
            $table->id();
            $table->string('ip_code')->unique();
            $table->string('title');
            $table->string('ip_type'); // PATENT, TRADEMARK, TRADE_SECRET
            $table->string('fto_status')->default('PENDING_REVIEW'); // CLEARED, PENDING_REVIEW, INFRINGING_RISK
            $table->date('renewal_deadline');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_rnd_ip_assets');
        Schema::dropIfExists('ops_rnd_stage_gates');
    }
};

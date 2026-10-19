<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 83.2 Anti-fraud & Dispute Window for Crossborder Escrow
        Schema::table('tf_crossborder_escrows', function (Blueprint $table) {
            $table->dateTime('disputed_at')->nullable()->after('released_at');
            $table->string('dispute_reason', 255)->nullable()->after('disputed_at');
            $table->dateTime('hold_until')->nullable()->after('dispute_reason');
        });

        // 83.3 & 83.4 CBAM Carbon Credit Linking & Landed Cost Margin Deduction
        Schema::table('tf_cbam_certificates', function (Blueprint $table) {
            $table->string('carbon_credit_ref', 64)->nullable()->after('status');
            $table->bigInteger('export_margin_deduction_idr')->default(0)->after('carbon_credit_ref');
        });
    }

    public function down(): void
    {
        Schema::table('tf_cbam_certificates', function (Blueprint $table) {
            $table->dropColumn(['carbon_credit_ref', 'export_margin_deduction_idr']);
        });

        Schema::table('tf_crossborder_escrows', function (Blueprint $table) {
            $table->dropColumn(['disputed_at', 'dispute_reason', 'hold_until']);
        });
    }
};

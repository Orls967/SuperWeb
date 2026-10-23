<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 84.5 Post-loss model feedback logging
        Schema::table('trd_bid_submissions', function (Blueprint $table) {
            $table->string('feedback_reason', 255)->nullable()->after('created_contract_code');
        });
    }

    public function down(): void
    {
        Schema::table('trd_bid_submissions', function (Blueprint $table) {
            $table->dropColumn('feedback_reason');
        });
    }
};

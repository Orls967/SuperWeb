<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 84.5 Post-loss model feedback logging & four-eyes creator tracking
        Schema::table('trd_bid_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('proposed_by_user_id')->nullable()->after('requires_four_eyes');
            $table->string('feedback_reason', 255)->nullable()->after('created_contract_code');

            $table->foreign('proposed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trd_bid_submissions', function (Blueprint $table) {
            $table->dropForeign(['proposed_by_user_id']);
            $table->dropColumn(['proposed_by_user_id', 'feedback_reason']);
        });
    }
};

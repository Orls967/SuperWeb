<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 175.2: Procurement RFPs & sealed proposals
        Schema::create('psv_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_code')->unique();
            $table->string('rfp_code');
            $table->string('consulting_firm_id');
            $table->decimal('bid_amount_idr', 18, 2);
            $table->boolean('is_sealed')->default(true);
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
        });

        // 175.1: Statements of Work & project milestones
        Schema::create('psv_milestones', function (Blueprint $table) {
            $table->id();
            $table->string('milestone_code')->unique();
            $table->string('sow_code');
            $table->string('title');
            $table->decimal('milestone_amount_idr', 18, 2);
            $table->boolean('is_deliverable_accepted')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->string('deliverable_checksum')->nullable();
            $table->timestamps();
        });

        // 175.3: Consultant time-bound access grants
        Schema::create('psv_consultant_access_grants', function (Blueprint $table) {
            $table->id();
            $table->string('grant_code')->unique();
            $table->unsignedBigInteger('consultant_user_id');
            $table->string('sow_code');
            $table->date('access_valid_until');
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psv_consultant_access_grants');
        Schema::dropIfExists('psv_milestones');
        Schema::dropIfExists('psv_proposals');
    }
};

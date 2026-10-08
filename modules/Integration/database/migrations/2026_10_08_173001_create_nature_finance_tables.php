<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 173.1: Ecosystem service projects (carbon, watershed, biodiversity)
        Schema::create('for_nature_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('project_name');
            $table->string('methodology_version');
            $table->decimal('verified_outcome_units', 18, 2);
            $table->decimal('issued_credits_count', 18, 2)->default(0.00);
            $table->timestamps();
        });

        // 173.2: Nature biodiversity credits with retirement ledger
        Schema::create('for_nature_credits', function (Blueprint $table) {
            $table->id();
            $table->string('credit_serial_number')->unique();
            $table->string('project_code');
            $table->string('current_holder');
            $table->boolean('is_retired')->default(false);
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });

        // 173.3: Corporate procurement & community benefit sharing
        Schema::create('for_community_benefit_shares', function (Blueprint $table) {
            $table->id();
            $table->string('share_code')->unique();
            $table->string('project_code');
            $table->decimal('total_proceeds_idr', 18, 2);
            $table->decimal('community_share_pct', 5, 2)->default(30.00); // 30% to local community
            $table->decimal('community_disbursement_idr', 18, 2);
            $table->decimal('developer_share_idr', 18, 2);
            $table->boolean('math_sums_to_proceeds')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('for_community_benefit_shares');
        Schema::dropIfExists('for_nature_credits');
        Schema::dropIfExists('for_nature_projects');
    }
};

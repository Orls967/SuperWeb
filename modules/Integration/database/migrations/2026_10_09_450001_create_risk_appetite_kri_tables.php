<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_risk_appetite_kris', function (Blueprint $table) {
            $table->id();
            $table->string('kri_code')->unique();
            $table->string('category'); // credit, market, operational, compliance, strategic, climate (450.1)
            $table->decimal('appetite_limit_threshold', 10, 2);
            $table->decimal('current_kri_value', 10, 2);
            $table->boolean('is_in_breach')->default(false); // 450.2, 450.4
            $table->string('assigned_risk_owner');
            $table->date('remediation_due_date')->nullable();
            $table->boolean('escalated_to_board_risk_committee')->default(false); // 450.2, 450.5
            $table->integer('breach_recurrence_count')->default(0); // 450.5 edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_risk_appetite_kris');
    }
};

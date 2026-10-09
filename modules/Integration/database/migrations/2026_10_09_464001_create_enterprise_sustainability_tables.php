<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_sustainability_steering', function (Blueprint $table) {
            $table->id();
            $table->string('decision_code')->unique();
            $table->string('decision_title');
            $table->text('tradeoff_rationale'); // cost vs carbon vs social (464.1, 464.5)
            $table->string('assigned_owner'); // 464.6 risk
            $table->date('due_date');
            $table->boolean('integrated_into_enterprise_risk')->default(false); // 464.2
            $table->boolean('uses_third_party_verified_metrics')->default(false); // 464.3, 464.4
            $table->string('status')->default('actionable'); // actionable, executed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_enterprise_sustainability_steering');
    }
};

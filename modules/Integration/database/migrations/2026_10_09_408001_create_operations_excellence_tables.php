<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_excellence_initiatives', function (Blueprint $table) {
            $table->id();
            $table->string('initiative_code')->unique();
            $table->string('title');
            $table->string('owner');
            $table->decimal('baseline_metric', 15, 2);
            $table->decimal('target_metric', 15, 2);
            $table->decimal('finance_verified_benefit', 18, 2)->default(0.00); // 408.2
            $table->boolean('finance_approved')->default(false);
            $table->boolean('safety_impact_detected')->default(false); // 408.5 edge case
            $table->boolean('is_halted')->default(false); // 408.5
            $table->string('status')->default('in_progress'); // in_progress, completed, halted
            $table->date('sustainment_review_date')->nullable(); // 408.2 & 408.6
            $table->boolean('sustainment_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('ops_excellence_deviations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained('ops_excellence_initiatives')->cascadeOnDelete();
            $table->string('deviation_code')->unique();
            $table->text('deviation_reason');
            $table->string('approved_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_excellence_deviations');
        Schema::dropIfExists('ops_excellence_initiatives');
    }
};

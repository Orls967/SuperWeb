<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_dps_compliance_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_ref', 64)->unique();
            $table->string('period_label', 32);
            $table->string('review_type', 32);
            $table->string('reviewer_name', 128);
            $table->string('verdict', 32)->default('COMPLIANT');
            $table->text('findings')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['period_label', 'review_type']);
            $table->index('verdict');
        });

        Schema::create('syb_ops_incident_logs', function (Blueprint $table) {
            $table->id();
            $table->string('incident_ref', 64)->unique();
            $table->string('severity', 16)->default('MEDIUM');
            $table->string('category', 32);
            $table->text('description');
            $table->string('entity_reference', 64);
            $table->string('resolution_status', 32)->default('OPEN');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['category', 'resolution_status']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_ops_incident_logs');
        Schema::dropIfExists('syb_dps_compliance_reviews');
    }
};

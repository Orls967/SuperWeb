<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_esg_disclosures', function (Blueprint $table) {
            $table->id();
            $table->string('disclosure_code')->unique();
            $table->string('metric_name'); // e.g. Scope 1 GHG Emissions, Water Consumption, Waste Recycled
            $table->string('period'); // e.g. 2026-FY
            $table->decimal('metric_value', 18, 4);
            $table->string('unit'); // tCO2e, m3, tons
            $table->string('system_source')->nullable(); // 407.5
            $table->string('owner');
            $table->string('evidence_bundle_hash')->nullable();
            $table->boolean('signed_off_by_esg_officer')->default(false);
            $table->boolean('is_published')->default(false); // 407.4
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('gov_esg_restatements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disclosure_id')->constrained('gov_esg_disclosures')->cascadeOnDelete();
            $table->integer('prior_version');
            $table->decimal('prior_value', 18, 4);
            $table->decimal('new_value', 18, 4);
            $table->text('correction_reason');
            $table->string('notified_stakeholders');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_esg_restatements');
        Schema::dropIfExists('gov_esg_disclosures');
    }
};

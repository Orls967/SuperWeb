<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_disclosure_publications', function (Blueprint $table) {
            $table->id();
            $table->string('publication_code')->unique();
            $table->string('reporting_year'); // e.g. 2026
            $table->string('metric_name');
            $table->decimal('reported_value', 15, 2);
            $table->string('data_tier'); // EMPIRICALLY_MEASURED, ESTIMATED_UNCERTAIN
            $table->boolean('has_supporting_evidence')->default(false); // 331.2 & 331.4
            $table->boolean('is_restated')->default(false); // 331.1, 331.4, 331.5 Edge case
            $table->string('previous_publication_code')->nullable(); // Preserves old publication
            $table->boolean('published_to_board')->default(false);
            $table->timestamps();
        });

        Schema::create('esg_finance_reconciliation_packs', function (Blueprint $table) {
            $table->id();
            $table->string('pack_code')->unique();
            $table->string('reporting_year');
            $table->decimal('green_capex_reported_usd', 18, 2);
            $table->decimal('gl_capex_audited_usd', 18, 2);
            $table->decimal('variance_usd', 18, 2);
            $table->boolean('financial_audit_reconciled')->default(false); // 331.3 & 331.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_finance_reconciliation_packs');
        Schema::dropIfExists('esg_disclosure_publications');
    }
};

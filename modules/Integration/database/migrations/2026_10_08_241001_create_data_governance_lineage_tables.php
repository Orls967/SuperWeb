<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_governance_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->unique();
            $table->string('steward_id')->nullable(); // 241.1 & 241.6
            $table->integer('retention_days')->default(365);
            $table->string('access_classification')->default('INTERNAL');
            $table->timestamps();
        });

        Schema::create('data_quality_rule_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->index();
            $table->string('dataset_name');
            $table->string('rule_type'); // COMPLETENESS, TIMELINESS, VALIDITY, CONSISTENCY, UNIQUENESS
            $table->decimal('dq_score_pct', 5, 2);
            $table->integer('quarantined_record_count')->default(0); // 241.2
            $table->string('owner_ticket_code')->nullable(); // 241.2
            $table->timestamps();
        });

        Schema::create('data_lineage_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('source_column')->index();
            $table->string('transform_operation');
            $table->string('target_artifact');
            $table->string('consumer_module');
            $table->timestamps();
        });

        Schema::create('data_glossary_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term_key')->unique();
            $table->string('domain_name');
            $table->text('definition');
            $table->boolean('approved_by_steward')->default(false); // 241.5
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_glossary_terms');
        Schema::dropIfExists('data_lineage_nodes');
        Schema::dropIfExists('data_quality_rule_evaluations');
        Schema::dropIfExists('data_governance_domains');
    }
};

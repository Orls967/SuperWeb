<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_data_catalog_datasets', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_code')->unique();
            $table->string('domain_name'); // MINING, ESG, COMMERCE
            $table->string('lineage_hash')->nullable(); // 359.1, 359.4, 359.6 Risk
            $table->boolean('has_recorded_lineage')->default(false);
            $table->boolean('permitted_for_training_pipeline')->default(false);
            $table->timestamps();
        });

        Schema::create('synthetic_dataset_privacy_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('evaluation_code')->unique();
            $table->string('synthetic_dataset_code')->index();
            $table->decimal('reidentification_risk_score', 5, 4);
            $table->decimal('max_allowed_risk_threshold', 5, 4)->default(0.0500);
            $table->boolean('privacy_check_passed')->default(false); // 359.2 & 359.4
            $table->boolean('regenerated_with_new_seed')->default(false); // 359.5 Edge case
            $table->boolean('permitted_for_fixtures')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synthetic_dataset_privacy_evaluations');
        Schema::dropIfExists('training_data_catalog_datasets');
    }
};

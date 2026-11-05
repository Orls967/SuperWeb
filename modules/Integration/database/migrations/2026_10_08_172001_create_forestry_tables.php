<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 172.1: Forest concession plots & sustainable harvest quotas
        Schema::create('for_concession_plots', function (Blueprint $table) {
            $table->id();
            $table->string('plot_code')->unique();
            $table->string('concession_name');
            $table->decimal('sustainable_quota_m3', 18, 2);
            $table->decimal('harvested_volume_m3', 18, 2)->default(0.00);
            $table->timestamps();
        });

        // 172.2: Chain of custody tickets (stump -> mill -> finished timber)
        Schema::create('for_custody_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code')->unique();
            $table->string('plot_code');
            $table->string('stage'); // STUMP, MILL, FINISHED
            $table->decimal('volume_m3', 18, 2);
            $table->string('permit_number');
            $table->string('ticket_hash');
            $table->timestamps();
        });

        // 172.3: Restoration polygons & survival rate monitoring
        Schema::create('for_restoration_polygons', function (Blueprint $table) {
            $table->id();
            $table->string('polygon_code')->unique();
            $table->decimal('planted_hectares', 18, 2);
            $table->decimal('tree_survival_rate_pct', 5, 2);
            $table->boolean('field_evidence_verified')->default(false);
            $table->boolean('claim_eligible')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('for_restoration_polygons');
        Schema::dropIfExists('for_custody_tickets');
        Schema::dropIfExists('for_concession_plots');
    }
};

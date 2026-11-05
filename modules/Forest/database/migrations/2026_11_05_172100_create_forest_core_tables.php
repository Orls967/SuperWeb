<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('for_concession_plots', function (Blueprint $table) {
            $table->id();
            $table->string('plot_code')->unique();
            $table->string('concession_name');
            $table->unsignedInteger('area_hectares');
            $table->unsignedInteger('annual_quota_m3');
            $table->unsignedInteger('harvested_volume_m3')->default(0);
            $table->string('status')->default('active'); // active, suspended, closed
            $table->timestamps();
        });

        Schema::create('for_tree_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('tree_tag')->unique();
            $table->foreignId('plot_id')->constrained('for_concession_plots')->cascadeOnDelete();
            $table->string('species_name');
            $table->unsignedInteger('dbh_cm');
            $table->unsignedInteger('height_m');
            $table->unsignedInteger('form_factor_pct')->default(70);
            $table->unsignedInteger('calculated_volume_dm3');
            $table->string('status')->default('standing'); // standing, marked_harvest, felled, conserved
            $table->timestamps();
        });

        Schema::create('for_harvest_permits', function (Blueprint $table) {
            $table->id();
            $table->string('permit_code')->unique();
            $table->foreignId('plot_id')->constrained('for_concession_plots')->cascadeOnDelete();
            $table->unsignedInteger('authorized_volume_m3');
            $table->unsignedInteger('harvested_volume_m3')->default(0);
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected, closed
            $table->unsignedBigInteger('requester_user_ref')->index();
            $table->unsignedBigInteger('approver_user_ref')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('for_timber_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_code')->unique();
            $table->foreignId('plot_id')->constrained('for_concession_plots')->cascadeOnDelete();
            $table->foreignId('permit_id')->constrained('for_harvest_permits')->cascadeOnDelete();
            $table->string('tree_tag')->index();
            $table->unsignedInteger('volume_dm3');
            $table->unsignedInteger('serial_number');
            $table->string('prev_hash');
            $table->string('canonical_hash');
            $table->string('stage')->default('stump'); // stump, log_landing, sawmill, delivered
            $table->timestamps();

            $table->unique(['permit_id', 'serial_number']);
        });

        Schema::create('for_timber_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_code')->unique();
            $table->unsignedBigInteger('buyer_party_ref')->index();
            $table->unsignedInteger('total_volume_dm3');
            $table->unsignedBigInteger('total_amount_idr');
            $table->unsignedBigInteger('forestry_levy_idr');
            $table->string('idempotency_key')->unique();
            $table->string('status')->default('invoiced'); // invoiced, paid
            $table->timestamps();
        });

        Schema::create('for_restoration_plots', function (Blueprint $table) {
            $table->id();
            $table->string('restoration_code')->unique();
            $table->foreignId('plot_id')->constrained('for_concession_plots')->cascadeOnDelete();
            $table->unsignedInteger('planted_hectares');
            $table->unsignedInteger('trees_planted');
            $table->unsignedInteger('survival_rate_pct');
            $table->unsignedInteger('satellite_ndvi_score');
            $table->unsignedInteger('carbon_credits_issued')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('for_restoration_plots');
        Schema::dropIfExists('for_timber_sales');
        Schema::dropIfExists('for_timber_logs');
        Schema::dropIfExists('for_harvest_permits');
        Schema::dropIfExists('for_tree_inventories');
        Schema::dropIfExists('for_concession_plots');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multiregion_domain_primaries', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->unique();
            $table->string('primary_region'); // AP_SOUTHEAST_SG, ID_JAKARTA
            $table->integer('current_epoch')->default(1);
            $table->boolean('strict_serialization_enforced')->default(true);
            $table->string('residency_country', 2)->default('ID');
            $table->timestamps();
        });

        Schema::create('multiregion_write_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tx_code')->unique();
            $table->string('domain_name')->index();
            $table->string('origin_region');
            $table->integer('epoch');
            $table->boolean('is_conflict_detected')->default(false);
            $table->string('conflict_outcome')->default('NO_CONFLICT'); // WINNER_COMMITTED, LOSER_ROLLED_BACK, NO_CONFLICT
            $table->timestamps();
        });

        Schema::create('multiregion_edge_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_code')->unique();
            $table->string('node_type'); // MINE_REMOTE, VESSEL_OFFSHORE, VENUE_HOTEL
            $table->integer('local_version_vector')->default(0);
            $table->integer('pending_ops_count')->default(0);
            $table->boolean('is_partitioned')->default(false);
            $table->boolean('is_converged')->default(true);
            $table->timestamps();
        });

        Schema::create('multiregion_federated_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query_code')->unique();
            $table->string('source_data_region');
            $table->string('execution_region');
            $table->decimal('egress_transfer_cost_usd', 10, 4)->default(0.0000);
            $table->boolean('data_residency_compliant')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multiregion_federated_queries');
        Schema::dropIfExists('multiregion_edge_nodes');
        Schema::dropIfExists('multiregion_write_transactions');
        Schema::dropIfExists('multiregion_domain_primaries');
    }
};

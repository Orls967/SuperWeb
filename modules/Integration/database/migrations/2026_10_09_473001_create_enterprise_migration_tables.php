<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_data_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration_code')->unique();
            $table->string('source_system');
            $table->string('target_system');
            $table->integer('source_records_count');
            $table->integer('target_records_count')->default(0);
            $table->boolean('pre_migration_cleansing_passed')->default(false); // 473.2, 473.6 risk
            $table->boolean('source_target_hash_reconciled')->default(false); // 473.4
            $table->string('status')->default('inventoried'); // inventoried, cleansed, migrated, rolled_back
            $table->timestamps();
        });

        Schema::create('int_legacy_system_retirements', function (Blueprint $table) {
            $table->id();
            $table->string('system_code')->unique();
            $table->string('system_name');
            $table->boolean('consumer_confirmation_or_waiver')->default(false); // 473.5 edge case
            $table->boolean('data_archived_and_access_revoked')->default(false); // 473.3, 473.4
            $table->decimal('annual_cost_saving_realized', 18, 2)->default(0.00); // 473.3, 473.7
            $table->string('status')->default('dual_run'); // dual_run, decommissioned
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_legacy_system_retirements');
        Schema::dropIfExists('int_enterprise_data_migrations');
    }
};

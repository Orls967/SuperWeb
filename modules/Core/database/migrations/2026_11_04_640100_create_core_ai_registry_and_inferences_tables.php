<?php

declare(strict_types=1);

namespace Modules\Core\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // D64-1 Registry Model AI
        Schema::create('core_ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('version', 32);
            $table->json('canonical_parameters');
            $table->string('parameter_hash', 64);
            $table->timestamps();

            $table->unique(['code', 'version'], 'uniq_c_ai_mod_code_ver');
        });

        // D64-1 Jejak Inferensi Append-Only
        Schema::create('core_ai_inferences', function (Blueprint $table) {
            $table->id();
            $table->string('model_code', 64)->index();
            $table->string('model_version', 32);
            $table->json('input_snapshot');
            $table->string('input_hash', 64);
            $table->bigInteger('seed');
            $table->json('output');
            $table->string('output_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['model_code', 'model_version'], 'idx_c_ai_inf_code_ver');
        });

        if (Schema::hasTable('core_ai_anomaly_quarantines')) {
            Schema::table('core_ai_anomaly_quarantines', function (Blueprint $table) {
                if (! Schema::hasColumn('core_ai_anomaly_quarantines', 'created_by')) {
                    $table->string('created_by', 255)->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('core_ai_anomaly_quarantines')) {
            Schema::table('core_ai_anomaly_quarantines', function (Blueprint $table) {
                if (Schema::hasColumn('core_ai_anomaly_quarantines', 'created_by')) {
                    $table->dropColumn('created_by');
                }
            });
        }
        Schema::dropIfExists('core_ai_inferences');
        Schema::dropIfExists('core_ai_models');
    }
};


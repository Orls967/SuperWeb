<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_financial_control_processes', function (Blueprint $table) {
            $table->id();
            $table->string('process_code')->unique();
            $table->string('process_title');
            $table->boolean('is_material_process')->default(true); // 458.2, 458.5
            $table->boolean('assurance_tested')->default(false); // 458.2, 458.4
            $table->string('management_assertion_status')->default('pending'); // pending, signed_effective, deficient
            $table->timestamps();
        });

        Schema::create('int_control_deficiencies', function (Blueprint $table) {
            $table->id();
            $table->string('deficiency_code')->unique();
            $table->string('process_code');
            $table->string('severity'); // control_deficiency, significant_deficiency, material_weakness (458.3)
            $table->boolean('disclosure_consideration_documented')->default(false); // 458.3, 458.6
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_control_deficiencies');
        Schema::dropIfExists('int_financial_control_processes');
    }
};

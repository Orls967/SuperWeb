<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_stress_domain_capacity_certifications', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name')->unique();
            $table->boolean('has_passing_benchmark_evidence')->default(false); // 400.4
            $table->boolean('is_certified')->default(false);
            $table->boolean('release_permitted')->default(false); // 400.4 & 400.5 Edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_stress_domain_capacity_certifications');
    }
};

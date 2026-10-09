<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_problem_records', function (Blueprint $table) {
            $table->id();
            $table->string('problem_code')->unique();
            $table->string('major_incident_code'); // 428.1 incident -> problem linkage
            $table->string('service_name');
            $table->text('root_cause_5whys')->nullable(); // 428.2
            $table->text('workaround_details')->nullable(); // 428.3
            $table->date('workaround_expiry_date')->nullable(); // 428.6 risk
            $table->boolean('effectiveness_verified')->default(false); // 428.4
            $table->boolean('escalated_to_engineering_review')->default(false); // 428.5 edge case
            $table->string('status')->default('investigating'); // investigating, known_error, closed, escalated
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plt_problem_records');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_process_racis', function (Blueprint $table) {
            $table->id();
            $table->string('process_code')->unique();
            $table->string('line_code'); // 30 lines (477.1)
            $table->string('responsible');
            $table->string('accountable'); // 477.1, 477.5 edge case (no critical process without accountable owner)
            $table->string('consulted');
            $table->string('informed');
            $table->timestamps();
        });

        Schema::create('int_enterprise_change_initiatives', function (Blueprint $table) {
            $table->id();
            $table->string('initiative_code')->unique();
            $table->string('title');
            $table->integer('required_capacity_fte');
            $table->boolean('capacity_check_passed')->default(false); // 477.6 risk
            $table->decimal('projected_benefit', 18, 2);
            $table->boolean('benefit_realized')->default(false); // 477.3, 477.4
            $table->string('status')->default('proposed'); // proposed, approved, benefit_realized
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_enterprise_change_initiatives');
        Schema::dropIfExists('int_enterprise_process_racis');
    }
};

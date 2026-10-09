<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_enterprise_tech_radar', function (Blueprint $table) {
            $table->id();
            $table->string('tech_name')->unique();
            $table->string('ring'); // adopt, trial, assess, hold (472.3)
            $table->string('owner');
            $table->date('review_cycle_due');
            $table->timestamps();
        });

        Schema::create('int_architecture_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('adr_code')->unique();
            $table->string('title');
            $table->string('proposed_tech');
            $table->boolean('violates_core_principles')->default(false); // 472.1, 472.6
            $table->boolean('adr_formally_approved')->default(false); // 472.2, 472.5
            $table->boolean('post_implementation_verified')->default(false);
            $table->string('status')->default('proposed'); // proposed, approved, rejected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_architecture_decisions');
        Schema::dropIfExists('int_enterprise_tech_radar');
    }
};

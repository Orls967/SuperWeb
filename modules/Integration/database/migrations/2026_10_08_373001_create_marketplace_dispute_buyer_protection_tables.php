<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_dispute_cases', function (Blueprint $table) {
            $table->id();
            $table->string('dispute_code')->unique();
            $table->string('buyer_id');
            $table->string('seller_id');
            $table->string('assigned_reviewer_id');
            $table->boolean('reviewer_conflict_of_interest')->default(false); // 373.4 & 373.5 Edge case
            $table->string('neutral_alternate_reviewer_id')->nullable();
            $table->boolean('has_required_evidence')->default(false); // 373.4
            $table->string('dispute_status'); // OPEN, RESOLVED
            $table->timestamps();
        });

        Schema::create('marketplace_buyer_protection_holds', function (Blueprint $table) {
            $table->id();
            $table->string('hold_code')->unique();
            $table->string('dispute_code')->index();
            $table->decimal('hold_amount_usd', 12, 2);
            $table->boolean('outcome_adjudicated')->default(false); // 373.4
            $table->boolean('hold_released')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_buyer_protection_holds');
        Schema::dropIfExists('marketplace_dispute_cases');
    }
};

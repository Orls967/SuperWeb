<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        gc_collect_cycles();

        Schema::create('htl_business_dates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id')->unique();
            $table->date('current_business_date');
            $table->string('status', 32)->default('open');
            $table->string('last_checkpoint', 64)->nullable();
            $table->timestamp('audited_at')->nullable();
            $table->timestamps();

            $table->foreign('property_id')
                ->references('id')
                ->on('htl_properties')
                ->cascadeOnDelete();
        });

        Schema::table('htl_properties', function (Blueprint $table) {
            $table->string('check_out_time', 8)->default('12:00:00');
            $table->unsignedBigInteger('late_checkout_fee_idr')->default(0);
        });

        gc_collect_cycles();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('htl_properties', function (Blueprint $table) {
            $table->dropColumn(['check_out_time', 'late_checkout_fee_idr']);
        });

        Schema::dropIfExists('htl_business_dates');
    }
};

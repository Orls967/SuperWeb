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
        Schema::create('htl_folio_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('folio_id');
            $table->date('business_date');
            $table->string('line_type', 32);
            $table->string('description', 255);
            $table->bigInteger('amount_idr');
            $table->unsignedBigInteger('base_amount_idr')->default(0);
            $table->unsignedBigInteger('service_charge_idr')->default(0);
            $table->unsignedBigInteger('tax_pbjt_idr')->default(0);
            $table->uuid('reversal_of_id')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();

            $table->foreign('folio_id')
                ->references('id')
                ->on('htl_folios')
                ->cascadeOnDelete();

            $table->foreign('reversal_of_id')
                ->references('id')
                ->on('htl_folio_lines')
                ->nullOnDelete();

            $table->index(['folio_id', 'business_date'], 'idx_htl_fl_folio_date');
        });

        Schema::create('htl_city_ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->string('company_name', 128);
            $table->string('company_code', 32)->unique();
            $table->unsignedBigInteger('credit_limit_idr');
            $table->unsignedBigInteger('outstanding_balance_idr')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('property_id')
                ->references('id')
                ->on('htl_properties')
                ->cascadeOnDelete();
        });

        Schema::create('htl_city_ledger_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('city_ledger_account_id');
            $table->uuid('folio_id');
            $table->unsignedBigInteger('transferred_amount_idr');
            $table->string('notes', 255)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->foreign('city_ledger_account_id')
                ->references('id')
                ->on('htl_city_ledger_accounts')
                ->cascadeOnDelete();

            $table->foreign('folio_id')
                ->references('id')
                ->on('htl_folios')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('htl_city_ledger_transfers');
        Schema::dropIfExists('htl_city_ledger_accounts');
        Schema::dropIfExists('htl_folio_lines');
    }
};

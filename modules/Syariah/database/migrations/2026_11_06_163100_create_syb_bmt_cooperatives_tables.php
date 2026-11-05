<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_bmt_cooperatives', function (Blueprint $table) {
            $table->id();
            $table->string('cooperative_code', 32)->unique();
            $table->string('name', 128);
            $table->string('legal_entity_number', 64);
            $table->string('city', 64);
            $table->string('address', 255)->nullable();
            $table->string('dps_advisor', 128);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('syb_bmt_savings', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 32)->unique();
            $table->string('cooperative_code', 32)->index();
            $table->string('customer_party_ref', 64)->index();
            $table->string('contract_type', 32); // WADIAH_YAD_DHAMANAH, MUDHARABAH_MUTLAQAH
            $table->bigInteger('balance_idr')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('cooperative_code')
                ->references('cooperative_code')
                ->on('syb_bmt_cooperatives')
                ->cascadeOnDelete();
        });

        Schema::create('syb_bmt_savings_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_ref', 64)->unique();
            $table->string('account_number', 32)->index();
            $table->string('type', 16); // DEPOSIT, WITHDRAWAL, PROFIT_SHARE, ATHAYA_BONUS
            $table->bigInteger('amount_idr');
            $table->bigInteger('balance_after_idr');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->foreign('account_number')
                ->references('account_number')
                ->on('syb_bmt_savings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_bmt_savings_transactions');
        Schema::dropIfExists('syb_bmt_savings');
        Schema::dropIfExists('syb_bmt_cooperatives');
    }
};

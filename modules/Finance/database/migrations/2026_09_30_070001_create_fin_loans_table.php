<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_loans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('store_orders')->nullOnDelete();

            $table->unsignedBigInteger('principal');
            $table->unsignedBigInteger('down_payment')->default(0);
            $table->decimal('interest_rate_annual', 6, 4)->default(0.08);
            $table->unsignedSmallInteger('tenor_months');

            $table->foreignId('collateral_asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->decimal('collateral_qty', 36, 18);
            $table->decimal('ltv_at_open', 8, 4);

            $table->unsignedBigInteger('outstanding_principal')->default(0);
            $table->string('status', 32)->default('active');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('margin_called_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });

        Schema::create('fin_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('fin_loans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->unsignedBigInteger('principal_part');
            $table->unsignedBigInteger('interest_part');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('penalty')->default(0);
            $table->string('status', 32)->default('scheduled');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('ledger_transaction_id')->nullable()
                ->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['loan_id', 'sequence']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_installments');
        Schema::dropIfExists('fin_loans');
    }
};

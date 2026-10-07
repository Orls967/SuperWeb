<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 161.1: Islamic banking products & contracts
        Schema::create('syb_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product_name');
            $table->string('akad_type'); // MURABAHAH, MUDHARABAH, MUSYARAKAH, IJARAH, QARDH
            $table->boolean('shariah_approved')->default(true);
            $table->timestamps();
        });

        // 161.2: Islamic subledger accounts (PSAK 102/103 segregated)
        Schema::create('syb_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->unique();
            $table->unsignedBigInteger('customer_id');
            $table->string('account_type'); // WADIAH, MUDHARABAH_DEPOSIT, FINANCING
            $table->decimal('balance', 18, 2)->default(0.00);
            $table->decimal('nisbah_customer_pct', 5, 2)->default(60.00); // Profit share ratio
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 161.3: Murabahah financing contracts
        Schema::create('syb_murabahah_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code')->unique();
            $table->string('account_number');
            $table->string('vendor_code'); // Direct vendor disbursement
            $table->decimal('cost_price_pokok', 18, 2);
            $table->decimal('margin_profit', 18, 2);
            $table->decimal('total_selling_price', 18, 2);
            $table->integer('tenor_months');
            $table->decimal('monthly_installment', 18, 2);
            $table->decimal('margin_recognized', 18, 2)->default(0.00);
            $table->decimal('late_penalties_to_amil', 18, 2)->default(0.00); // Disgorgement to charity pool (not bank revenue)
            $table->string('contract_hash')->nullable();
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        // 161.4: Mudharabah profit sharing pool
        Schema::create('syb_mudharabah_pools', function (Blueprint $table) {
            $table->id();
            $table->string('pool_period', 10);
            $table->decimal('total_pool_profit', 18, 2);
            $table->decimal('customer_share_distributed', 18, 2);
            $table->decimal('bank_mudharib_share', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_mudharabah_pools');
        Schema::dropIfExists('syb_murabahah_contracts');
        Schema::dropIfExists('syb_accounts');
        Schema::dropIfExists('syb_products');
    }
};

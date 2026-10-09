<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_insurance_portfolio_reserves', function (Blueprint $table) {
            $table->id();
            $table->string('policy_pool_code')->unique();
            $table->decimal('total_written_premium', 18, 2);
            $table->decimal('required_actuarial_reserve', 18, 2); // 440.1, 440.5
            $table->decimal('allocated_reserve', 18, 2);
            $table->boolean('under_reserve_flagged')->default(false);
            $table->boolean('reserve_top_up_approved')->default(true);
            $table->timestamps();
        });

        Schema::create('fin_syariah_akad_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('akad_code')->unique();
            $table->string('akad_type'); // mudharabah, musyarakah, murabahah, ijarah (440.2)
            $table->date('expiry_date');
            $table->boolean('renewal_reminder_sent')->default(false); // 440.6
            $table->boolean('shariah_board_cleared')->default(true); // 440.2, 440.4
            $table->string('status')->default('active'); // active, expired, renewed
            $table->timestamps();
        });

        Schema::create('fin_digital_securities_registers', function (Blueprint $table) {
            $table->id();
            $table->string('security_token_code')->unique();
            $table->decimal('total_issued_units', 18, 4);
            $table->decimal('distributed_units_sum', 18, 4); // 440.3, 440.4 reconciliation
            $table->boolean('is_register_balanced')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_digital_securities_registers');
        Schema::dropIfExists('fin_syariah_akad_agreements');
        Schema::dropIfExists('fin_insurance_portfolio_reserves');
    }
};

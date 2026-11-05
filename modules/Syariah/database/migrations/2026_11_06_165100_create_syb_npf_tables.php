<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_npf_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('classification_ref', 64)->unique();
            $table->date('evaluation_date');
            $table->string('period_label', 32);
            $table->unsignedInteger('total_contracts')->default(0);
            $table->bigInteger('total_portfolio_minor')->default(0);
            $table->bigInteger('performing_principal_minor')->default(0);
            $table->bigInteger('non_performing_principal_minor')->default(0);
            $table->bigInteger('total_required_provision_minor')->default(0);
            $table->unsignedInteger('npf_gross_bps')->default(0);
            $table->unsignedInteger('npf_net_bps')->default(0);
            $table->boolean('is_compliant')->default(true);
            $table->string('certificate_hash', 64);
            $table->string('evaluated_by', 64)->default('SYSTEM');
            $table->timestamps();

            $table->index(['evaluation_date', 'period_label']);
        });

        Schema::create('syb_npf_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('classification_ref', 64);
            $table->string('contract_ref', 64);
            $table->string('customer_ref', 64);
            $table->string('product_type', 32);
            $table->unsignedInteger('dpd')->default(0);
            $table->unsignedTinyInteger('collectibility')->default(1);
            $table->string('collectibility_name', 64);
            $table->bigInteger('outstanding_principal_minor')->default(0);
            $table->bigInteger('remaining_margin_minor')->default(0);
            $table->unsignedInteger('provision_rate_bps')->default(100);
            $table->bigInteger('provision_amount_minor')->default(0);
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();

            $table->index(['classification_ref', 'collectibility']);
            $table->index('contract_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_npf_snapshots');
        Schema::dropIfExists('syb_npf_classifications');
    }
};

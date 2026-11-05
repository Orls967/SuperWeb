<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syb_group_financings', function (Blueprint $table) {
            $table->id();
            $table->string('group_code', 32)->unique();
            $table->string('cooperative_code', 32)->index();
            $table->string('group_name', 128);
            $table->string('leader_party_ref', 64)->nullable();
            $table->integer('total_members')->default(5);
            $table->bigInteger('total_facility_amount_idr');
            $table->bigInteger('outstanding_balance_idr');
            $table->string('shariah_contract', 32); // MUDHARABAH_BERTINGKAT, MUSYARAKAH, QARDH_AL_HASAN
            $table->integer('profit_nisbah_shohibul_maal_bps')->default(4000); // 40%
            $table->boolean('group_liability_active')->default(true);
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });

        Schema::create('syb_group_members', function (Blueprint $table) {
            $table->id();
            $table->string('group_code', 32)->index();
            $table->string('member_party_ref', 64)->index();
            $table->string('member_name', 128);
            $table->bigInteger('share_amount_idr');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('group_code')
                ->references('group_code')
                ->on('syb_group_financings')
                ->cascadeOnDelete();
        });

        Schema::create('syb_micro_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('facility_code', 32)->unique();
            $table->string('cooperative_code', 32)->index();
            $table->string('customer_party_ref', 64)->index();
            $table->string('group_code', 32)->nullable()->index();
            $table->string('contract_type', 32);
            $table->string('level', 16)->default('pemula'); // pemula, bertumbuh, mandiri
            $table->bigInteger('principal_amount_idr');
            $table->bigInteger('outstanding_balance_idr');
            $table->integer('margin_or_nisbah_bps');
            $table->integer('tenor_months');
            $table->string('status', 24)->default('draft');
            $table->string('approved_by', 64)->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('syb_micro_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_code', 32)->unique();
            $table->string('facility_code', 32)->index();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->bigInteger('principal_due_idr');
            $table->bigInteger('margin_due_idr');
            $table->timestamp('paid_at')->nullable();
            $table->string('status', 16)->default('PENDING'); // PENDING, PAID, OVERDUE
            $table->timestamps();

            $table->foreign('facility_code')
                ->references('facility_code')
                ->on('syb_micro_facilities')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syb_micro_schedules');
        Schema::dropIfExists('syb_micro_facilities');
        Schema::dropIfExists('syb_group_members');
        Schema::dropIfExists('syb_group_financings');
    }
};

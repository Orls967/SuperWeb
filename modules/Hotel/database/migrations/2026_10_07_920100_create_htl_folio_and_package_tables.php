<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('htl_folio_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('folio_id');
            $table->string('item_category', 32); // ROOM_SERVICE, SPA, LAUNDRY, MINIBAR, VALET
            $table->string('description');
            $table->unsignedBigInteger('amount_idr');
            $table->timestamps();
        });

        Schema::create('htl_timeshare_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->string('unit_code')->unique();
            $table->string('unit_name');
            $table->unsignedInteger('total_token_shares')->default(100);
            $table->unsignedBigInteger('daily_rental_rate_idr');
            $table->timestamps();
        });

        Schema::create('htl_timeshare_investors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('timeshare_unit_id');
            $table->uuid('investor_user_id');
            $table->unsignedInteger('token_shares');
            $table->timestamps();
        });

        Schema::create('htl_destination_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('package_code')->unique();
            $table->string('title');
            $table->unsignedBigInteger('total_price_idr');
            $table->json('vendor_shares'); // [{"vendor": "HOTEL", "amount": 2000000, "account": "..."}, ...]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_destination_packages');
        Schema::dropIfExists('htl_timeshare_investors');
        Schema::dropIfExists('htl_timeshare_units');
        Schema::dropIfExists('htl_folio_items');
    }
};

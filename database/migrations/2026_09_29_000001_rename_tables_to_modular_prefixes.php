<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('services') && ! Schema::hasTable('serve_services')) {
            Schema::rename('services', 'serve_services');
        }

        if (Schema::hasTable('spareparts') && ! Schema::hasTable('serve_spareparts')) {
            Schema::rename('spareparts', 'serve_spareparts');
        }

        if (Schema::hasTable('bookings') && ! Schema::hasTable('serve_bookings')) {
            Schema::rename('bookings', 'serve_bookings');
        }

        if (Schema::hasTable('booking_sparepart') && ! Schema::hasTable('serve_booking_sparepart')) {
            Schema::rename('booking_sparepart', 'serve_booking_sparepart');
        }

        if (Schema::hasTable('brands') && ! Schema::hasTable('dex_brands')) {
            Schema::rename('brands', 'dex_brands');
        }

        if (Schema::hasTable('cars') && ! Schema::hasTable('dex_cars')) {
            Schema::rename('cars', 'dex_cars');
        }

        if (Schema::hasTable('garages') && ! Schema::hasTable('dex_garages')) {
            Schema::rename('garages', 'dex_garages');
        }

        if (Schema::hasTable('wishlists') && ! Schema::hasTable('dex_wishlists')) {
            Schema::rename('wishlists', 'dex_wishlists');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('serve_services') && ! Schema::hasTable('services')) {
            Schema::rename('serve_services', 'services');
        }

        if (Schema::hasTable('serve_spareparts') && ! Schema::hasTable('spareparts')) {
            Schema::rename('serve_spareparts', 'spareparts');
        }

        if (Schema::hasTable('serve_bookings') && ! Schema::hasTable('bookings')) {
            Schema::rename('serve_bookings', 'bookings');
        }

        if (Schema::hasTable('serve_booking_sparepart') && ! Schema::hasTable('booking_sparepart')) {
            Schema::rename('serve_booking_sparepart', 'booking_sparepart');
        }

        if (Schema::hasTable('dex_brands') && ! Schema::hasTable('brands')) {
            Schema::rename('dex_brands', 'brands');
        }

        if (Schema::hasTable('dex_cars') && ! Schema::hasTable('cars')) {
            Schema::rename('dex_cars', 'cars');
        }

        if (Schema::hasTable('dex_garages') && ! Schema::hasTable('garages')) {
            Schema::rename('dex_garages', 'garages');
        }

        if (Schema::hasTable('dex_wishlists') && ! Schema::hasTable('wishlists')) {
            Schema::rename('dex_wishlists', 'wishlists');
        }
    }
};

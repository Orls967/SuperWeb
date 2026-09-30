<?php

declare(strict_types=1);

namespace Modules\Core\database\seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\ActivityLogger;
use Modules\Core\Application\Services\NotificationService;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $notif = app(NotificationService::class);
        $activity = app(ActivityLogger::class);

        $customer = User::where('email', 'customer@autoserve.test')->first();
        $admin = User::where('email', 'admin@autoserve.test')->first();
        $mekanik = User::where('email', 'mekanik@autoserve.test')->first();

        if (! $customer || ! $admin) {
            return;
        }

        // === Sample Notifications for Customer ===
        $notif->send(
            userId: $customer->id,
            type: 'welcome',
            title: 'Selamat Datang di AutoServe! 🎉',
            body: 'Akun Anda telah berhasil dibuat. Mulai jelajahi fitur bengkel, toko, dan kripto kami.',
            icon: 'success',
            actionUrl: route('autodex.index'),
            actionLabel: 'Jelajahi AutoDex',
        );

        $notif->send(
            userId: $customer->id,
            type: 'topup_success',
            title: 'Top-Up Berhasil ✅',
            body: 'Saldo Anda telah bertambah Rp 5.000.000. Saldo siap digunakan untuk belanja dan servis.',
            icon: 'success',
            actionUrl: route('wallet.index'),
            actionLabel: 'Lihat Dompet',
        );

        $notif->send(
            userId: $customer->id,
            type: 'promo',
            title: 'Diskon Servis 20% 🔥',
            body: 'Nikmati diskon 20% untuk servis Tune Up Mesin selama bulan ini. Booking sekarang!',
            icon: 'info',
            actionUrl: route('bookings.create'),
            actionLabel: 'Booking Sekarang',
        );

        // === Sample Notifications for Admin ===
        $notif->send(
            userId: $admin->id,
            type: 'system',
            title: 'Sistem Berjalan Normal ✅',
            body: 'Semua modul (AutoServe, AutoDex, Banking, Store, Crypto) berjalan dengan baik. Tidak ada error terdeteksi.',
            icon: 'success',
        );

        $notif->send(
            userId: $admin->id,
            type: 'low_stock',
            title: 'Peringatan Stok Rendah ⚠️',
            body: 'Beberapa sparepart memiliki stok rendah (≤5 unit). Segera lakukan restocking.',
            icon: 'warning',
            actionUrl: route('spareparts.index'),
            actionLabel: 'Lihat Sparepart',
        );

        // === Sample Activities ===
        $activity->log(
            module: 'autoserve',
            event: 'system_ready',
            description: 'Platform AutoServe siap digunakan. Semua modul aktif.',
            userId: $admin->id,
        );

        $activity->log(
            module: 'banking',
            event: 'topup_completed',
            description: 'Customer Siti melakukan top-up Rp 5.000.000',
            userId: $customer->id,
        );

        $activity->log(
            module: 'autodex',
            event: 'catalog_browsed',
            description: 'Katalog AutoDex dikunjungi — 30+ merk, 100+ model tersedia',
            userId: $customer->id,
        );

        $activity->log(
            module: 'crypto',
            event: 'market_opened',
            description: 'Pasar kripto simulasi aktif — BTC, ETH, SOL tersedia untuk trading',
            userId: null,
        );

        $activity->log(
            module: 'store',
            event: 'products_available',
            description: 'Toko online aktif — sparepart, aksesoris, dan mobil bekas tersedia',
            userId: null,
        );

        if ($mekanik) {
            $notif->send(
                userId: $mekanik->id,
                type: 'shift_start',
                title: 'Shift Dimulai 🔧',
                body: 'Selamat pagi! Ada beberapa booking pending yang menunggu dikerjakan.',
                icon: 'info',
                actionUrl: route('dashboard'),
                actionLabel: 'Lihat Dashboard',
            );

            $activity->log(
                module: 'autoserve',
                event: 'mechanic_login',
                description: 'Mekanik Budi memulai shift hari ini',
                userId: $mekanik->id,
            );
        }
    }
}

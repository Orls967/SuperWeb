<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Asset\Database\Seeders\AssetSeeder;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Contract\database\seeders\ContractSeeder;
use Modules\Core\database\seeders\PlatformSeeder;
use Modules\Core\database\seeders\RbacSeeder;
use Modules\Crypto\database\seeders\CryptoSeeder;
use Modules\Logistics\database\seeders\LogisticsSeeder;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Party\database\seeders\PartySeeder;
use Modules\Resto\database\seeders\RestoMenuSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // USERS: Admin, Mekanik, Customer
        // ============================================================
        User::create([
            'name' => 'Admin AutoServe',
            'email' => 'admin@autoserve.test',
            'phone' => '081234567890',
            'role' => 'admin',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Budi Mekanik',
            'email' => 'mekanik@autoserve.test',
            'phone' => '081234567891',
            'role' => 'mekanik',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Andi Mekanik',
            'email' => 'mekanik2@autoserve.test',
            'phone' => '081234567892',
            'role' => 'mekanik',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Siti Customer',
            'email' => 'customer@autoserve.test',
            'phone' => '081234567893',
            'role' => 'customer',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // ============================================================
        // SERVICES (Jenis Servis)
        // ============================================================
        $services = [
            ['name' => 'Tune Up Mesin', 'description' => 'Servis berkala mesin meliputi busi, filter, karburator', 'price' => 350000],
            ['name' => 'Ganti Oli Mesin', 'description' => 'Penggantian oli mesin + filter oli', 'price' => 150000],
            ['name' => 'Servis Rem', 'description' => 'Pengecekan & perbaikan sistem rem', 'price' => 250000],
            ['name' => 'Spooring & Balancing', 'description' => 'Penyetelan sudut roda dan balancing', 'price' => 200000],
            ['name' => 'AC Mobil', 'description' => 'Servis & isi freon AC mobil', 'price' => 300000],
            ['name' => 'Ganti Timing Belt', 'description' => 'Penggantian timing belt mesin', 'price' => 500000],
            ['name' => 'Overhaul Mesin', 'description' => 'Bongkar pasang mesin total', 'price' => 2500000],
            ['name' => 'Servis Kopling', 'description' => 'Penggantian dan penyetelan kopling', 'price' => 400000],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }

        // ============================================================
        // SPAREPARTS
        // ============================================================
        $spareparts = [
            ['name' => 'Oli Mesin Castrol 10W-40', 'code' => 'SP-001', 'stock' => 50, 'price' => 85000, 'unit' => 'liter'],
            ['name' => 'Filter Oli', 'code' => 'SP-002', 'stock' => 30, 'price' => 45000, 'unit' => 'pcs'],
            ['name' => 'Busi NGK Iridium', 'code' => 'SP-003', 'stock' => 40, 'price' => 95000, 'unit' => 'pcs'],
            ['name' => 'Kampas Rem Depan', 'code' => 'SP-004', 'stock' => 20, 'price' => 250000, 'unit' => 'set'],
            ['name' => 'Kampas Rem Belakang', 'code' => 'SP-005', 'stock' => 20, 'price' => 200000, 'unit' => 'set'],
            ['name' => 'Filter Udara', 'code' => 'SP-006', 'stock' => 25, 'price' => 75000, 'unit' => 'pcs'],
            ['name' => 'Aki GS Astra 45Ah', 'code' => 'SP-007', 'stock' => 10, 'price' => 750000, 'unit' => 'pcs'],
            ['name' => 'Timing Belt Continental', 'code' => 'SP-008', 'stock' => 8, 'price' => 450000, 'unit' => 'pcs'],
            ['name' => 'Freon AC R134a', 'code' => 'SP-009', 'stock' => 15, 'price' => 120000, 'unit' => 'kaleng'],
            ['name' => 'Minyak Rem DOT 4', 'code' => 'SP-010', 'stock' => 20, 'price' => 55000, 'unit' => 'botol'],
            ['name' => 'V-Belt Fan', 'code' => 'SP-011', 'stock' => 12, 'price' => 180000, 'unit' => 'pcs'],
            ['name' => 'Kopling Set', 'code' => 'SP-012', 'stock' => 5, 'price' => 1200000, 'unit' => 'set'],
        ];

        foreach ($spareparts as $part) {
            Sparepart::create($part);
        }

        // Jalankan seeder AutoDex, Banking & Crypto
        $this->call([
            AutoDexSeeder::class,
            BankingSeeder::class,
            CryptoSeeder::class,
        ]);

        // Inisialisasi Dompet & PIN untuk seeded users
        $topUp = app(TopUpAction::class);
        $setPin = app(SetPinAction::class);

        $customer = User::where('email', 'customer@autoserve.test')->first();
        if ($customer) {
            $customer->walletAccount('IDR');
            $setPin->execute($customer, '123456');
            $topUp->execute($customer, '5000000', 'seed_topup_customer');
        }

        $admin = User::where('email', 'admin@autoserve.test')->first();
        if ($admin) {
            $admin->walletAccount('IDR');
            $setPin->execute($admin, '123456');
            $topUp->execute($admin, '25000000', 'seed_topup_admin');
        }

        // Platform notifications & activity log demo data
        $this->call([
            PlatformSeeder::class,
            DemoCustomerSeeder::class,
            RestoMenuSeeder::class,
            MallSeeder::class,
            LogisticsSeeder::class,
            PartySeeder::class,
            ContractSeeder::class,
            AssetSeeder::class,
            RbacSeeder::class,
        ]);
    }
}

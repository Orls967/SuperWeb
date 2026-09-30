<?php

declare(strict_types=1);

namespace Modules\Resto\database\seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\BaseUnit;
use Modules\Resto\Domain\Enums\IngredientCategory;
use Modules\Resto\Domain\Enums\OutletType;
use Modules\Resto\Domain\Enums\RecipeLineType;
use Modules\Resto\Domain\Enums\ServiceStyle;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\IngredientCost;
use Modules\Resto\Domain\Models\MenuCategory;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\MenuItemOutlet;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\Recipe;
use Modules\Resto\Domain\Models\RecipeLine;
use Modules\Resto\Domain\Models\RestoStaffAssignment;
use Modules\Resto\Domain\Models\UnitConversion;

class RestoMenuSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Outlets
        $dmOutlet = Outlet::updateOrCreate(
            ['code' => 'DM-01'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'RM Sari Ranah — Duta Mall',
                'type' => OutletType::OUTLET,
                'address' => 'Duta Mall Lt. Dasar Unit LG-12, Jl. A. Yani Km 2',
                'city' => 'Banjarmasin',
                'phone' => '0511-3255101',
                'mall_unit_ref' => 'LG-12',
                'seats' => 90,
                'opens_at' => '10:00:00',
                'closes_at' => '22:00:00',
                'is_active' => true,
            ]
        );

        $centralKitchen = Outlet::updateOrCreate(
            ['code' => 'CK-01'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'RM Sari Ranah — Dapur Sentral Veteran',
                'type' => OutletType::CENTRAL_KITCHEN,
                'address' => 'Jl. Veteran No. 88',
                'city' => 'Banjarmasin',
                'phone' => '0511-3255999',
                'mall_unit_ref' => null,
                'seats' => 0,
                'opens_at' => '04:00:00',
                'closes_at' => '20:00:00',
                'is_active' => true,
            ]
        );

        $kayutangiOutlet = Outlet::updateOrCreate(
            ['code' => 'KD-01'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'RM Sari Ranah — Kayutangi',
                'type' => OutletType::OUTLET,
                'address' => 'Jl. Brigjend H. Hasan Basri No. 45',
                'city' => 'Banjarmasin',
                'phone' => '0511-3255202',
                'mall_unit_ref' => null,
                'seats' => 60,
                'opens_at' => '08:00:00',
                'closes_at' => '22:00:00',
                'is_active' => true,
            ]
        );

        // Staff demo accounts
        $kasirUser = User::firstOrCreate(
            ['email' => 'kasir@autoserve.test'],
            [
                'name' => 'Siti Kasir Minang',
                'password' => bcrypt('password'),
                'role' => 'cashier',
                'phone' => '081299887766',
            ]
        );

        $kitchenUser = User::firstOrCreate(
            ['email' => 'dapur@autoserve.test'],
            [
                'name' => 'Uda Dapur Sentral',
                'password' => bcrypt('password'),
                'role' => 'kitchen',
                'phone' => '081299887755',
            ]
        );

        $managerUser = User::firstOrCreate(
            ['email' => 'manager.resto@autoserve.test'],
            [
                'name' => 'Bustami Manager Resto',
                'password' => bcrypt('password'),
                'role' => 'outlet_manager',
                'phone' => '081299887744',
            ]
        );

        RestoStaffAssignment::updateOrCreate(
            ['user_id' => $kasirUser->id, 'outlet_id' => $dmOutlet->id, 'role' => 'cashier'],
            ['is_active' => true]
        );
        RestoStaffAssignment::updateOrCreate(
            ['user_id' => $kitchenUser->id, 'outlet_id' => $centralKitchen->id, 'role' => 'kitchen'],
            ['is_active' => true]
        );
        RestoStaffAssignment::updateOrCreate(
            ['user_id' => $managerUser->id, 'outlet_id' => $dmOutlet->id, 'role' => 'outlet_manager'],
            ['is_active' => true]
        );

        // 2. Ingredients
        $ingredientsData = [
            ['sku' => 'ING-DAGING-GANDIK', 'name' => 'Daging Sapi Gandik (Topside)', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::PROTEIN, 'perishable' => true, 'shelf' => 72, 'cost' => 140.000000], // Rp140/g = Rp140.000/kg
            ['sku' => 'ING-DAGING-CINCANG', 'name' => 'Daging Sapi Tetelan & Cincang', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::PROTEIN, 'perishable' => true, 'shelf' => 72, 'cost' => 90.000000], // Rp90/g
            ['sku' => 'ING-TUNJANG', 'name' => 'Kikil / Tunjang Kaki Sapi', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::PROTEIN, 'perishable' => true, 'shelf' => 72, 'cost' => 95.000000],
            ['sku' => 'ING-OTAK-SAPI', 'name' => 'Otak Sapi Segar', 'base_unit' => BaseUnit::PCS, 'category' => IngredientCategory::PROTEIN, 'perishable' => true, 'shelf' => 48, 'cost' => 15000.000000],
            ['sku' => 'ING-AYAM-PEJANTAN', 'name' => 'Ayam Pejantan Segar Utuh', 'base_unit' => BaseUnit::PCS, 'category' => IngredientCategory::PROTEIN, 'perishable' => true, 'shelf' => 48, 'cost' => 38000.000000],
            ['sku' => 'ING-IKAN-KAKAP', 'name' => 'Kepala Ikan Kakap Merah Segar', 'base_unit' => BaseUnit::PCS, 'category' => IngredientCategory::PROTEIN, 'perishable' => true, 'shelf' => 48, 'cost' => 35000.000000],
            ['sku' => 'ING-IKAN-TONGKOL', 'name' => 'Ikan Tongkol Segar', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::PROTEIN, 'perishable' => true, 'shelf' => 48, 'cost' => 45.000000],
            ['sku' => 'ING-TELUR-BEBEK', 'name' => 'Telur Bebek Minang Segar', 'base_unit' => BaseUnit::PCS, 'category' => IngredientCategory::PROTEIN, 'perishable' => false, 'shelf' => 240, 'cost' => 3000.000000],
            ['sku' => 'ING-TELUR-AYAM', 'name' => 'Telur Ayam Ras Segar', 'base_unit' => BaseUnit::PCS, 'category' => IngredientCategory::PROTEIN, 'perishable' => false, 'shelf' => 240, 'cost' => 2000.000000],
            ['sku' => 'ING-KENTANG', 'name' => 'Kentang Dieng', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::SAYUR, 'perishable' => false, 'shelf' => 360, 'cost' => 18.000000],
            ['sku' => 'ING-DAUN-SINGKONG', 'name' => 'Daun Singkong Muda', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::SAYUR, 'perishable' => true, 'shelf' => 48, 'cost' => 12.000000],
            ['sku' => 'ING-NANGKA-MUDA', 'name' => 'Nangka Muda Kupas', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::SAYUR, 'perishable' => true, 'shelf' => 48, 'cost' => 15.000000],
            ['sku' => 'ING-CABE-MERAH', 'name' => 'Cabai Merah Keriting Bukittinggi', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::BUMBU, 'perishable' => true, 'shelf' => 96, 'cost' => 50.000000],
            ['sku' => 'ING-CABE-HIJAU', 'name' => 'Cabai Hijau Keriting', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::BUMBU, 'perishable' => true, 'shelf' => 96, 'cost' => 35.000000],
            ['sku' => 'ING-BAWANG-MERAH', 'name' => 'Bawang Merah Alahan Panjang', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::BUMBU, 'perishable' => false, 'shelf' => 240, 'cost' => 40.000000],
            ['sku' => 'ING-BAWANG-PUTIH', 'name' => 'Bawang Putih Kating', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::BUMBU, 'perishable' => false, 'shelf' => 240, 'cost' => 35.000000],
            ['sku' => 'ING-LENGKUAS', 'name' => 'Lengkuas Segar', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::BUMBU, 'perishable' => false, 'shelf' => 240, 'cost' => 15.000000],
            ['sku' => 'ING-JAHE', 'name' => 'Jahe Gajah Segar', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::BUMBU, 'perishable' => false, 'shelf' => 240, 'cost' => 20.000000],
            ['sku' => 'ING-KUNYIT', 'name' => 'Kunyit Basah', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::BUMBU, 'perishable' => false, 'shelf' => 240, 'cost' => 15.000000],
            ['sku' => 'ING-SANTAN-MURNI', 'name' => 'Santan Murni Peras Kental', 'base_unit' => BaseUnit::ML, 'category' => IngredientCategory::BUMBU, 'perishable' => true, 'shelf' => 24, 'cost' => 25.000000],
            ['sku' => 'ING-BERAS-SOLOK', 'name' => 'Beras Solok Anak Daro', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::BERAS, 'perishable' => false, 'shelf' => 720, 'cost' => 17.000000], // Rp17.000/kg
            ['sku' => 'ING-TEH-BUBUK', 'name' => 'Teh Hitam Bubuk Minang', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::MINUMAN, 'perishable' => false, 'shelf' => 720, 'cost' => 50.000000],
            ['sku' => 'ING-GULA-PASIR', 'name' => 'Gula Pasir Kristal', 'base_unit' => BaseUnit::GRAM, 'category' => IngredientCategory::MINUMAN, 'perishable' => false, 'shelf' => 720, 'cost' => 16.000000],
            ['sku' => 'ING-SUSU-KENTAL', 'name' => 'Susu Kental Manis Putih', 'base_unit' => BaseUnit::ML, 'category' => IngredientCategory::MINUMAN, 'perishable' => false, 'shelf' => 360, 'cost' => 30.000000],
            ['sku' => 'ING-KERTAS-BUNGKUS', 'name' => 'Kertas Nasi Bungkus Cokelat Minang', 'base_unit' => BaseUnit::PCS, 'category' => IngredientCategory::KEMASAN, 'perishable' => false, 'shelf' => null, 'cost' => 450.000000],
            ['sku' => 'ING-KOTAK-KATERING', 'name' => 'Kotak Nasi Katering Custom', 'base_unit' => BaseUnit::PCS, 'category' => IngredientCategory::KEMASAN, 'perishable' => false, 'shelf' => null, 'cost' => 2500.000000],
        ];

        $ingredients = [];
        $outlets = [$dmOutlet, $centralKitchen, $kayutangiOutlet];

        foreach ($ingredientsData as $ingData) {
            $ing = Ingredient::updateOrCreate(
                ['sku' => $ingData['sku']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $ingData['name'],
                    'base_unit' => $ingData['base_unit'],
                    'category' => $ingData['category'],
                    'is_perishable' => $ingData['perishable'],
                    'shelf_life_hours' => $ingData['shelf'],
                    'min_stock_base_unit' => '500',
                ]
            );
            $ingredients[$ingData['sku']] = $ing;

            // Set cost per outlet
            foreach ($outlets as $outlet) {
                IngredientCost::updateOrCreate(
                    ['ingredient_id' => $ing->id, 'outlet_id' => $outlet->id],
                    [
                        'moving_avg_cost_per_base_unit' => number_format($ingData['cost'], 6, '.', ''),
                        'last_purchase_cost' => number_format($ingData['cost'], 6, '.', ''),
                    ]
                );
            }
        }

        // Global unit conversions
        $conversions = [
            ['from_unit' => 'kg', 'to_base_factor' => '1000.000000', 'label' => 'Kilogram ke Gram'],
            ['from_unit' => 'liter', 'to_base_factor' => '1000.000000', 'label' => 'Liter ke Mililiter'],
            ['from_unit' => 'ikat', 'to_base_factor' => '250.000000', 'label' => 'Ikat Sayur (250g)'],
            ['from_unit' => 'butir', 'to_base_factor' => '1.000000', 'label' => 'Butir / Pieces'],
            ['from_unit' => 'pax', 'to_base_factor' => '1.000000', 'label' => 'Porsi / Pax'],
        ];

        foreach ($conversions as $conv) {
            UnitConversion::firstOrCreate(
                ['from_unit' => $conv['from_unit']],
                [
                    'to_base_factor' => $conv['to_base_factor'],
                    'label' => $conv['label'],
                ]
            );
        }

        // 3. Categories
        $categoriesData = [
            ['name' => 'Lauk Daging', 'sort' => 1],
            ['name' => 'Lauk Ayam', 'sort' => 2],
            ['name' => 'Lauk Ikan & Seafood', 'sort' => 3],
            ['name' => 'Lauk Jeroan & Telur', 'sort' => 4],
            ['name' => 'Sayur & Sambal', 'sort' => 5],
            ['name' => 'Nasi', 'sort' => 6],
            ['name' => 'Minuman', 'sort' => 7],
            ['name' => 'Paket Ramas', 'sort' => 8],
            ['name' => 'Katering & Prasmanan', 'sort' => 9],
        ];

        $categories = [];
        foreach ($categoriesData as $cData) {
            $cat = MenuCategory::updateOrCreate(['name' => $cData['name']], ['sort' => $cData['sort']]);
            $categories[$cData['name']] = $cat;
        }

        // 4. Menu Items (42 items)
        $itemsData = [
            // Lauk Daging
            ['cat' => 'Lauk Daging', 'sku' => 'MNU-RENDANG-DAGING', 'name' => 'Rendang Daging Sapi Spesial', 'style' => ServiceStyle::HIDANG, 'base' => 24000, 'takeaway' => 25000, 'spice' => 3, 'desc' => 'Daging sapi gandik dimasak perlahan dengan santan dan rempah khas Minang.'],
            ['cat' => 'Lauk Daging', 'sku' => 'MNU-DENDENG-BATOKOK', 'name' => 'Dendeng Batokok Lado Merah', 'style' => ServiceStyle::HIDANG, 'base' => 23000, 'takeaway' => 24000, 'spice' => 4, 'desc' => 'Daging sapi pipih dipukul lalu dibakar dengan siraman lado merah pedas wangi.'],
            ['cat' => 'Lauk Daging', 'sku' => 'MNU-DENDENG-BALADO', 'name' => 'Dendeng Balado Renyah', 'style' => ServiceStyle::HIDANG, 'base' => 23000, 'takeaway' => 24000, 'spice' => 3, 'desc' => 'Irisan daging sapi tipis garing berlumur sambal balado manis gurih.'],
            ['cat' => 'Lauk Daging', 'sku' => 'MNU-GULAI-CINCANG', 'name' => 'Gulai Cincang Daging Padang', 'style' => ServiceStyle::HIDANG, 'base' => 25000, 'takeaway' => 26000, 'spice' => 4, 'desc' => 'Cincangan daging dan tetelan sapi empuk dengan kuah gulai kental pekat.'],
            ['cat' => 'Lauk Daging', 'sku' => 'MNU-GULAI-TUNJANG', 'name' => 'Gulai Tunjang Kaki Sapi', 'style' => ServiceStyle::HIDANG, 'base' => 26000, 'takeaway' => 27000, 'spice' => 3, 'desc' => 'Kikil tunjang kenyal lembut terendam kuah gulai kuning berempah.'],

            // Lauk Ayam
            ['cat' => 'Lauk Ayam', 'sku' => 'MNU-AYAM-POP', 'name' => 'Ayam Pop Panas Spesial', 'style' => ServiceStyle::PESAN, 'base' => 21000, 'takeaway' => 22000, 'spice' => 1, 'desc' => 'Ayam kampung muda gurih lembut digoreng kilat disajikan dengan sambal tomat pop panas.'],
            ['cat' => 'Lauk Ayam', 'sku' => 'MNU-AYAM-BAKAR', 'name' => 'Ayam Bakar Padang', 'style' => ServiceStyle::HIDANG, 'base' => 21000, 'takeaway' => 21000, 'spice' => 2, 'desc' => 'Ayam berlumur bumbu rempah kuning dibakar kecokelatan aroma arang.'],
            ['cat' => 'Lauk Ayam', 'sku' => 'MNU-AYAM-GULAI', 'name' => 'Gulai Ayam Lengkuas', 'style' => ServiceStyle::HIDANG, 'base' => 21000, 'takeaway' => 21000, 'spice' => 2, 'desc' => 'Potongan ayam empuk dengan kuah gulai rempah wangi daun kunyit.'],
            ['cat' => 'Lauk Ayam', 'sku' => 'MNU-AYAM-GORENG-LENGKUAS', 'name' => 'Ayam Goreng Serundeng Lengkuas', 'style' => ServiceStyle::HIDANG, 'base' => 21000, 'takeaway' => 21000, 'spice' => 1, 'desc' => 'Ayam goreng garing bertabur serundeng lengkuas gurih renyah.'],
            ['cat' => 'Lauk Ayam', 'sku' => 'MNU-AYAM-CABE-IJO', 'name' => 'Ayam Goreng Lado Mudo', 'style' => ServiceStyle::HIDANG, 'base' => 22000, 'takeaway' => 22000, 'spice' => 4, 'desc' => 'Ayam goreng empuk dilumuri sambal cabai hijau tumbuk kasar segar.'],

            // Lauk Ikan & Seafood
            ['cat' => 'Lauk Ikan & Seafood', 'sku' => 'MNU-KEPALA-KAKAP', 'name' => 'Gulai Kepala Ikan Kakap Jumbo', 'style' => ServiceStyle::PESAN, 'base' => 55000, 'takeaway' => 57000, 'spice' => 3, 'desc' => 'Kepala ikan kakap merah segar dengan kuah gulai kuning asam kandis melegenda.'],
            ['cat' => 'Lauk Ikan & Seafood', 'sku' => 'MNU-GULAI-TONGKOL', 'name' => 'Gulai Ikan Tongkol Karang', 'style' => ServiceStyle::HIDANG, 'base' => 19000, 'takeaway' => 20000, 'spice' => 2, 'desc' => 'Ikan tongkol berdaging tebal dimasak gulai kuning sedap.'],
            ['cat' => 'Lauk Ikan & Seafood', 'sku' => 'MNU-IKAN-KEMBUNG-BAKAR', 'name' => 'Ikan Kembung Bakar Minang', 'style' => ServiceStyle::HIDANG, 'base' => 18000, 'takeaway' => 18000, 'spice' => 2, 'desc' => 'Ikan kembung bakar dengan bumbu olesan rempah kelapa.'],
            ['cat' => 'Lauk Ikan & Seafood', 'sku' => 'MNU-BELUT-BALADO', 'name' => 'Belut Goreng Balado Renyah', 'style' => ServiceStyle::HIDANG, 'base' => 24000, 'takeaway' => 25000, 'spice' => 4, 'desc' => 'Belut sawah garing renyah dibalut sambal merah cabai keriting.'],
            ['cat' => 'Lauk Ikan & Seafood', 'sku' => 'MNU-UDANG-BALADO', 'name' => 'Udang Sambal Balado Petai', 'style' => ServiceStyle::HIDANG, 'base' => 26000, 'takeaway' => 27000, 'spice' => 3, 'desc' => 'Udang laut segar dimasak balado petai harum menggoda.'],

            // Lauk Jeroan & Telur
            ['cat' => 'Lauk Jeroan & Telur', 'sku' => 'MNU-TELUR-DADAR', 'name' => 'Telur Dadar Padang Tebal', 'style' => ServiceStyle::HIDANG, 'base' => 10000, 'takeaway' => 10000, 'spice' => 1, 'desc' => 'Telur bebek campur telur ayam tebal berlapis daun bawang dan cabai merah.'],
            ['cat' => 'Lauk Jeroan & Telur', 'sku' => 'MNU-TELUR-BALADO', 'name' => 'Telur Bulat Balado', 'style' => ServiceStyle::HIDANG, 'base' => 8000, 'takeaway' => 8000, 'spice' => 3, 'desc' => 'Telur rebus goreng dengan sambal balado merah merona.'],
            ['cat' => 'Lauk Jeroan & Telur', 'sku' => 'MNU-TELUR-GULAI', 'name' => 'Gulai Telur Bulat', 'style' => ServiceStyle::HIDANG, 'base' => 8000, 'takeaway' => 8000, 'spice' => 1, 'desc' => 'Telur rebus dalam kuah gulai gurih santan kelapa.'],
            ['cat' => 'Lauk Jeroan & Telur', 'sku' => 'MNU-GULAI-OTAK', 'name' => 'Gulai Otak Sapi Lembut', 'style' => ServiceStyle::HIDANG, 'base' => 22000, 'takeaway' => 23000, 'spice' => 2, 'desc' => 'Otak sapi segar kukus dimasak gulai kuning gurih lumer di mulut.'],
            ['cat' => 'Lauk Jeroan & Telur', 'sku' => 'MNU-GULAI-BABAT', 'name' => 'Gulai Babat Sapi', 'style' => ServiceStyle::HIDANG, 'base' => 20000, 'takeaway' => 21000, 'spice' => 2, 'desc' => 'Babat sapi bersih empuk berkuah gulai santan kental.'],
            ['cat' => 'Lauk Jeroan & Telur', 'sku' => 'MNU-PARU-GORENG', 'name' => 'Paru Goreng Balado Renyah', 'style' => ServiceStyle::HIDANG, 'base' => 22000, 'takeaway' => 23000, 'spice' => 3, 'desc' => 'Paru sapi garing renyah tidak alot berpadu sambal lado.'],
            ['cat' => 'Lauk Jeroan & Telur', 'sku' => 'MNU-PERKEDEL', 'name' => 'Perkedel Kentang Minang', 'style' => ServiceStyle::HIDANG, 'base' => 6000, 'takeaway' => 6000, 'spice' => 0, 'desc' => 'Perkedel kentang lembut dengan bumbu rempah bawang goreng.'],
            ['cat' => 'Lauk Jeroan & Telur', 'sku' => 'MNU-RENDANG-JENGKOL', 'name' => 'Rendang Jengkol Empuk Legit', 'style' => ServiceStyle::HIDANG, 'base' => 15000, 'takeaway' => 15000, 'spice' => 3, 'desc' => 'Jengkol pilihan pulen dimasak bumbu rendang Minang.'],

            // Sayur & Sambal
            ['cat' => 'Sayur & Sambal', 'sku' => 'MNU-SAMBAL-IJO', 'name' => 'Sambal Lado Mudo (Sambal Ijo)', 'style' => ServiceStyle::HIDANG, 'base' => 5000, 'takeaway' => 5000, 'spice' => 4, 'desc' => 'Cabai hijau kukus tumbuk kasar dengan minyak kelapa harum.'],
            ['cat' => 'Sayur & Sambal', 'sku' => 'MNU-SAMBAL-MERAH', 'name' => 'Sambal Balado Merah', 'style' => ServiceStyle::HIDANG, 'base' => 5000, 'takeaway' => 5000, 'spice' => 3, 'desc' => 'Sambal merah Minang gurih sedikit asam manis segar.'],
            ['cat' => 'Sayur & Sambal', 'sku' => 'MNU-DAUN-SINGKONG', 'name' => 'Daun Singkong Rebus Empuk', 'style' => ServiceStyle::HIDANG, 'base' => 5000, 'takeaway' => 5000, 'spice' => 0, 'desc' => 'Pucuk daun singkong rebus hijau segar tanpa pahit.'],
            ['cat' => 'Sayur & Sambal', 'sku' => 'MNU-GULAI-NANGKA', 'name' => 'Gulai Kapau Nangka Muda & Kol', 'style' => ServiceStyle::HIDANG, 'base' => 7000, 'takeaway' => 7000, 'spice' => 2, 'desc' => 'Nangka muda empuk berpadu kol dan kacang panjang kuah kuning.'],
            ['cat' => 'Sayur & Sambal', 'sku' => 'MNU-LALAPAN-TIMUN', 'name' => 'Lalap Timun & Kemangi', 'style' => ServiceStyle::HIDANG, 'base' => 4000, 'takeaway' => 4000, 'spice' => 0, 'desc' => 'Irisan timun segar dan kemangi pembersih lidah.'],

            // Nasi
            ['cat' => 'Nasi', 'sku' => 'MNU-NASI-PUTIH', 'name' => 'Nasi Putih Solok Porsi', 'style' => ServiceStyle::PESAN, 'base' => 8000, 'takeaway' => 8000, 'spice' => 0, 'desc' => 'Nasi putih beras Solok Anak Daro pulen dan wangi.'],
            ['cat' => 'Nasi', 'sku' => 'MNU-TAMBUAH-NASI', 'name' => 'Tambuah Nasi (Tambah Nasi Setengah)', 'style' => ServiceStyle::PESAN, 'base' => 4000, 'takeaway' => 4000, 'spice' => 0, 'desc' => 'Porsi tambah nasi panas khas Ranah Minang.'],

            // Paket Ramas
            ['cat' => 'Paket Ramas', 'sku' => 'MNU-RAMAS-RENDANG', 'name' => 'Nasi Ramas Rendang Komplit', 'style' => ServiceStyle::PAKET, 'base' => 32000, 'takeaway' => 33000, 'spice' => 3, 'desc' => 'Nasi putih + Rendang Daging Sapi + Sayur Nangka + Daun Singkong + Sambal Ijo.'],
            ['cat' => 'Paket Ramas', 'sku' => 'MNU-RAMAS-AYAM-POP', 'name' => 'Nasi Ramas Ayam Pop Panas', 'style' => ServiceStyle::PAKET, 'base' => 29000, 'takeaway' => 30000, 'spice' => 2, 'desc' => 'Nasi putih + Ayam Pop Panas + Sambal Pop + Sayur Nangka + Daun Singkong.'],
            ['cat' => 'Paket Ramas', 'sku' => 'MNU-RAMAS-DENDENG', 'name' => 'Nasi Ramas Dendeng Batokok', 'style' => ServiceStyle::PAKET, 'base' => 31000, 'takeaway' => 32000, 'spice' => 4, 'desc' => 'Nasi putih + Dendeng Batokok Lado Merah + Sayur Nangka + Daun Singkong.'],
            ['cat' => 'Paket Ramas', 'sku' => 'MNU-RAMAS-TELUR-DADAR', 'name' => 'Nasi Ramas Telur Dadar Minang', 'style' => ServiceStyle::PAKET, 'base' => 19000, 'takeaway' => 20000, 'spice' => 2, 'desc' => 'Nasi putih + Telur Dadar Tebal + Sayur Nangka + Sambal Ijo.'],

            // Minuman
            ['cat' => 'Minuman', 'sku' => 'MNU-TEH-TALUA', 'name' => 'Teh Talua Minang Asli', 'style' => ServiceStyle::PESAN, 'base' => 15000, 'takeaway' => 15000, 'spice' => 0, 'desc' => 'Teh kocok telur bebek berbuih tebal dengan susu kental manis dan jeruk nipis.'],
            ['cat' => 'Minuman', 'sku' => 'MNU-ES-TEBAK', 'name' => 'Es Tebak Campur Padang', 'style' => ServiceStyle::MINUMAN, 'base' => 14000, 'takeaway' => 14000, 'spice' => 0, 'desc' => 'Es serut dengan olahan tepung beras tebak, kolang-kaling, cincau hitam, dan sirup merah.'],
            ['cat' => 'Minuman', 'sku' => 'MNU-ES-CENDOL-DURIAN', 'name' => 'Es Cendol Durian Ranah', 'style' => ServiceStyle::MINUMAN, 'base' => 18000, 'takeaway' => 18000, 'spice' => 0, 'desc' => 'Cendol kenyal berpadu santan gurih, gula aren asli, dan daging durian legit.'],
            ['cat' => 'Minuman', 'sku' => 'MNU-ES-JERUK-PERAS', 'name' => 'Es Jeruk Peras Murni', 'style' => ServiceStyle::MINUMAN, 'base' => 10000, 'takeaway' => 10000, 'spice' => 0, 'desc' => 'Jeruk peras segar manis asam dingin melegakan.'],
            ['cat' => 'Minuman', 'sku' => 'MNU-TEH-MANIS-DINGIN', 'name' => 'Es Teh Manis Minang', 'style' => ServiceStyle::MINUMAN, 'base' => 6000, 'takeaway' => 6000, 'spice' => 0, 'desc' => 'Teh hitam kental wangi es batu menyegarkan.'],
            ['cat' => 'Minuman', 'sku' => 'MNU-TEH-TAWAR-HANGAT', 'name' => 'Teh Tawar Hangat', 'style' => ServiceStyle::MINUMAN, 'base' => 3000, 'takeaway' => 3000, 'spice' => 0, 'desc' => 'Teh hangat tawar peluntur lemak rempah.'],

            // Katering & Prasmanan
            ['cat' => 'Katering & Prasmanan', 'sku' => 'MNU-KATERING-AQIQAH', 'name' => 'Paket Katering Nasi Kotak Aqiqah', 'style' => ServiceStyle::PAKET, 'base' => 45000, 'takeaway' => 45000, 'spice' => 2, 'desc' => 'Kotak nasi eksklusif: Nasi Minang + Rendang Daging + Gulai Ayam + Telur Balado + Sayur + Buah.'],
            ['cat' => 'Katering & Prasmanan', 'sku' => 'MNU-KATERING-PRASMANAN', 'name' => 'Paket Prasmanan Padang Istimewa (Per Pax)', 'style' => ServiceStyle::PAKET, 'base' => 65000, 'takeaway' => 65000, 'spice' => 3, 'desc' => 'Layanan prasmanan lengkap 10 menu lauk + meja saji tradisional dan pelayan berseragam.'],
        ];

        $createdMenuItems = [];
        foreach ($itemsData as $idx => $iData) {
            $cat = $categories[$iData['cat']];
            $item = MenuItem::updateOrCreate(
                ['sku' => $iData['sku']],
                [
                    'uuid' => (string) Str::uuid(),
                    'category_id' => $cat->id,
                    'name' => $iData['name'],
                    'slug' => Str::slug($iData['name']).'-'.$idx,
                    'description' => $iData['desc'],
                    'service_style' => $iData['style'],
                    'base_price' => $iData['base'],
                    'takeaway_price' => $iData['takeaway'],
                    'is_active' => true,
                    'is_halal_certified' => true,
                    'spice_level' => $iData['spice'],
                    'sort' => $idx + 1,
                ]
            );
            $createdMenuItems[$iData['sku']] = $item;

            // Seed price override for Kayutangi (misal sedikit lebih murah atau promo)
            if ($iData['sku'] === 'MNU-RENDANG-DAGING') {
                MenuItemOutlet::updateOrCreate(
                    ['menu_item_id' => $item->id, 'outlet_id' => $kayutangiOutlet->id],
                    ['price_override' => 23000, 'is_available' => true]
                );
            }
        }

        // 5. Multi-Level Recipes (BOM)
        // Sub-Recipe 1: Bumbu Dasar Merah Padang (Yield 1000 gram)
        $subBumbuMerah = Recipe::updateOrCreate(
            ['sub_recipe_name' => 'Bumbu Dasar Merah Padang'],
            [
                'menu_item_id' => null,
                'yield_qty' => '1000.000000',
                'yield_unit' => 'gram',
                'expected_portions' => '10.000000',
                'waste_percent' => '5.00',
                'instructions' => 'Giling halus cabai merah keriting, bawang merah, bawang putih, jahe, lengkuas dengan sedikit minyak kelapa.',
                'version' => 1,
                'is_active' => true,
            ]
        );

        RecipeLine::updateOrCreate(['recipe_id' => $subBumbuMerah->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-CABE-MERAH']->id], ['qty_base_unit' => '500.000000', 'note' => 'Cabai merah']);
        RecipeLine::updateOrCreate(['recipe_id' => $subBumbuMerah->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-BAWANG-MERAH']->id], ['qty_base_unit' => '300.000000', 'note' => 'Bawang merah']);
        RecipeLine::updateOrCreate(['recipe_id' => $subBumbuMerah->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-BAWANG-PUTIH']->id], ['qty_base_unit' => '150.000000', 'note' => 'Bawang putih']);
        RecipeLine::updateOrCreate(['recipe_id' => $subBumbuMerah->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-JAHE']->id], ['qty_base_unit' => '50.000000', 'note' => 'Jahe']);

        // Sub-Recipe 2: Bumbu Gulai Minang (Yield 1000 gram)
        $subBumbuGulai = Recipe::updateOrCreate(
            ['sub_recipe_name' => 'Bumbu Gulai Minang'],
            [
                'menu_item_id' => null,
                'yield_qty' => '1000.000000',
                'yield_unit' => 'gram',
                'expected_portions' => '10.000000',
                'waste_percent' => '4.00',
                'instructions' => 'Giling kunyit, jahe, lengkuas, bawang merah, bawang putih dengan rempah daun aromatik.',
                'version' => 1,
                'is_active' => true,
            ]
        );

        RecipeLine::updateOrCreate(['recipe_id' => $subBumbuGulai->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-KUNYIT']->id], ['qty_base_unit' => '300.000000', 'note' => 'Kunyit basah']);
        RecipeLine::updateOrCreate(['recipe_id' => $subBumbuGulai->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-LENGKUAS']->id], ['qty_base_unit' => '300.000000', 'note' => 'Lengkuas']);
        RecipeLine::updateOrCreate(['recipe_id' => $subBumbuGulai->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-BAWANG-MERAH']->id], ['qty_base_unit' => '250.000000', 'note' => 'Bawang merah']);
        RecipeLine::updateOrCreate(['recipe_id' => $subBumbuGulai->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-BAWANG-PUTIH']->id], ['qty_base_unit' => '150.000000', 'note' => 'Bawang putih']);

        // Main Recipe: Rendang Daging Sapi (Yield 10 porsi) using Bumbu Dasar Merah Padang!
        if (isset($createdMenuItems['MNU-RENDANG-DAGING'])) {
            $rendangItem = $createdMenuItems['MNU-RENDANG-DAGING'];
            $rendangRecipe = Recipe::updateOrCreate(
                ['menu_item_id' => $rendangItem->id],
                [
                    'sub_recipe_name' => null,
                    'yield_qty' => '10.000000',
                    'yield_unit' => 'porsi',
                    'expected_portions' => '10.000000',
                    'waste_percent' => '3.00',
                    'instructions' => 'Masak daging gandik dengan santan murni dan bumbu dasar merah hingga mengering dan menghitam berkilau (± 6-8 jam).',
                    'version' => 1,
                    'is_active' => true,
                ]
            );

            RecipeLine::updateOrCreate(
                ['recipe_id' => $rendangRecipe->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-DAGING-GANDIK']->id],
                ['qty_base_unit' => '1000.000000', 'note' => '1 kg daging sapi gandik (100g/porsi)']
            );
            RecipeLine::updateOrCreate(
                ['recipe_id' => $rendangRecipe->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-SANTAN-MURNI']->id],
                ['qty_base_unit' => '1500.000000', 'note' => '1500 ml santan kental murni']
            );
            RecipeLine::updateOrCreate(
                ['recipe_id' => $rendangRecipe->id, 'line_type' => RecipeLineType::SUB_RECIPE, 'sub_recipe_id' => $subBumbuMerah->id],
                ['qty_base_unit' => '300.000000', 'note' => '300 gram bumbu dasar merah padang']
            );
        }

        // Main Recipe: Gulai Tunjang Kaki Sapi using Bumbu Gulai Minang!
        if (isset($createdMenuItems['MNU-GULAI-TUNJANG'])) {
            $tunjangItem = $createdMenuItems['MNU-GULAI-TUNJANG'];
            $tunjangRecipe = Recipe::updateOrCreate(
                ['menu_item_id' => $tunjangItem->id],
                [
                    'sub_recipe_name' => null,
                    'yield_qty' => '10.000000',
                    'yield_unit' => 'porsi',
                    'expected_portions' => '10.000000',
                    'waste_percent' => '2.00',
                    'instructions' => 'Rebus tunjang kaki sapi hingga empuk, lalu masak bersama bumbu gulai dan santan hingga meresap sempurna.',
                    'version' => 1,
                    'is_active' => true,
                ]
            );

            RecipeLine::updateOrCreate(
                ['recipe_id' => $tunjangRecipe->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-TUNJANG']->id],
                ['qty_base_unit' => '1200.000000', 'note' => '1.2 kg tunjang empuk']
            );
            RecipeLine::updateOrCreate(
                ['recipe_id' => $tunjangRecipe->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-SANTAN-MURNI']->id],
                ['qty_base_unit' => '1000.000000', 'note' => '1000 ml santan kental']
            );
            RecipeLine::updateOrCreate(
                ['recipe_id' => $tunjangRecipe->id, 'line_type' => RecipeLineType::SUB_RECIPE, 'sub_recipe_id' => $subBumbuGulai->id],
                ['qty_base_unit' => '250.000000', 'note' => '250 gram bumbu gulai minang']
            );
        }

        // Main Recipe: Teh Talua Minang
        if (isset($createdMenuItems['MNU-TEH-TALUA'])) {
            $tehItem = $createdMenuItems['MNU-TEH-TALUA'];
            $tehRecipe = Recipe::updateOrCreate(
                ['menu_item_id' => $tehItem->id],
                [
                    'sub_recipe_name' => null,
                    'yield_qty' => '1.000000',
                    'yield_unit' => 'porsi',
                    'expected_portions' => '1.000000',
                    'waste_percent' => '0.00',
                    'instructions' => 'Kocok kuning telur bebek dengan gula sampai mengembang putih pucat, seduh dengan teh hitam pekat mendidih, beri susu kental.',
                    'version' => 1,
                    'is_active' => true,
                ]
            );

            RecipeLine::updateOrCreate(
                ['recipe_id' => $tehRecipe->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-TELUR-BEBEK']->id],
                ['qty_base_unit' => '1.000000', 'note' => '1 butir telur bebek']
            );
            RecipeLine::updateOrCreate(
                ['recipe_id' => $tehRecipe->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-TEH-BUBUK']->id],
                ['qty_base_unit' => '10.000000', 'note' => '10 gram teh hitam pekat']
            );
            RecipeLine::updateOrCreate(
                ['recipe_id' => $tehRecipe->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-GULA-PASIR']->id],
                ['qty_base_unit' => '20.000000', 'note' => '20 gram gula pasir']
            );
            RecipeLine::updateOrCreate(
                ['recipe_id' => $tehRecipe->id, 'line_type' => RecipeLineType::INGREDIENT, 'ingredient_id' => $ingredients['ING-SUSU-KENTAL']->id],
                ['qty_base_unit' => '20.000000', 'note' => '20 ml susu kental manis']
            );
        }
    }
}

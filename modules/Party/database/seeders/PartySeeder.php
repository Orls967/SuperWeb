<?php

declare(strict_types=1);

namespace Modules\Party\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Party\Application\Services\PartyService;
use Modules\Party\Application\Services\SanctionScreeningService;
use Modules\Party\Domain\Enums\PartyRoleType;
use Modules\Party\Domain\Enums\PartyType;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;
use Modules\Party\Domain\Models\PartyAddress;
use Modules\Party\Domain\Models\PartyBankAccount;
use Modules\Party\Domain\Models\PartyContact;

class PartySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed simulated sanctions lists
        $this->seedSanctionsList();

        // 2. Seed corporate hierarchy (Legal Entities)
        $parentHolding = LegalEntity::create([
            'id' => (string) Str::uuid(),
            'name' => 'PT AutoServe Ekosistem Digital Indonesia',
            'short_name' => 'AutoServe Group',
            'entity_type' => 'company',
            'parent_id' => null,
            'npwp' => '01.345.678.9-012.000',
            'nib' => '9120001230001',
            'functional_currency' => 'IDR',
            'fiscal_year_start' => '01-01',
            'ledger_prefix' => 'GRP',
            'is_active' => true,
        ]);

        $subLogistics = LegalEntity::create([
            'id' => (string) Str::uuid(),
            'name' => 'PT Sari Ranah Express Logistik',
            'short_name' => 'SRE Logistics',
            'entity_type' => 'subsidiary',
            'parent_id' => $parentHolding->id,
            'npwp' => '01.345.678.9-012.001',
            'nib' => '9120001230002',
            'functional_currency' => 'IDR',
            'fiscal_year_start' => '01-01',
            'ledger_prefix' => 'LGX',
            'is_active' => true,
        ]);

        $subMall = LegalEntity::create([
            'id' => (string) Str::uuid(),
            'name' => 'PT Duta Properti Megah',
            'short_name' => 'Duta Mall',
            'entity_type' => 'subsidiary',
            'parent_id' => $parentHolding->id,
            'npwp' => '01.345.678.9-012.002',
            'nib' => '9120001230003',
            'functional_currency' => 'IDR',
            'fiscal_year_start' => '01-01',
            'ledger_prefix' => 'MLL',
            'is_active' => true,
        ]);

        // 3. Seed Realistic Parties with Roles, Addresses, Contacts & Bank Accounts
        $partyService = app(PartyService::class);
        $screening = app(SanctionScreeningService::class);

        // Party 1: Carrier (Trans Jaya)
        $p1 = $partyService->create([
            'legal_entity_id' => $subLogistics->id,
            'type' => PartyType::Company->value,
            'name' => 'PT Lintas Samudra Nusantara',
            'short_name' => 'LSN Cargo',
            'npwp' => '02.456.789.0-123.000',
            'nib' => '9120004560001',
            'role' => PartyRoleType::Carrier->value,
            'credit_limit_idr' => 100_000_000,
        ]);
        PartyAddress::create([
            'party_id' => $p1->id,
            'label' => 'headquarters',
            'line1' => 'Jl. Pelabuhan Tanjung Priok No. 12',
            'city' => 'Jakarta Utara',
            'province' => 'DKI Jakarta',
            'postal_code' => '14310',
            'is_primary' => true,
        ]);
        PartyContact::create([
            'party_id' => $p1->id,
            'label' => 'operations',
            'contact_type' => 'phone',
            'value' => '021-4321098',
            'name' => 'Hendra Kusuma',
            'is_primary' => true,
        ]);
        PartyBankAccount::create([
            'party_id' => $p1->id,
            'bank_name' => 'Bank Mandiri',
            'bank_code' => '008',
            'account_number_masked' => 'XXXX-XXXX-1234',
            'account_number_hash' => hash('sha256', '12345678901234'),
            'account_holder_name' => 'PT Lintas Samudra Nusantara',
            'currency' => 'IDR',
            'is_primary' => true,
            'is_verified' => true,
        ]);
        $screening->screen($p1, 'onboarding');

        // Party 2: Mall Anchor Tenant (Kopi Kenangan Sejahtera)
        $p2 = $partyService->create([
            'legal_entity_id' => $subMall->id,
            'type' => PartyType::Company->value,
            'name' => 'PT Kenangan Kopi Nusantara',
            'short_name' => 'Kenangan Coffee',
            'npwp' => '03.567.890.1-234.000',
            'nib' => '9120005670002',
            'role' => PartyRoleType::Tenant->value,
            'credit_limit_idr' => 250_000_000,
        ]);
        PartyAddress::create([
            'party_id' => $p2->id,
            'label' => 'outlet',
            'line1' => 'Duta Mall Ground Floor Unit G-14',
            'city' => 'Banjarmasin',
            'province' => 'Kalimantan Selatan',
            'postal_code' => '70111',
            'is_primary' => true,
        ]);
        PartyContact::create([
            'party_id' => $p2->id,
            'label' => 'store_manager',
            'contact_type' => 'whatsapp',
            'value' => '081198765432',
            'name' => 'Dina Amelia',
            'is_primary' => true,
        ]);
        PartyBankAccount::create([
            'party_id' => $p2->id,
            'bank_name' => 'BCA',
            'bank_code' => '014',
            'account_number_masked' => 'XXXX-XXXX-5678',
            'account_number_hash' => hash('sha256', '87654321095678'),
            'account_holder_name' => 'PT Kenangan Kopi Nusantara',
            'currency' => 'IDR',
            'is_primary' => true,
            'is_verified' => true,
        ]);
        $screening->screen($p2, 'onboarding');

        // Party 3: Supplier Bahan Baku Resto (Supplier Daging Segar)
        $p3 = $partyService->create([
            'type' => PartyType::Company->value,
            'name' => 'CV Sumber Ternak Mandiri',
            'short_name' => 'Sumber Ternak',
            'npwp' => '04.678.901.2-345.000',
            'nib' => '9120006780003',
            'role' => PartyRoleType::Supplier->value,
            'credit_limit_idr' => 75_000_000,
        ]);
        PartyAddress::create([
            'party_id' => $p3->id,
            'label' => 'depot',
            'line1' => 'Kawasan Industri Ruminansia Blok D-5',
            'city' => 'Bogor',
            'province' => 'Jawa Barat',
            'postal_code' => '16110',
            'is_primary' => true,
        ]);
        PartyContact::create([
            'party_id' => $p3->id,
            'label' => 'sales',
            'contact_type' => 'phone',
            'value' => '0251-8345678',
            'name' => 'Agus Priyono',
            'is_primary' => true,
        ]);
        $screening->screen($p3, 'onboarding');

        // Party 4: Shipper Account Corporate (B2B E-commerce)
        $p4 = $partyService->create([
            'type' => PartyType::Company->value,
            'name' => 'PT Mega Niaga Elektronik',
            'short_name' => 'MegaNiaga',
            'npwp' => '05.789.012.3-456.000',
            'nib' => '9120007890004',
            'role' => PartyRoleType::Shipper->value,
            'credit_limit_idr' => 500_000_000,
        ]);
        PartyContact::create([
            'party_id' => $p4->id,
            'label' => 'logistics_pic',
            'contact_type' => 'email',
            'value' => 'supply@meganiaga.co.id',
            'name' => 'Rina Sugiarto',
            'is_primary' => true,
        ]);
        $screening->screen($p4, 'onboarding');

        // Party 5: Perorangan (Distributor & Agent Independen)
        $p5 = $partyService->create([
            'type' => PartyType::Person->value,
            'name' => 'Bambang Pamungkas SE',
            'short_name' => 'BP Distribution',
            'nik' => '3271011508820005',
            'npwp' => '06.890.123.4-567.000',
            'role' => PartyRoleType::Distributor->value,
            'credit_limit_idr' => 25_000_000,
        ]);
        $screening->screen($p5, 'onboarding');
    }

    private function seedSanctionsList(): void
    {
        $sanctions = [
            [
                'source' => 'simulated_un',
                'entry_type' => 'entity',
                'name' => 'Dark Ocean Maritime Trading Co',
                'name_normalized' => 'dark ocean maritime trading co',
                'alias' => 'DOM Trading',
                'country' => 'KP',
                'identifier' => 'IMO-9876543',
                'list_code' => 'UN-SANCT-001',
                'reason' => 'Illicit ship-to-ship transfers and sanctions evasion',
                'is_active' => true,
            ],
            [
                'source' => 'simulated_ofac',
                'entry_type' => 'individual',
                'name' => 'Victor Boutov Shadowman',
                'name_normalized' => 'victor boutov shadowman',
                'alias' => 'The Ghost Merchant',
                'country' => 'RU',
                'identifier' => 'PP-RU-8829104',
                'list_code' => 'OFAC-SDN-402',
                'reason' => 'Proliferation financing and transnational fraud',
                'is_active' => true,
            ],
            [
                'source' => 'simulated_local',
                'entry_type' => 'entity',
                'name' => 'PT Sindikat Investasi Gelap',
                'name_normalized' => 'pt sindikat investasi gelap',
                'alias' => 'SIG Syndicate',
                'country' => 'ID',
                'identifier' => '09.999.888.7-666.000',
                'list_code' => 'PPATK-DTTOT-109',
                'reason' => 'Daftar Terduga Teroris & Pendanaan Terorisme (DTTOT)',
                'is_active' => true,
            ],
            [
                'source' => 'simulated_ofac',
                'entry_type' => 'entity',
                'name' => 'Black Gold Petrochemical Syndicate Ltd',
                'name_normalized' => 'black gold petrochemical syndicate ltd',
                'alias' => 'BlackGold Global',
                'country' => 'IR',
                'identifier' => 'TAX-IR-991204',
                'list_code' => 'OFAC-SDN-719',
                'reason' => 'Exporting sanctioned refined petroleum products',
                'is_active' => true,
            ],
            [
                'source' => 'simulated_eu',
                'entry_type' => 'individual',
                'name' => 'Alexei Rostovsky Cartel',
                'name_normalized' => 'alexei rostovsky cartel',
                'alias' => 'ARC Boss',
                'country' => 'BY',
                'identifier' => 'PP-BY-342019',
                'list_code' => 'EU-CFSP-550',
                'reason' => 'State-sponsored cyber financial diversion',
                'is_active' => true,
            ],
        ];

        foreach ($sanctions as $item) {
            DB::table('pty_sanctions_lists')->insert($item + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Contract\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Contract\Application\Services\ContractService;
use Modules\Contract\Domain\Enums\ContractStatus;
use Modules\Contract\Domain\Enums\ContractType;
use Modules\Contract\Domain\Models\ClauseTemplate;
use Modules\Contract\Domain\Models\Contract;
use Modules\Contract\Domain\Models\ContractMilestone;
use Modules\Contract\Domain\Models\ContractTemplate;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;

class ContractSeeder extends Seeder
{
    public function run(ContractService $service): void
    {
        // ── 1. Library Klausul Standar ─────────────────────────────────
        $clauses = [
            ['code' => 'CL-PARTIES',       'title' => 'Para Pihak',                     'category' => 'general',         'body_template' => 'Perjanjian ini dibuat antara {{first_party}} selaku Pihak Pertama dan {{second_party}} selaku Pihak Kedua.'],
            ['code' => 'CL-OBJECT',        'title' => 'Objek Perjanjian',               'category' => 'general',         'body_template' => 'Pihak Kedua setuju untuk menyediakan layanan/barang sebagaimana tercantum dalam Lampiran A perjanjian ini, terhitung mulai tanggal {{start_date}}.'],
            ['code' => 'CL-VALUE',         'title' => 'Nilai Kontrak',                  'category' => 'payment',         'body_template' => 'Nilai perjanjian ini adalah sebesar {{total_value}} yang dibayarkan sesuai dengan jadwal pembayaran yang disepakati.'],
            ['code' => 'CL-PAYMENT-TERMS', 'title' => 'Syarat Pembayaran',             'category' => 'payment',         'body_template' => 'Pembayaran dilakukan dalam 30 (tiga puluh) hari kalender sejak diterimanya invoice yang sah. Keterlambatan dikenakan denda 0,1% per hari dari jumlah terutang.'],
            ['code' => 'CL-CONFIDENTIAL',  'title' => 'Kerahasiaan',                   'category' => 'confidentiality', 'body_template' => 'Para pihak sepakat untuk menjaga kerahasiaan seluruh informasi yang diperoleh selama perjanjian ini berlaku dan selama 2 (dua) tahun setelah berakhirnya perjanjian.'],
            ['code' => 'CL-FORCE-MAJEURE', 'title' => 'Force Majeure',                  'category' => 'force_majeure',   'body_template' => 'Tidak ada pihak yang bertanggung jawab atas kegagalan atau keterlambatan pelaksanaan kewajiban akibat kejadian di luar kendali yang wajar (force majeure), termasuk bencana alam, perang, atau kebijakan pemerintah.'],
            ['code' => 'CL-TERMINATE',     'title' => 'Pengakhiran Perjanjian',        'category' => 'termination',     'body_template' => 'Perjanjian ini dapat diakhiri oleh salah satu pihak dengan memberikan pemberitahuan tertulis {{notice_days}} hari sebelumnya, atau segera apabila pihak lain melakukan pelanggaran material yang tidak diperbaiki dalam 14 hari.'],
            ['code' => 'CL-GOVERNING-LAW', 'title' => 'Hukum yang Berlaku & Yurisdiksi', 'category' => 'jurisdiction',   'body_template' => 'Perjanjian ini tunduk pada hukum {{governing_law}}. Setiap sengketa diselesaikan melalui {{dispute_forum}}.'],
            ['code' => 'CL-LIABILITY',     'title' => 'Batasan Tanggung Jawab',        'category' => 'liability',       'body_template' => 'Total tanggung jawab masing-masing pihak berdasarkan perjanjian ini tidak akan melebihi total nilai perjanjian yang telah dibayarkan.'],
            ['code' => 'CL-WARRANTY',      'title' => 'Jaminan & Garansi',             'category' => 'warranty',        'body_template' => 'Pihak Kedua menjamin bahwa barang/jasa yang diberikan sesuai dengan spesifikasi yang disepakati dan bebas dari cacat selama periode garansi 12 (dua belas) bulan.'],
        ];

        $clauseModels = [];
        foreach ($clauses as $c) {
            $clauseModels[$c['code']] = ClauseTemplate::firstOrCreate(
                ['code' => $c['code']],
                array_merge($c, ['version' => 1, 'is_standard' => true, 'is_active' => true])
            );
        }

        // ── 2. Template Kontrak ────────────────────────────────────────
        ContractTemplate::firstOrCreate(
            ['code' => 'TPL-SERVICE-STD'],
            [
                'name' => 'Perjanjian Jasa Standar',
                'contract_type' => ContractType::Service->value,
                'description' => 'Template perjanjian jasa umum dengan klausul pembayaran, kerahasiaan, dan force majeure.',
                'default_clause_ids' => [
                    $clauseModels['CL-PARTIES']->id,
                    $clauseModels['CL-OBJECT']->id,
                    $clauseModels['CL-VALUE']->id,
                    $clauseModels['CL-PAYMENT-TERMS']->id,
                    $clauseModels['CL-WARRANTY']->id,
                    $clauseModels['CL-CONFIDENTIAL']->id,
                    $clauseModels['CL-LIABILITY']->id,
                    $clauseModels['CL-FORCE-MAJEURE']->id,
                    $clauseModels['CL-TERMINATE']->id,
                    $clauseModels['CL-GOVERNING-LAW']->id,
                ],
                'required_variables' => ['first_party', 'second_party', 'start_date', 'total_value', 'notice_days', 'governing_law', 'dispute_forum'],
                'is_active' => true,
            ]
        );

        ContractTemplate::firstOrCreate(
            ['code' => 'TPL-NDA-STD'],
            [
                'name' => 'NDA Standar',
                'contract_type' => ContractType::Nda->value,
                'description' => 'Perjanjian Kerahasiaan (Non-Disclosure Agreement) standar.',
                'default_clause_ids' => [
                    $clauseModels['CL-PARTIES']->id,
                    $clauseModels['CL-CONFIDENTIAL']->id,
                    $clauseModels['CL-LIABILITY']->id,
                    $clauseModels['CL-GOVERNING-LAW']->id,
                ],
                'required_variables' => ['first_party', 'second_party', 'governing_law', 'dispute_forum'],
                'is_active' => true,
            ]
        );

        // ── 3. Demo Contracts (menggunakan party & legal entity dari PartySeeder) ─
        $le = LegalEntity::where('is_active', true)->first();
        $parties = Party::where('is_active', true)->whereNull('merged_into_id')->take(4)->get();

        if (! $le || $parties->count() < 2) {
            return;
        }

        [$p1, $p2] = [$parties->first(), $parties->skip(1)->first()];

        // Demo Contract 1: Active service contract
        $existing = Contract::where('contract_number', 'like', 'CTR/%')->first();
        if (! $existing) {
            $contract = $service->createContract([
                'legal_entity_id' => $le->id,
                'title' => 'Perjanjian Jasa Pemeliharaan IT Platform – Fase I',
                'contract_type' => ContractType::Service->value,
                'total_value_idr' => 240_000_000,
                'currency' => 'IDR',
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => now()->addMonths(9)->toDateString(),
                'notice_period_days' => 30,
                'auto_renew' => true,
                'renewal_period_months' => 12,
                'governing_law' => 'Indonesia',
                'dispute_forum' => 'BANI Jakarta',
                'created_by_name' => 'System Seeder',
                'parties' => [
                    ['party_id' => $p1->id, 'role' => 'first_party',  'signing_order' => 1],
                    ['party_id' => $p2->id, 'role' => 'second_party', 'signing_order' => 2],
                ],
                'variables' => [
                    'first_party' => $p1->name,
                    'second_party' => $p2->name,
                    'start_date' => now()->subMonths(3)->format('d F Y'),
                    'total_value' => 'Rp 240.000.000,00',
                    'notice_days' => '30',
                    'governing_law' => 'Indonesia',
                    'dispute_forum' => 'BANI Jakarta',
                ],
            ]);

            // Manually transition to active (skip approval for seed)
            $contract->update(['status' => ContractStatus::Active->value, 'activated_at' => now()->subMonths(3)]);

            // Add milestone
            ContractMilestone::create([
                'contract_id' => $contract->id,
                'title' => 'Deliverable: Laporan Pemeliharaan Kuartal 1',
                'description' => 'Penyerahan laporan hasil pemeliharaan infrastruktur Q1',
                'due_date' => now()->subMonth()->toDateString(),
                'responsible_role' => 'second_party',
                'status' => 'completed',
                'completed_at' => now()->subMonth()->subDays(3),
                'amount_idr' => 60_000_000,
            ]);

            ContractMilestone::create([
                'contract_id' => $contract->id,
                'title' => 'Deliverable: Laporan Pemeliharaan Kuartal 2',
                'due_date' => now()->addMonths(2)->toDateString(),
                'responsible_role' => 'second_party',
                'status' => 'pending',
                'amount_idr' => 60_000_000,
            ]);
        }

        // Demo Contract 2: Draft NDA
        $c2Exists = Contract::where('title', 'like', '%NDA%')->first();
        if (! $c2Exists && $parties->count() >= 3) {
            $p3 = $parties->skip(2)->first();
            $ndaContract = $service->createContract([
                'legal_entity_id' => $le->id,
                'title' => 'NDA Kerja Sama Strategis – Distribusi Regional Kalimantan',
                'contract_type' => ContractType::Nda->value,
                'total_value_idr' => 0,
                'currency' => 'IDR',
                'governing_law' => 'Indonesia',
                'dispute_forum' => 'Pengadilan Negeri',
                'created_by_name' => 'System Seeder',
                'parties' => [
                    ['party_id' => $p1->id, 'role' => 'first_party',  'signing_order' => 1],
                    ['party_id' => $p3->id, 'role' => 'second_party', 'signing_order' => 2],
                ],
            ]);
            // Keep as draft
        }
    }
}

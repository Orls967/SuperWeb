<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Core\Domain\Models\DsComponent;
use Modules\Core\Domain\Models\DsComponentHistory;
use Modules\Core\Domain\Models\DsToken;
use Modules\Core\Domain\Models\DsUserPref;
use Modules\Core\Domain\Models\UxFinding;
use Modules\Core\Domain\Models\UxIteration;

final class DesignSystemSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::first() ?? User::create([
            'name' => 'Design System Lead',
            'email' => 'ds-owner@superweb.test',
            'password' => bcrypt('password'),
        ]);

        $actorId = $adminUser->id;

        // 1. Seed 32 Komponen Kanonikal
        $componentsDef = [
            // Action
            ['name' => 'button', 'display_name' => 'Tombol Aksi (Button)', 'category' => 'action', 'status' => 'released', 'version' => '1.2.0', 'wcag' => 'AAA', 'props' => ['variant' => 'primary', 'size' => 'md', 'type' => 'button', 'disabled' => false]],
            ['name' => 'theme-switch', 'display_name' => 'Pengubah Tema (Theme Switcher)', 'category' => 'action', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['current' => 'light']],
            ['name' => 'export-toolbar', 'display_name' => 'Bilah Alat Ekspor', 'category' => 'action', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['targetUrl' => 'string']],

            // Form
            ['name' => 'input', 'display_name' => 'Input Teks Standar', 'category' => 'form', 'status' => 'released', 'version' => '1.1.0', 'wcag' => 'AA', 'props' => ['name' => 'string', 'label' => 'string', 'required' => false]],
            ['name' => 'select', 'display_name' => 'Menu Pilihan (Dropdown Select)', 'category' => 'form', 'status' => 'released', 'version' => '1.1.0', 'wcag' => 'AA', 'props' => ['name' => 'string', 'options' => 'array']],
            ['name' => 'combobox', 'display_name' => 'Kotak Kombo Cari & Pilih', 'category' => 'form', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['options' => 'array', 'searchable' => true]],
            ['name' => 'date', 'display_name' => 'Pemilih Tanggal (Date Picker)', 'category' => 'form', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['name' => 'string', 'value' => 'string']],
            ['name' => 'money-input', 'display_name' => 'Input Moneter / Mata Uang', 'category' => 'form', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['currency' => 'IDR', 'cents' => true]],
            ['name' => 'upload', 'display_name' => 'Unggah Berkas Aksesibel', 'category' => 'form', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['accept' => 'string', 'maxSizeMb' => 10]],

            // Navigation
            ['name' => 'breadcrumb', 'display_name' => 'Navigasi Jejak (Breadcrumb)', 'category' => 'navigation', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['items' => 'array']],
            ['name' => 'tabs', 'display_name' => 'Bilah Tab Navigasi', 'category' => 'navigation', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['tabs' => 'array', 'active' => 'string']],
            ['name' => 'stepper', 'display_name' => 'Penunjuk Langkah (Stepper)', 'category' => 'navigation', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['steps' => 'array', 'current' => 1]],
            ['name' => 'command-palette', 'display_name' => 'Palet Perintah (Ctrl+K)', 'category' => 'navigation', 'status' => 'released', 'version' => '1.2.0', 'wcag' => 'AA', 'props' => ['placeholder' => 'string']],

            // Layout
            ['name' => 'shell', 'display_name' => 'Kerangka Aplikasi (App Shell)', 'category' => 'layout', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['title' => 'string']],
            ['name' => 'topbar', 'display_name' => 'Bilah Navigasi Atas (Topbar)', 'category' => 'layout', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['user' => 'object']],
            ['name' => 'sidebar', 'display_name' => 'Bilah Sisi Modular (Sidebar)', 'category' => 'layout', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['menuItems' => 'array']],

            // Data Display
            ['name' => 'table', 'display_name' => 'Tabel Data Responsif (375px Card Stack)', 'category' => 'data-display', 'status' => 'released', 'version' => '1.2.0', 'wcag' => 'AA', 'props' => ['columns' => 'array', 'rows' => 'array', 'paginator' => 'object']],
            ['name' => 'card', 'display_name' => 'Kartu Konten (Card Container)', 'category' => 'data-display', 'status' => 'released', 'version' => '1.1.0', 'wcag' => 'AA', 'props' => ['title' => 'string']],
            ['name' => 'stat', 'display_name' => 'Kartu Statistik KPI', 'category' => 'data-display', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['label' => 'string', 'value' => 'mixed']],
            ['name' => 'chart-bar', 'display_name' => 'Grafik Batang SVG Murni', 'category' => 'data-display', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['values' => 'array', 'labels' => 'array']],
            ['name' => 'chart-line', 'display_name' => 'Grafik Garis & Area SVG Murni', 'category' => 'data-display', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['values' => 'array', 'labels' => 'array']],
            ['name' => 'sparkline', 'display_name' => 'Garis Tren Mini (Sparkline)', 'category' => 'data-display', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['data' => 'array']],
            ['name' => 'timeline', 'display_name' => 'Garis Waktu Peristiwa (Timeline)', 'category' => 'data-display', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['items' => 'array']],
            ['name' => 'badge-status', 'display_name' => 'Lencana Status Semantik', 'category' => 'data-display', 'status' => 'released', 'version' => '1.1.0', 'wcag' => 'AAA', 'props' => ['status' => 'string']],
            ['name' => 'contrast-badge', 'display_name' => 'Lencana Rasio Kontras A1', 'category' => 'data-display', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AAA', 'props' => ['fgHex' => 'string', 'bgHex' => 'string']],

            // Feedback & States
            ['name' => 'modal-confirm', 'display_name' => 'Dialog Konfirmasi Fokus Terjebak', 'category' => 'feedback', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['name' => 'string', 'title' => 'string']],
            ['name' => 'toast', 'display_name' => 'Notifikasi Mengambang (Toast)', 'category' => 'feedback', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['type' => 'string', 'message' => 'string']],
            ['name' => 'error', 'display_name' => 'Pesan Galat & Alert Aksesibel', 'category' => 'feedback', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['message' => 'string']],
            ['name' => 'empty', 'display_name' => 'Status Kosong (Empty State)', 'category' => 'feedback', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['title' => 'string']],
            ['name' => 'skeleton', 'display_name' => 'Pemuat Tulang (Skeleton Shimmer)', 'category' => 'feedback', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['type' => 'string']],
            ['name' => 'filter-bar', 'display_name' => 'Bilah Filter Terpadu', 'category' => 'data-display', 'status' => 'released', 'version' => '1.0.0', 'wcag' => 'AA', 'props' => ['filters' => 'array']],

            // Komponen dalam Tahapan SM Lainnya (Draft, In Review, Approved, Deprecated, Retired)
            ['name' => 'rating-stars', 'display_name' => 'Bintang Penilaian Interaktif', 'category' => 'form', 'status' => 'approved', 'version' => '0.9.0-rc', 'wcag' => 'AA', 'props' => ['value' => 5]],
            ['name' => 'audio-player', 'display_name' => 'Pemutar Audio Semantik', 'category' => 'data-display', 'status' => 'in_review', 'version' => '0.8.0-beta', 'wcag' => 'AA', 'props' => ['src' => 'string']],
            ['name' => 'virtual-scroll', 'display_name' => 'Penggulung Virtual Kinerja Tinggi', 'category' => 'data-display', 'status' => 'draft', 'version' => '0.1.0-alpha', 'wcag' => 'A', 'props' => ['items' => 'array']],
            ['name' => 'legacy-badge', 'display_name' => 'Lencana Versi Lama (Legacy)', 'category' => 'data-display', 'status' => 'deprecated', 'version' => '0.5.0', 'wcag' => 'A', 'props' => ['text' => 'string']],
            ['name' => 'old-datepicker', 'display_name' => 'Pemilih Tanggal Non-Aksesibel Lama', 'category' => 'form', 'status' => 'retired', 'version' => '0.2.0', 'wcag' => 'A', 'props' => []],
        ];

        foreach ($componentsDef as $c) {
            $comp = DsComponent::updateOrCreate(
                ['name' => $c['name']],
                [
                    'display_name' => $c['display_name'],
                    'category' => $c['category'],
                    'status' => $c['status'],
                    'current_version' => $c['version'],
                    'wcag_level' => $c['wcag'],
                    'spec_doc_url' => 'docs/design/components/'.$c['name'].'.md',
                    'notes' => 'Komponen kanonikal standar teruji.',
                    'props_schema' => $c['props'],
                    'slots_schema' => ['default' => 'Konten utama komponen'],
                    'events_schema' => [],
                    'owner_id' => $actorId,
                    'idempotency_key' => 'seeder:ds:'.$c['name'],
                ]
            );

            // Buat entri riwayat transisi
            DsComponentHistory::firstOrCreate(
                [
                    'component_id' => $comp->id,
                    'to_status' => $c['status'],
                ],
                [
                    'from_status' => $c['status'] === 'draft' ? 'none' : 'draft',
                    'actor_id' => $actorId,
                    'reason' => 'Seeding inisial siklus hidup komponen',
                    'transitioned_at' => now()->subDays(5),
                ]
            );

            // Tambahkan transisi sebelumnya untuk komponen released
            if ($c['status'] === 'released') {
                DsComponentHistory::firstOrCreate(
                    [
                        'component_id' => $comp->id,
                        'from_status' => 'approved',
                        'to_status' => 'released',
                    ],
                    [
                        'actor_id' => $actorId,
                        'reason' => 'Rilis resmi produksi kanonikal',
                        'transitioned_at' => now()->subDays(1),
                    ]
                );
            }
        }

        // 2. Seed 4 Iterasi Riset UX
        $it1 = UxIteration::updateOrCreate(
            ['iteration_number' => 1],
            [
                'name' => 'Iterasi 1 — Navigasi & Shell Dasar',
                'sus_score' => 68.5,
                'task_success_rate' => 78.0,
                'participants_count' => 8,
                'tested_at' => now()->subMonths(3),
                'methodology' => 'moderated_remote_testing',
                'summary' => 'Uji perdana shell modular dan tata letak topbar/sidebar.',
            ]
        );

        $it2 = UxIteration::updateOrCreate(
            ['iteration_number' => 2],
            [
                'name' => 'Iterasi 2 — Komponen Form & Keyboard Trap',
                'sus_score' => 74.0,
                'task_success_rate' => 83.5,
                'participants_count' => 10,
                'tested_at' => now()->subMonths(2),
                'methodology' => 'moderated_remote_testing',
                'summary' => 'Fokus pada modal konfirmasi fokus trap dan perbaikan input label.',
            ]
        );

        $it3 = UxIteration::updateOrCreate(
            ['iteration_number' => 3],
            [
                'name' => 'Iterasi 3 — Responsivitas 375px & Kontras WCAG AA',
                'sus_score' => 81.5,
                'task_success_rate' => 89.0,
                'participants_count' => 12,
                'tested_at' => now()->subMonth(),
                'methodology' => 'unmoderated_task_based',
                'summary' => 'Uji tumpukan kartu tabel data di viewport layar 375px.',
            ]
        );

        $it4 = UxIteration::updateOrCreate(
            ['iteration_number' => 4],
            [
                'name' => 'Iterasi 4 — Tema Gelap & Kontras Tinggi (HC)',
                'sus_score' => 87.0,
                'task_success_rate' => 94.5,
                'participants_count' => 12,
                'tested_at' => now()->subDays(7),
                'methodology' => 'unmoderated_task_based',
                'summary' => 'Verifikasi kejelasan palet warna tema gelap dan keterbacaan teks.',
            ]
        );

        // 3. Seed 14 Temuan Riset UX
        $findingsDef = [
            ['it' => $it1->id, 'comp' => 'shell', 'cat' => 'navigation', 'sev' => 'blocker', 'desc' => 'Menu sidebar tidak tertutup saat tombol Esc ditekan di ponsel.', 'rec' => 'Pasang event listener @keydown.escape pada container drawer.', 'res' => true],
            ['it' => $it1->id, 'comp' => 'command-palette', 'cat' => 'accessibility', 'sev' => 'major', 'desc' => 'Fokus tab melompat keluar dari modal dialog pencarian.', 'rec' => 'Terapkan fokus trap siklis pada x-ds.command-palette.', 'res' => true],
            ['it' => $it2->id, 'comp' => 'modal-confirm', 'cat' => 'accessibility', 'sev' => 'major', 'desc' => 'Screen reader tidak membacakan judul dialog saat modal dibuka.', 'rec' => 'Tambahkan aria-labelledby yang merujuk pada elemen h3 modal.', 'res' => true],
            ['it' => $it2->id, 'comp' => 'input', 'cat' => 'form_validation', 'sev' => 'minor', 'desc' => 'Pesan galat validasi tidak terhubung dengan atribut aria-describedby.', 'rec' => 'Hubungkan id pesan galat ke aria-describedby pada input.', 'res' => true],
            ['it' => $it2->id, 'comp' => 'money-input', 'cat' => 'interaction', 'sev' => 'suggestion', 'desc' => 'Pengguna mengharapkan pemformatan otomatis titik ribuan saat mengetik.', 'rec' => 'Integrasikan format angka lokal IDR secara dinamis.', 'res' => true],
            ['it' => $it3->id, 'comp' => 'table', 'cat' => 'responsive', 'sev' => 'blocker', 'desc' => 'Tabel transaksi mengalami scroll horizontal parah di layar 375px.', 'rec' => 'Gunakan mode kartu vertikal berjejar otomatis di @media max-width 375px.', 'res' => true],
            ['it' => $it3->id, 'comp' => 'stat', 'cat' => 'visual_contrast', 'sev' => 'major', 'desc' => 'Warna teks subjudul abu-abu muda memiliki rasio kontras < 3:1.', 'rec' => 'Tingkatkan kecerahan warna teks dari #94A3B8 menjadi #475569 di tema terang.', 'res' => true],
            ['it' => $it3->id, 'comp' => 'toast', 'cat' => 'accessibility', 'sev' => 'minor', 'desc' => 'Toast otomatis menghilang terlalu cepat sebelum pembaca layar selesai membaca.', 'rec' => 'Perpanjang durasi menjadi 5 detik dan pasang role="status".', 'res' => true],
            ['it' => $it4->id, 'comp' => 'theme-switch', 'cat' => 'interaction', 'sev' => 'minor', 'desc' => 'Ikon tema tidak menunjukkan status tema yang aktif secara eksplisit.', 'rec' => 'Sertakan teks label dan tooltip yang menandai tema terpilih.', 'res' => true],
            ['it' => $it4->id, 'comp' => 'button', 'cat' => 'accessibility', 'sev' => 'suggestion', 'desc' => 'Area sentuh tombol ukuran xs agak sulit ditekan pengguna jari besar.', 'rec' => 'Pastikan min-height 44px untuk touch target pada perangkat mobile.', 'res' => false],
            ['it' => $it4->id, 'comp' => 'sparkline', 'cat' => 'visual_contrast', 'sev' => 'minor', 'desc' => 'Ketebalan garis tren 1px sulit dilihat pada latar belakang gelap.', 'rec' => 'Tingkatkan stroke-width garis menjadi 2px konsisten.', 'res' => true],
            ['it' => $it4->id, 'comp' => 'combobox', 'cat' => 'interaction', 'sev' => 'major', 'desc' => 'Daftar dropdown tertutup tiba-tiba saat melakukan scroll cepat.', 'rec' => 'Terapkan listener klik di luar (@click.outside) dengan delay pelindung.', 'res' => true],
            ['it' => $it4->id, 'comp' => null, 'cat' => 'performance', 'sev' => 'minor', 'desc' => 'Animasi transisi terasa sedikit lambat di perangkat low-end Android.', 'rec' => 'Sediakan toggle reduced-motion untuk menonaktifkan transisi CSS.', 'res' => true],
            ['it' => $it4->id, 'comp' => 'filter-bar', 'cat' => 'interaction', 'sev' => 'suggestion', 'desc' => 'Tombol reset filter sebaiknya selalu terlihat bila ada parameter aktif.', 'rec' => 'Tampilkan tombol Reset dinamis saat query string tidak kosong.', 'res' => true],
        ];

        foreach ($findingsDef as $fd) {
            $compModel = $fd['comp'] ? DsComponent::where('name', $fd['comp'])->first() : null;
            UxFinding::firstOrCreate(
                [
                    'iteration_id' => $fd['it'],
                    'description' => $fd['desc'],
                ],
                [
                    'component_id' => $compModel?->id,
                    'category' => $fd['cat'],
                    'severity' => $fd['sev'],
                    'recommendation' => $fd['rec'],
                    'is_resolved' => $fd['res'],
                    'resolved_at' => $fd['res'] ? now() : null,
                ]
            );
        }

        // 4. Seed Token Tema di core_ds_tokens
        $themesTokens = [
            'light' => [
                'color.primary' => '#2563eb',
                'color.primary.hover' => '#1d4ed8',
                'color.success' => '#16a34a',
                'color.danger' => '#dc2626',
                'color.warning' => '#d97706',
                'color.bg.canvas' => '#f8fafc',
                'color.bg.surface' => '#ffffff',
                'color.border.default' => '#e2e8f0',
                'color.text.primary' => '#0f172a',
                'color.text.secondary' => '#475569',
                'color.text.muted' => '#64748b',
            ],
            'dark' => [
                'color.primary' => '#3b82f6',
                'color.primary.hover' => '#60a5fa',
                'color.success' => '#22c55e',
                'color.danger' => '#ef4444',
                'color.warning' => '#f59e0b',
                'color.bg.canvas' => '#020617',
                'color.bg.surface' => '#0f172a',
                'color.border.default' => '#1e293b',
                'color.text.primary' => '#f8fafc',
                'color.text.secondary' => '#cbd5e1',
                'color.text.muted' => '#94a3b8',
            ],
            'hc' => [
                'color.primary' => '#00ffff',
                'color.primary.hover' => '#80ffff',
                'color.success' => '#00ff00',
                'color.danger' => '#ff0000',
                'color.warning' => '#ffff00',
                'color.bg.canvas' => '#000000',
                'color.bg.surface' => '#000000',
                'color.border.default' => '#ffffff',
                'color.text.primary' => '#ffffff',
                'color.text.secondary' => '#ffffff',
                'color.text.muted' => '#ffff00',
            ],
        ];

        foreach ($themesTokens as $theme => $tokens) {
            foreach ($tokens as $k => $val) {
                DsToken::updateOrCreate(
                    [
                        'theme' => $theme,
                        'token_key' => $k,
                    ],
                    [
                        'token_type' => 'color',
                        'raw_value' => $val,
                        'resolved_value' => $val,
                        'is_semantic' => true,
                        'description' => "Token warna {$k} untuk tema {$theme}",
                    ]
                );
            }
        }

        // 5. Seed Preferensi Pengguna Awal
        DsUserPref::updateOrCreate(
            ['user_id' => $adminUser->id],
            [
                'theme' => 'light',
                'density' => 'comfortable',
                'font_scale' => 100,
                'reduced_motion' => false,
                'high_contrast' => false,
            ]
        );
    }
}

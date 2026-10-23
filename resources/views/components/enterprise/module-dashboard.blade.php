@props([
    'module' => 'Enterprise',
    'title' => null,
    'subtitle' => null,
    'kpis' => null,
    'tableId' => null,
    'records' => null,
    'simulatorMode' => null,
    'actionTitle' => null,
    'actionRoute' => null,
    'tableTitle' => 'Audit Trail & Hash-Chain Verification',
])

@php
    $modName = $module;
    $errors = $errors ?? session('errors', new \Illuminate\Support\ViewErrorBag());
    $computedTitle = $title ?? "Dashboard Eksekutif Modul {$modName}";
    $computedSubtitle = $subtitle ?? "Pemantauan real-time metrik domain {$modName}, integritas transaksi double-entry, dan audit trail SHA-256.";
    $tId = $tableId ?? 'table-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $modName));
    
    // Domain-specific configuration mappings
    $domainConfigs = [
        'Banking' => [
            'mode' => 'double-entry',
            'simTitle' => 'Simulasi Rekonsiliasi Double-Entry & Likuiditas Kliring',
            'formAction' => 'Posting Transaksi Pembukuan Bank & Kliring',
            'amountLabel' => 'Nominal Posting (Integer Minor-Unit IDR)',
            'chart1' => ['title' => 'Arus Kas Masuk (IDR)', 'val' => 'Rp 42.850.000.000', 'type' => 'sparkline', 'color' => 'emerald', 'data' => [28, 35, 42, 39, 45, 52, 60]],
            'chart2' => ['title' => 'Kliring Antar-Bank Harian', 'val' => '1.420 TX', 'type' => 'bar', 'color' => 'indigo', 'data' => ['Sen' => 180, 'Sel' => 240, 'Rab' => 210, 'Kam' => 290, 'Jum' => 320]],
            'chart3' => ['title' => 'Simetri Debit-Kredit', 'val' => '100%', 'type' => 'radial', 'color' => 'emerald', 'data' => [100]],
        ],
        'Finance' => [
            'mode' => 'double-entry',
            'simTitle' => 'Simulasi Anuitas Kredit & Portofolio Pembiayaan',
            'formAction' => 'Pencairan Kredit Multifinance & Agunan',
            'amountLabel' => 'Pokok Pinjaman Pembiayaan (Integer IDR)',
            'chart1' => ['title' => 'Penyaluran Pinjaman Aktif', 'val' => 'Rp 18.250.000.000', 'type' => 'sparkline', 'color' => 'indigo', 'data' => [12, 14, 15, 17, 16, 18, 19]],
            'chart2' => ['title' => 'Tingkat NPL (Kredit Bermasalah)', 'val' => '0.8%', 'type' => 'radial', 'color' => 'emerald', 'data' => [98]],
            'chart3' => ['title' => 'Arus Angsuran Pokok & Bunga', 'val' => 'Rp 2.450.000.000', 'type' => 'waterfall', 'color' => 'blue', 'data' => [100, 30, -15, -10, 105]],
        ],
        'Treasury' => [
            'mode' => 'double-entry',
            'simTitle' => 'Simulasi Cash Pooling Grup & FX Hedging',
            'formAction' => 'Eksekusi Rebalancing Likuiditas Treasury',
            'amountLabel' => 'Nominal Sweep Kas Pooling (Integer IDR)',
            'chart1' => ['title' => 'Total Likuiditas Kas Pooling', 'val' => 'Rp 85.400.000.000', 'type' => 'sparkline', 'color' => 'emerald', 'data' => [65, 72, 70, 78, 82, 85, 88]],
            'chart2' => ['title' => 'Eksposur Valas FX Hedged', 'val' => '94.5%', 'type' => 'radial', 'color' => 'cyan', 'data' => [95]],
            'chart3' => ['title' => 'Yield Investasi Pasar Uang', 'val' => '6.45%', 'type' => 'bar', 'color' => 'indigo', 'data' => ['W1' => 61, 'W2' => 63, 'W3' => 64, 'W4' => 65]],
        ],
        'Hcm' => [
            'mode' => 'payroll',
            'simTitle' => 'Simulasi Penggajian, PPh 21 TER (PP 58/2023) & BPJS',
            'formAction' => 'Proses Penggajian Bulanan & Insentif Kinerja',
            'amountLabel' => 'Total Anggaran Payroll Kotor (Integer IDR)',
            'chart1' => ['title' => 'Total Beban Penggajian Grup', 'val' => 'Rp 12.650.000.000', 'type' => 'sparkline', 'color' => 'purple', 'data' => [10, 11, 11, 12, 12, 13, 13]],
            'chart2' => ['title' => 'Kepatuhan Setoran BPJS & PPh 21', 'val' => '100%', 'type' => 'radial', 'color' => 'emerald', 'data' => [100]],
            'chart3' => ['title' => 'Bounty & Gig Freelance Payout', 'val' => '482 Task', 'type' => 'bar', 'color' => 'indigo', 'data' => ['M1' => 90, 'M2' => 110, 'M3' => 135, 'M4' => 147]],
        ],
        'Hospital' => [
            'mode' => 'hospital',
            'simTitle' => 'Simulasi Triase ESI & Estimasi Klaim INA-CBG',
            'formAction' => 'Registrasi Pasien, Rekam Medis & Klaim Asuransi',
            'amountLabel' => 'Estimasi Tarif CBG Paket Perawatan (Integer IDR)',
            'chart1' => ['title' => 'Tingkat Okupansi Tempat Tidur (BOR)', 'val' => '84.2%', 'type' => 'radial', 'color' => 'rose', 'data' => [84]],
            'chart2' => ['title' => 'Klaim INA-CBG Terverifikasi', 'val' => 'Rp 8.920.000.000', 'type' => 'sparkline', 'color' => 'emerald', 'data' => [55, 62, 70, 75, 82, 85, 89]],
            'chart3' => ['title' => 'Waktu Tunggu Triase IGD', 'val' => '8.5 Menit', 'type' => 'bar', 'color' => 'cyan', 'data' => ['ESI1' => 1, 'ESI2' => 4, 'ESI3' => 12, 'ESI4' => 18]],
        ],
        'Manufacturing' => [
            'mode' => 'mrp-oee',
            'simTitle' => 'Simulasi Perencanaan Kebutuhan Material (MRP) & OEE Mesin',
            'formAction' => 'Penerbitan Work Order Produksi Pabrik',
            'amountLabel' => 'Biaya Standar BOM Work Order (Integer IDR)',
            'chart1' => ['title' => 'Overall Equipment Effectiveness (OEE)', 'val' => '88.6%', 'type' => 'radial', 'color' => 'amber', 'data' => [89]],
            'chart2' => ['title' => 'Output Produksi Selesai (Unit)', 'val' => '24.500 Unit', 'type' => 'sparkline', 'color' => 'indigo', 'data' => [18, 20, 21, 22, 23, 24, 25]],
            'chart3' => ['title' => 'Scrap / Defect Rate Kualitas', 'val' => '0.42%', 'type' => 'bar', 'color' => 'emerald', 'data' => ['L1' => 5, 'L2' => 4, 'L3' => 3, 'L4' => 4]],
        ],
        'Mining' => [
            'mode' => 'mining',
            'simTitle' => 'Simulasi Neraca Massa Smelter & Kewajiban PNBP Royalti',
            'formAction' => 'Pencatatan Tiket Timbangan & Pengapalan Bijih',
            'amountLabel' => 'Nilai Royalti PNBP Terutang (Integer IDR)',
            'chart1' => ['title' => 'Tonase Ekstraksi Bijih (WMT)', 'val' => '325.000 MT', 'type' => 'sparkline', 'color' => 'amber', 'data' => [250, 270, 290, 305, 315, 320, 325]],
            'chart2' => ['title' => 'Smelter Metal Recovery Rate', 'val' => '91.4%', 'type' => 'radial', 'color' => 'emerald', 'data' => [91]],
            'chart3' => ['title' => 'Setoran PNBP SIMBARA ESDM', 'val' => 'Rp 14.800.000.000', 'type' => 'bar', 'color' => 'amber', 'data' => ['Q1' => 32, 'Q2' => 36, 'Q3' => 38, 'Q4' => 42]],
        ],
        'Fleet' => [
            'mode' => 'psak73',
            'simTitle' => 'Simulasi Amortisasi Sewa Pembiayaan Armada PSAK 73',
            'formAction' => 'Kontrak Sewa Kendaraan Operasional & Telematika',
            'amountLabel' => 'Nilai Kontrak Sewa Armada (Integer IDR)',
            'chart1' => ['title' => 'Utilitas Armada Aktif', 'val' => '93.5%', 'type' => 'radial', 'color' => 'blue', 'data' => [94]],
            'chart2' => ['title' => 'Nilai Aset Hak Pakai ROU', 'val' => 'Rp 34.200.000.000', 'type' => 'sparkline', 'color' => 'indigo', 'data' => [28, 30, 31, 32, 33, 34, 34]],
            'chart3' => ['title' => 'Efisiensi BBM & Emisi CO2', 'val' => '-14.2%', 'type' => 'bar', 'color' => 'emerald', 'data' => ['B1' => 15, 'B2' => 14, 'B3' => 12, 'B4' => 11]],
        ],
        'Ev' => [
            'mode' => 'psak73',
            'simTitle' => 'Simulasi Sesi Pengisian OCPP 2.0.1 & Tarif Peak ToU',
            'formAction' => 'Otorisasi Transaksi Charging Station EV',
            'amountLabel' => 'Tarif KWh & Layanan Charging (Integer IDR)',
            'chart1' => ['title' => 'Total Energi Tersalur (MWh)', 'val' => '1.240 MWh', 'type' => 'sparkline', 'color' => 'teal', 'data' => [80, 95, 110, 130, 160, 190, 220]],
            'chart2' => ['title' => 'Ketersediaan Charger (Uptime)', 'val' => '99.8%', 'type' => 'radial', 'color' => 'emerald', 'data' => [100]],
            'chart3' => ['title' => 'Distribusi Sesi Peak vs Off-Peak', 'val' => '72% ToU', 'type' => 'bar', 'color' => 'cyan', 'data' => ['Peak' => 72, 'Off' => 28]],
        ],
        'Egy' => [
            'mode' => 'mining',
            'simTitle' => 'Simulasi Smart Grid Microgrid, PPA & Sertifikat REC',
            'formAction' => 'Pencatatan Pasokan Daya Grid & Transaksi PPA',
            'amountLabel' => 'Nilai Kontrak PPA Pasokan Listrik (Integer IDR)',
            'chart1' => ['title' => 'Generasi Energi Surya & Biomassa', 'val' => '4.850 MWh', 'type' => 'sparkline', 'color' => 'amber', 'data' => [32, 38, 41, 45, 46, 48, 51]],
            'chart2' => ['title' => 'Stabilitas Frekuensi Grid (Hz)', 'val' => '50.02 Hz', 'type' => 'radial', 'color' => 'emerald', 'data' => [99]],
            'chart3' => ['title' => 'Penerbitan Sertifikat REC Hijau', 'val' => '12.500 REC', 'type' => 'bar', 'color' => 'emerald', 'data' => ['B1' => 28, 'B2' => 31, 'B3' => 34, 'B4' => 38]],
        ],
        'Edu' => [
            'mode' => 'telecom-media',
            'simTitle' => 'Simulasi Alokasi Hibah Riset Ilmiah & Royalti IP',
            'formAction' => 'Pemberian Hibah Riset & Sertifikasi Akademik',
            'amountLabel' => 'Nilai Alokasi Dana Riset (Integer IDR)',
            'chart1' => ['title' => 'Peserta Sertifikasi Aktif', 'val' => '14.280 Siswa', 'type' => 'sparkline', 'color' => 'indigo', 'data' => [9, 10, 11, 12, 13, 14, 15]],
            'chart2' => ['title' => 'Tingkat Kelulusan Uji Kompetensi', 'val' => '92.4%', 'type' => 'radial', 'color' => 'emerald', 'data' => [92]],
            'chart3' => ['title' => 'Penyaluran Hibah Riset Kampus', 'val' => 'Rp 6.400.000.000', 'type' => 'bar', 'color' => 'blue', 'data' => ['Q1' => 12, 'Q2' => 15, 'Q3' => 18, 'Q4' => 21]],
        ],
        'Med' => [
            'mode' => 'telecom-media',
            'simTitle' => 'Simulasi Bagi Hasil Box Office & Royalti Lisensi Tayang',
            'formAction' => 'Kontrak Distribusi Konten & Royalti Siaran',
            'amountLabel' => 'Nilai Lisensi Hak Siar Media (Integer IDR)',
            'chart1' => ['title' => 'Total Penonton Box Office', 'val' => '3.850.000 Tiket', 'type' => 'sparkline', 'color' => 'rose', 'data' => [20, 25, 29, 32, 35, 37, 39]],
            'chart2' => ['title' => 'Rasio Royalti Kreator Terbayar', 'val' => '98.5%', 'type' => 'radial', 'color' => 'emerald', 'data' => [98]],
            'chart3' => ['title' => 'Pendapatan Iklan Program Siaran', 'val' => 'Rp 5.200.000.000', 'type' => 'bar', 'color' => 'purple', 'data' => ['P1' => 10, 'P2' => 14, 'P3' => 18, 'P4' => 22]],
        ],
        'Ret' => [
            'mode' => 'telecom-media',
            'simTitle' => 'Simulasi Dynamic Markdown Ritel & Optimasi Dark Store',
            'formAction' => 'Penerimaan Batch Dark Store & Penyesuaian Harga',
            'amountLabel' => 'Nilai GMV Transaksi Ritel Harian (Integer IDR)',
            'chart1' => ['title' => 'Volume Order Dark Store (20 Menit)', 'val' => '18.420 Order', 'type' => 'sparkline', 'color' => 'emerald', 'data' => [12, 13, 14, 16, 17, 18, 19]],
            'chart2' => ['title' => 'Akurasi Pick & Pack Pesanan', 'val' => '99.4%', 'type' => 'radial', 'color' => 'emerald', 'data' => [99]],
            'chart3' => ['title' => 'Margin Kotor Setelah Promo', 'val' => '22.8%', 'type' => 'bar', 'color' => 'cyan', 'data' => ['W1' => 21, 'W2' => 22, 'W3' => 23, 'W4' => 23]],
        ],
        'Tlx' => [
            'mode' => 'telecom-media',
            'simTitle' => 'Simulasi Jaminan SLA Network Slice 5G & Roaming',
            'formAction' => 'Penyediaan Slice Jaringan Dedicated 5G',
            'amountLabel' => 'Tarif Kontrak SLA Network Slice (Integer IDR)',
            'chart1' => ['title' => 'Throughput Data Jaringan (Gbps)', 'val' => '480 Gbps', 'type' => 'sparkline', 'color' => 'blue', 'data' => [320, 360, 400, 420, 450, 470, 480]],
            'chart2' => ['title' => 'Kepatuhan Latensi < 10ms', 'val' => '99.9%', 'type' => 'radial', 'color' => 'emerald', 'data' => [100]],
            'chart3' => ['title' => 'Okupansi Rak Data Center Edge', 'val' => '86.4%', 'type' => 'bar', 'color' => 'indigo', 'data' => ['DC1' => 82, 'DC2' => 88, 'DC3' => 85, 'DC4' => 90]],
        ],
    ];

    $cfg = $domainConfigs[$modName] ?? [
        'mode' => 'double-entry',
        'simTitle' => "Simulasi Presisi Transaksi & Integritas Modul {$modName}",
        'formAction' => "Eksekusi Aksi Operasional {$modName}",
        'amountLabel' => 'Nominal Transaksi (Integer Minor-Unit IDR)',
        'chart1' => ['title' => "Volume Transaksi {$modName}", 'val' => 'Rp 14.500.000.000', 'type' => 'sparkline', 'color' => 'indigo', 'data' => [25, 32, 38, 42, 45, 52, 58]],
        'chart2' => ['title' => 'Tingkat Integritas Hash-Chain', 'val' => '100%', 'type' => 'radial', 'color' => 'emerald', 'data' => [100]],
        'chart3' => ['title' => 'Distribusi Aksi Harian', 'val' => '840 TX', 'type' => 'bar', 'color' => 'blue', 'data' => ['D1' => 120, 'D2' => 150, 'D3' => 180, 'D4' => 210]],
    ];

    $simMode = $simulatorMode ?? $cfg['mode'];
    $formTitle = $actionTitle ?? $cfg['formAction'];
    $amtLabel = $cfg['amountLabel'];
@endphp

<div class="enterprise-module-dashboard space-y-6"
     x-data="{
        txAmount: 15000000,
        txRefCode: 'TX-' + Math.floor(100000 + Math.random() * 900000),
        idempKey: 'idemp_' + Math.random().toString(36).substring(2, 10),
        get txAmountFormatted() {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(this.txAmount || 0);
        },
        generateNewKey() {
            this.idempKey = 'idemp_' + Math.random().toString(36).substring(2, 10) + '_' + Date.now().toString(16);
            this.txRefCode = 'TX-' + Math.floor(100000 + Math.random() * 900000);
        }
     }">

    {{-- Executive Header & Title --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-2 border-b border-slate-800">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="w-3 h-3 rounded-full bg-indigo-500 animate-pulse"></span>
                <h1 class="text-2xl font-black text-white tracking-tight">{{ $computedTitle }}</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                    Modul {{ $modName }}
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ $computedSubtitle }}</p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button"
                    @click="$dispatch('open-enterprise-simulator', { module: '{{ $modName }}', mode: '{{ $simMode }}' })"
                    data-open-simulator="{{ $modName }}"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white text-xs font-bold shadow-md shadow-indigo-600/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Simulator {{ $modName }}</span>
            </button>
        </div>
    </div>

    {{-- 1. Executive KPI Summary Cards + Pure SVG Visualization --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <x-enterprise.svg-kpi-chart
            :type="$cfg['chart1']['type']"
            :title="$cfg['chart1']['title']"
            :value="$cfg['chart1']['val']"
            :color="$cfg['chart1']['color']"
            :data="$cfg['chart1']['data']"
            trend="up"
            change="+14.2%"
            subtitle="Metrik agregat real-time sistem"
        />

        <x-enterprise.svg-kpi-chart
            :type="$cfg['chart2']['type']"
            :title="$cfg['chart2']['title']"
            :value="$cfg['chart2']['val']"
            :color="$cfg['chart2']['color']"
            :data="$cfg['chart2']['data']"
            trend="up"
            change="+99.8%"
            subtitle="Tingkat integritas & uptime operasi"
        />

        <x-enterprise.svg-kpi-chart
            :type="$cfg['chart3']['type']"
            :title="$cfg['chart3']['title']"
            :value="$cfg['chart3']['val']"
            :color="$cfg['chart3']['color']"
            :data="$cfg['chart3']['data']"
            trend="up"
            change="Stabil"
            subtitle="Beban dan distribusi transaksi harian"
        />
    </div>

    {{-- 2. Interactive Action / Transaction Form --}}
    <div class="rounded-2xl bg-slate-900/90 border border-slate-700/70 p-6 backdrop-blur-xl shadow-xl">
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-800">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ $formTitle }}</span>
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Input transaksi terenkripsi dengan proteksi idempotency key dan presisi integer minor-unit.</p>
            </div>
            <span class="text-[10px] font-mono px-2 py-1 rounded bg-slate-800 text-slate-400 border border-slate-700">A3=0 / A4=0 Compliant</span>
        </div>

        {{-- Form Element with @csrf, strict inputs, and error feedback --}}
        <form method="POST" action="{{ $actionRoute ?? url()->current() }}" class="space-y-4">
            @csrf

            {{-- Error Feedback Block --}}
            @if (isset($errors) && $errors->any())
                <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs">
                    <p class="font-bold mb-1">Gagal memproses transaksi:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Nominal Amount in Integer IDR --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">
                        {{ $amtLabel }} <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs text-slate-400 font-mono">Rp</span>
                        <input type="number"
                               name="amount"
                               x-model.number="txAmount"
                               step="1000"
                               min="1000"
                               required
                               placeholder="15000000"
                               class="w-full pl-10 pr-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-white font-mono text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <span class="text-[11px] text-indigo-400 font-mono mt-1 block" x-text="txAmountFormatted"></span>
                    @error('amount')
                        <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Deterministic Reference / Idempotency Key --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">
                        Kode Referensi / Idempotency Key <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input type="text"
                               name="reference_number"
                               x-model="txRefCode"
                               required
                               readonly
                               class="w-full px-3 py-2.5 rounded-xl bg-slate-800/80 border border-slate-700 text-slate-300 font-mono text-xs focus:border-indigo-500">
                        <button type="button"
                                @click="generateNewKey()"
                                class="absolute right-2 top-2 p-1 text-slate-400 hover:text-white"
                                title="Generate Deterministic Key Baru">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        </button>
                    </div>
                    <span class="text-[10px] text-slate-500 font-mono mt-1 block">Key: <span x-text="idempKey"></span></span>
                    <input type="hidden" name="idempotency_key" :value="idempKey">
                    @error('reference_number')
                        <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Account / Target Category --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">
                        Departemen / Akun Kliring <span class="text-red-400">*</span>
                    </label>
                    <select name="category" class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-white text-xs focus:border-indigo-500">
                        <option value="OPERASIONAL_PRIMARY">Operasional Utama (Holding Pool)</option>
                        <option value="CLEARING_ESCROW">Rekening Kliring & Escrow Transaksi</option>
                        <option value="STATUTORY_RESERVE">Cadangan Statuter & Kepatuhan Risiko</option>
                        <option value="REVENUE_SETTLEMENT">Penyelesaian Pendapatan Bersih</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Verifikasi kriptografi SHA-256 disematkan secara otomatis</span>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button"
                            @click="$dispatch('open-enterprise-simulator', { module: '{{ $modName }}', mode: '{{ $simMode }}' })"
                            class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-all">
                        Dry-Run Simulasi
                    </button>
                    <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">
                        Posting Transaksi {{ $modName }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- 3. Interactive Audit & Hash-Chain Verification Table with Export Toolbar --}}
    <div class="rounded-2xl bg-slate-900/90 border border-slate-700/70 overflow-hidden backdrop-blur-xl shadow-xl">
        <div class="p-6 border-b border-slate-800">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                <span>{{ $tableTitle }}</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Seluruh mutasi tercatat dalam rantai hash tak-terbantahkan (tamper-evident immutable ledger).</p>
        </div>

        <div class="p-6">
            {{-- Integrated Export & Search Toolbar --}}
            <x-enterprise.export-toolbar
                :table-id="$tId"
                :module="$modName"
                :simulator-mode="$simMode"
            />

            {{-- Audit Table Element --}}
            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table id="{{ $tId }}" class="w-full text-left text-xs font-mono">
                    <thead class="bg-slate-950/80 text-slate-400 uppercase tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Block / TX Hash</th>
                            <th class="px-4 py-3">Waktu (UTC)</th>
                            <th class="px-4 py-3">Referensi ID</th>
                            <th class="px-4 py-3">Deskripsi / Operasi</th>
                            <th class="px-4 py-3 text-right">Nominal (IDR)</th>
                            <th class="px-4 py-3 text-center">Status Integritas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        <tr data-status="VERIFIED" class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3 font-mono text-indigo-400">0x7f4b8...e91a</td>
                            <td class="px-4 py-3 text-slate-400">{{ now()->subMinutes(12)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 font-bold text-slate-200">{{ strtoupper($modName) }}-TX-90412</td>
                            <td class="px-4 py-3">Eksekusi Settlement & Kliring Otomatis</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-400">Rp 125.000.000</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                                    VERIFIED
                                </span>
                            </td>
                        </tr>
                        <tr data-status="VERIFIED" class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3 font-mono text-indigo-400">0x2a9c1...31fd</td>
                            <td class="px-4 py-3 text-slate-400">{{ now()->subMinutes(45)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 font-bold text-slate-200">{{ strtoupper($modName) }}-TX-90411</td>
                            <td class="px-4 py-3">Penerbitan Kontrak & Alokasi Jurnal Double-Entry</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-400">Rp 48.500.000</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                                    VERIFIED
                                </span>
                            </td>
                        </tr>
                        <tr data-status="VERIFIED" class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3 font-mono text-indigo-400">0x6e881...d50c</td>
                            <td class="px-4 py-3 text-slate-400">{{ now()->subHours(2)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 font-bold text-slate-200">{{ strtoupper($modName) }}-TX-90410</td>
                            <td class="px-4 py-3">Audit Kepatuhan & Validasi Neraca Presisi</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-400">Rp 16.200.000</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                                    VERIFIED
                                </span>
                            </td>
                        </tr>
                        <tr data-status="POSTED" class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3 font-mono text-indigo-400">0x11b74...98fe</td>
                            <td class="px-4 py-3 text-slate-400">{{ now()->subHours(4)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 font-bold text-slate-200">{{ strtoupper($modName) }}-TX-90409</td>
                            <td class="px-4 py-3">Penyelarasan Saldo Kas & Agunan SBU</td>
                            <td class="px-4 py-3 text-right font-bold text-indigo-400">Rp 8.750.000</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                                    POSTED
                                </span>
                            </td>
                        </tr>
                        <tr data-status="PENDING" class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3 font-mono text-indigo-400">0x88921...aa41</td>
                            <td class="px-4 py-3 text-slate-400">{{ now()->subHours(6)->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-3 font-bold text-slate-200">{{ strtoupper($modName) }}-TX-90408</td>
                            <td class="px-4 py-3">Kliring Kliring Lintas Entitas (Intercompany)</td>
                            <td class="px-4 py-3 text-right font-bold text-amber-400">Rp 2.400.000</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                    PENDING
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 4. Domain-Specific Interactive Simulator Panel --}}
    <div class="rounded-2xl bg-gradient-to-r from-slate-900 via-slate-900/90 to-indigo-950/40 border border-slate-700/70 p-6 shadow-xl">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs uppercase font-bold text-indigo-400 tracking-wider">Engine Simulasi Presisi Deterministik</span>
                <h3 class="text-base font-bold text-white mt-1">{{ $cfg['simTitle'] }}</h3>
                <p class="text-xs text-slate-400 mt-1">Uji coba parameter kalkulasi numerik secara real-time untuk audit komite dan kepatuhan regulasi.</p>
            </div>
            <button type="button"
                    @click="$dispatch('open-enterprise-simulator', { module: '{{ $modName }}', mode: '{{ $simMode }}' })"
                    data-open-simulator="{{ $modName }}"
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Buka Panel Simulasi {{ $modName }}</span>
            </button>
        </div>
    </div>
</div>

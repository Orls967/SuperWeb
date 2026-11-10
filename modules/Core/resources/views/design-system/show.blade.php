@extends('layouts.app')

@section('title', 'Spesifikasi: ' . $component->display_name . ' — Design System')

@section('content')
    <div class="space-y-6">
        {{-- Breadcrumb Navigasi --}}
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => route('dashboard')],
            ['label' => 'Design System', 'url' => route('design-system.index')],
            ['label' => $component->display_name, 'url' => '#', 'active' => true],
        ]" />

        {{-- Header Komponen --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $component->display_name }}</h1>
                    <x-ds.badge-status :status="$component->status" />
                    <span class="inline-flex items-center rounded-md bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                        WCAG 2.1 {{ $component->wcag_level }}
                    </span>
                </div>
                <p class="font-mono text-sm text-blue-600 dark:text-blue-400">
                    &lt;x-ds.{{ $component->name }} /&gt;
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-400 max-w-2xl">
                    {{ $component->notes ?? 'Komponen antarmuka kanonikal berstandar tinggi yang mendukung kontrol keyboard dan pembaca layar.' }}
                </p>
            </div>
            <div class="flex flex-col items-end gap-1.5 text-xs text-slate-500">
                <span>Versi Terkini: <strong class="font-mono text-slate-800 dark:text-slate-200">{{ $component->current_version }}</strong></span>
                <span>Kategori: <strong class="text-slate-800 dark:text-slate-200">{{ ucfirst($component->category) }}</strong></span>
                @if($component->spec_doc_url)
                    <a href="{{ $component->spec_doc_url }}" class="text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1 mt-1 font-medium">
                        Dokumentasi Desain &rarr;
                    </a>
                @endif
            </div>
        </div>

        {{-- Live Demo Box --}}
        <x-ds.card title="Pratinjau Langsung (Live Preview)">
            <div class="rounded-lg border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-950/50 p-8 flex items-center justify-center">
                @if($component->name === 'button')
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ds.button variant="primary">Tombol Utama</x-ds.button>
                        <x-ds.button variant="secondary">Sekunder</x-ds.button>
                        <x-ds.button variant="danger">Bahaya</x-ds.button>
                        <x-ds.button variant="ghost">Ghost</x-ds.button>
                        <x-ds.button variant="primary" :disabled="true">Nonaktif</x-ds.button>
                    </div>
                @elseif($component->name === 'badge-status')
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ds.badge-status status="draft" />
                        <x-ds.badge-status status="in_review" />
                        <x-ds.badge-status status="approved" />
                        <x-ds.badge-status status="released" />
                        <x-ds.badge-status status="deprecated" />
                        <x-ds.badge-status status="retired" />
                    </div>
                @elseif($component->name === 'stat')
                    <div class="w-full max-w-sm">
                        <x-ds.stat label="Total Transaksi" value="1.240.000" change="+12.5%" change-type="positive" hint="Bulan Ini" />
                    </div>
                @elseif($component->name === 'contrast-badge')
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ds.contrast-badge fg-hex="0F172A" bg-hex="FFFFFF" />
                        <x-ds.contrast-badge fg-hex="64748B" bg-hex="FFFFFF" />
                        <x-ds.contrast-badge fg-hex="E2E8F0" bg-hex="FFFFFF" />
                    </div>
                @else
                    <div class="text-center space-y-2">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Pratinjau Standar Komponen</p>
                        <code class="text-xs font-mono text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 px-3 py-1.5 rounded-lg border border-blue-200 dark:border-blue-800 inline-block">
                            &lt;x-ds.{{ $component->name }} ...attributes /&gt;
                        </code>
                    </div>
                @endif
            </div>
        </x-ds.card>

        {{-- Sertifikasi Kontras Warna 3 Tema (A1 Algoritma) --}}
        <x-ds.card title="Sertifikasi Rasio Kontras Warna WCAG (Algoritma A1)">
            <p class="text-xs text-slate-600 dark:text-slate-400 mb-4">
                Dihitung menggunakan formula presisi integer basis poin (bps) & ppm tanpa pembulatan float prematur.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-lg border border-slate-200 dark:border-slate-800 p-4 bg-white dark:bg-slate-900 space-y-2">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Tema Terang (Light)</span>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">Teks / Latar:</span>
                        <code class="font-mono">&#35;0f172a / &#35;ffffff</code>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">Rasio Kontras:</span>
                        <span class="font-mono font-semibold">{{ number_format($contrastMetrics['light']['ratio_bps'] / 10000, 2) }}:1 ({{ $contrastMetrics['light']['ratio_bps'] }} bps)</span>
                    </div>
                    <div class="pt-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                            {{ $contrastMetrics['light']['passes_aa_normal'] ? 'WCAG AA' : 'GAGAL' }} LULUS
                        </span>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 dark:border-slate-800 p-4 bg-slate-900 text-white space-y-2">
                    <span class="text-xs font-bold text-slate-200">Tema Gelap (Dark)</span>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400">Teks / Latar:</span>
                        <code class="font-mono">&#35;f8fafc / &#35;0f172a</code>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400">Rasio Kontras:</span>
                        <span class="font-mono font-semibold">{{ number_format($contrastMetrics['dark']['ratio_bps'] / 10000, 2) }}:1 ({{ $contrastMetrics['dark']['ratio_bps'] }} bps)</span>
                    </div>
                    <div class="pt-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-900/80 text-emerald-300">
                            {{ $contrastMetrics['dark']['passes_aa_normal'] ? 'WCAG AA' : 'GAGAL' }} LULUS
                        </span>
                    </div>
                </div>

                <div class="rounded-lg border border-yellow-400 p-4 bg-black text-yellow-300 space-y-2">
                    <span class="text-xs font-bold text-yellow-300">Kontras Tinggi (HC)</span>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-yellow-400">Teks / Latar:</span>
                        <code class="font-mono">&#35;ffff00 / &#35;000000</code>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-yellow-400">Rasio Kontras:</span>
                        <span class="font-mono font-semibold">{{ number_format($contrastMetrics['hc']['ratio_bps'] / 10000, 2) }}:1 ({{ $contrastMetrics['hc']['ratio_bps'] }} bps)</span>
                    </div>
                    <div class="pt-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-yellow-400 text-black">
                            {{ $contrastMetrics['hc']['passes_aa_normal'] ? 'WCAG AA' : 'GAGAL' }} LULUS
                        </span>
                    </div>
                </div>
            </div>
        </x-ds.card>

        {{-- Skema Props, Slot & Events --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-ds.card title="Skema Properti (Props)">
                @if(empty($component->props_schema))
                    <p class="text-xs text-slate-500 italic">Menerima atribut HTML standar via $attributes.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                            <thead class="border-b border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 font-semibold">
                                <tr>
                                    <th class="py-2">Nama</th>
                                    <th class="py-2">Tipe</th>
                                    <th class="py-2">Default</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                @foreach($component->props_schema as $pName => $pDef)
                                    <tr>
                                        <td class="py-2 text-blue-600 dark:text-blue-400">{{ $pName }}</td>
                                        <td class="py-2">{{ is_array($pDef) ? ($pDef['type'] ?? 'mixed') : $pDef }}</td>
                                        <td class="py-2 text-slate-500">{{ is_array($pDef) ? ($pDef['default'] ?? '-') : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ds.card>

            {{-- Riwayat Transisi State Machine --}}
            <x-ds.card title="Riwayat Transisi Status State Machine">
                @if($history->isEmpty())
                    <p class="text-xs text-slate-500 italic">Belum ada riwayat transisi status.</p>
                @else
                    <x-ds.timeline :items="$history->map(fn($h) => [
                        'title' => strtoupper($h->from_status) . ' &rarr; ' . strtoupper($h->to_status),
                        'description' => $h->reason ?? 'Transisi status state machine',
                        'timestamp' => (string)$h->created_at,
                        'status' => $h->to_status,
                    ])->toArray()" />
                @endif
            </x-ds.card>
        </div>
    </div>
@endsection

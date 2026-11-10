@extends('layouts.app')

@section('title', 'Papan Riset UX & Usability Testing — Design System')

@section('content')
    <div class="space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => route('dashboard')],
            ['label' => 'Design System', 'url' => route('design-system.index')],
            ['label' => 'Papan Riset UX', 'url' => '#', 'active' => true],
        ]" />

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Papan Riset UX & Pengujian Kegunaan</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">Metrik usability testing berbasis standar SUS (System Usability Scale) dan task completion rate per siklus iterasi antarmuka.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.design-system.research.export-csv') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-600 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Ekspor CSV Temuan
                </a>
            </div>
        </div>

        {{-- Metrik Ringkasan --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-ds.stat label="Rata-rata Skor SUS" :value="$avgSus . ' / 100'" change="+14.2%" change-type="positive" hint="Target Min: 70.0 (Grade B)" icon="chart-bar" />
            <x-ds.stat label="Task Success Rate" :value="$avgSuccessRate . '%'" change="+8.5%" change-type="positive" hint="Target Min: 85%" icon="check-circle" />
            <x-ds.stat label="Total Temuan Kegunaan" :value="$totalFindings" hint="Dari Seluruh Iterasi" icon="exclamation-circle" />
            <x-ds.stat label="Temuan Terselesaikan" :value="$resolvedFindings" change-type="positive" hint="Telah Diperbaiki di Komponen" icon="shield-check" />
        </div>

        {{-- Grafik Tren SVG Murni --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-ds.card title="Tren Skor Kelayakan Sistem (SUS Score)">
                @if(count($chartLabels) > 1)
                    <x-ds.chart-line
                        :labels="$chartLabels"
                        :values="$susValues"
                        stroke-color="primary"
                        title="Skor SUS per Iterasi Riset"
                    />
                @else
                    <p class="text-xs text-slate-500 italic p-4">Diperlukan minimal dua iterasi riset untuk menampilkan tren garis.</p>
                @endif
            </x-ds.card>

            <x-ds.card title="Tren Tingkat Keberhasilan Tugas (Task Success %)">
                @if(count($chartLabels) > 1)
                    <x-ds.chart-line
                        :labels="$chartLabels"
                        :values="$successRateValues"
                        stroke-color="success"
                        title="Persentase Tugas Terselesaikan tanpa Bantuan"
                    />
                @else
                    <p class="text-xs text-slate-500 italic p-4">Diperlukan minimal dua iterasi riset untuk menampilkan tren garis.</p>
                @endif
            </x-ds.card>
        </div>

        {{-- Tabel Temuan Kegunaan --}}
        <x-ds.card title="Daftar Temuan Kegunaan & Rekomendasi Solusi">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                    <thead class="border-b border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 font-semibold uppercase tracking-wider bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-4 py-3">Keparahan</th>
                            <th class="px-4 py-3">Komponen Terkait</th>
                            <th class="px-4 py-3">Kategori Masalah</th>
                            <th class="px-4 py-3">Deskripsi Temuan</th>
                            <th class="px-4 py-3">Rekomendasi Desain</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($findings as $f)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition">
                                <td class="px-4 py-3">
                                    @php
                                        $sevBadge = match($f->severity) {
                                            'blocker' => 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300 font-bold',
                                            'major' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 font-semibold',
                                            'minor' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
                                            default => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] uppercase {{ $sevBadge }}">
                                        {{ $f->severity }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono font-medium text-slate-900 dark:text-slate-100">
                                    {{ $f->component?->name ? '<x-ds.' . $f->component->name . '>' : 'Global Shell' }}
                                </td>
                                <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $f->category) }}</td>
                                <td class="px-4 py-3 max-w-xs text-slate-700 dark:text-slate-300">{{ $f->description }}</td>
                                <td class="px-4 py-3 max-w-xs italic text-slate-500">{{ $f->recommendation ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @if($f->is_resolved)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            Selesai
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Terbuka
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pt-4">
                {{ $findings->links() }}
            </div>
        </x-ds.card>
    </div>
@endsection

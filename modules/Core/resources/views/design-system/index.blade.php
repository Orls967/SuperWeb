@extends('layouts.app')

@section('title', 'Katalog Design System Kanonikal')

@section('content')
    <div class="space-y-6">
        {{-- Breadcrumb Navigasi --}}
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => route('dashboard')],
            ['label' => 'Design System', 'url' => route('design-system.index')],
            ['label' => 'Katalog Komponen', 'url' => '#', 'active' => true],
        ]" />

        {{-- Header & Statistik Global --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Katalog Komponen Kanonikal</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">Pustaka 25+ komponen standar antarmuka SuperWeb dengan sertifikasi aksesibilitas WCAG 2.1 AA.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.design-system.releases.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    Tata Kelola Rilis
                </a>
                <a href="{{ route('admin.design-system.research.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                    Papan Riset UX
                </a>
            </div>
        </div>

        {{-- Ringkasan Metrik --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-ds.stat label="Total Komponen" :value="$totalCount" hint="Katalog Kanonikal" icon="cube" />
            <x-ds.stat label="Komponen Dirilis" :value="$releasedCount" change="+100%" change-type="positive" hint="Siap Pakai Produksi" icon="check-circle" />
            <x-ds.stat label="Token Tema" :value="$tokensCount" hint="Light, Dark, Kontras Tinggi" icon="adjustments" />
            <x-ds.stat label="Kepatuhan Aksesibilitas" value="WCAG AA" change="100%" change-type="positive" hint="Presisi Integer Bps" icon="shield-check" />
        </div>

        {{-- Filter & Pencarian Bar --}}
        <form method="GET" action="{{ route('design-system.index') }}" class="flex flex-col sm:flex-row gap-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
            <div class="flex-1">
                <x-ds.input
                    type="search"
                    name="search"
                    :value="$search"
                    placeholder="Cari nama komponen atau deskripsi..."
                    aria-label="Cari komponen"
                />
            </div>
            <div class="w-full sm:w-48">
                <x-ds.select
                    name="category"
                    :value="$selectedCategory"
                    :options="array_merge(['' => 'Semua Kategori'], $categories->mapWithKeys(fn($c) => [$c => ucfirst($c)])->toArray())"
                    aria-label="Filter kategori"
                />
            </div>
            <div class="w-full sm:w-48">
                <x-ds.select
                    name="status"
                    :value="$selectedStatus"
                    :options="[
                        '' => 'Semua Status SM',
                        'released' => 'Released (Dirilis)',
                        'approved' => 'Approved (Disetujui)',
                        'in_review' => 'In Review (Ditinjau)',
                        'draft' => 'Draft (Konsep)',
                        'deprecated' => 'Deprecated (Usang)',
                        'retired' => 'Retired (Dipensiunkan)',
                    ]"
                    aria-label="Filter status state machine"
                />
            </div>
            <div class="flex gap-2">
                <x-ds.button type="submit" variant="primary" size="md">Filter</x-ds.button>
                @if($search || $selectedCategory || $selectedStatus)
                    <a href="{{ route('design-system.index') }}" class="inline-flex items-center px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 transition">Reset</a>
                @endif
            </div>
        </form>

        {{-- Grid Kartu Komponen --}}
        @if($components->isEmpty())
            <x-ds.empty
                title="Tidak Ada Komponen yang Cocok"
                description="Coba ubah kata kunci pencarian atau bersihkan filter kategori untuk menemukan komponen lainnya."
                action-label="Kembalikan Filter"
                :action-url="route('design-system.index')"
            />
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($components as $comp)
                    <x-ds.card :title="$comp->display_name" class="flex flex-col justify-between hover:shadow-md transition">
                        <x-slot:headerActions>
                            <x-ds.badge-status :status="$comp->status" />
                        </x-slot:headerActions>

                        <div class="space-y-3">
                            <p class="font-mono text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 px-2 py-0.5 rounded inline-block">
                                &lt;x-ds.{{ $comp->name }} /&gt;
                            </p>
                            <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2">
                                {{ $comp->notes ?? 'Komponen kanonikal standar terintegrasi dengan tokens & aksesibilitas pembaca layar.' }}
                            </p>

                            <div class="flex items-center gap-3 pt-2 text-[11px] text-slate-500 dark:text-slate-400 border-t border-slate-100 dark:border-slate-800">
                                <span>Versi: <strong class="font-mono text-slate-700 dark:text-slate-200">{{ $comp->current_version }}</strong></span>
                                <span>Kategori: <strong class="text-slate-700 dark:text-slate-200">{{ ucfirst($comp->category) }}</strong></span>
                                <span class="ml-auto inline-flex items-center font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ $comp->wcag_level }}
                                </span>
                            </div>
                        </div>

                        <x-slot:footer>
                            <div class="flex items-center justify-between w-full">
                                <a href="{{ route('design-system.show', $comp->name) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 inline-flex items-center gap-1">
                                    Buka Spesifikasi & Demo &rarr;
                                </a>
                            </div>
                        </x-slot:footer>
                    </x-ds.card>
                @endforeach
            </div>

            <div class="pt-4">
                {{ $components->links() }}
            </div>
        @endif
    </div>
@endsection

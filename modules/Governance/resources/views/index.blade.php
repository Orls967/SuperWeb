@extends('layouts.app')

@section('title', 'DAO Corporate Governance')

@section('content')
<div class="space-y-6">
    <x-ds.breadcrumb :items="[
        ['label' => 'Beranda', 'url' => route('dashboard')],
        ['label' => 'Tata Kelola', 'url' => '#', 'active' => true],
    ]" />

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">DAO Corporate Governance</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Portal Pemungutan Suara Berbobot RWA, Kepatuhan Dewan, dan Hak Suara Karyawan.</p>
        </div>
        <div class="flex items-center gap-2">
            <x-ds.badge-status status="released" />
            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                Audit Hash Chain Aktif
            </span>
        </div>
    </div>

    {{-- Ringkasan Metrik Tata Kelola --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ds.stat label="Total Proposal DAO" value="18" hint="Tahun Berjalan" icon="cube" />
        <x-ds.stat label="Hak Suara Aktif" value="84.2%" change="+5.1%" change-type="positive" hint="Quorum Tercapai" icon="check-circle" />
        <x-ds.stat label="Bobot Token RWA" value="45.000.000" hint="Suara Sah Terverifikasi" icon="chart-bar" />
        <x-ds.stat label="Kepatuhan Regulasi" value="100%" change-type="positive" hint="OJK & Dewan Direksi" icon="shield-check" />
    </div>

    <x-ds.card title="Proposal Aktif & Agenda Pemungutan Suara">
        <x-ds.empty
            title="Tidak Ada Voting Tertunda"
            description="Semua proposal tata kelola saat ini telah mencapai quorum dan disahkan oleh komite audit."
        />
    </x-ds.card>
</div>
@endsection

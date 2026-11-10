@extends('layouts.app')

@section('title', 'Lini 22: Perikanan & Marine Aquaculture')

@section('content')
<div class="space-y-6">
    <x-ds.breadcrumb :items="[
        ['label' => 'Beranda', 'url' => route('dashboard')],
        ['label' => 'Marine Aquaculture', 'url' => '#', 'active' => true],
    ]" />

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Lini 22: Perikanan & Marine Aquaculture</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Siklus Tambak, FCR Rasional, Panen & Settlement Pembudidaya Terintegrasi.</p>
        </div>
        <div class="flex items-center gap-2">
            <x-ds.badge-status status="released" />
            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                IoT Sensor Terhubung
            </span>
        </div>
    </div>

    {{-- Metrik KPI Tambak --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ds.stat label="Total Kolam Aktif" value="64 Kolam" hint="Tambak Udang & Ikan" icon="cube" />
        <x-ds.stat label="Rata-rata FCR" value="1.24" change="-0.08" change-type="positive" hint="Rasio Konversi Pakan" icon="chart-bar" />
        <x-ds.stat label="Biomassa Terestimasi" value="128.5 Ton" hint="Siap Panen Bulan Depan" icon="check-circle" />
        <x-ds.stat label="Penyelesaian Pembudidaya" value="Rp 2.450.000.000" change-type="positive" hint="Bulan Ini" icon="shield-check" />
    </div>

    <x-ds.card title="Status Siklus Kolam & Pengawasan Mutu Air">
        <x-ds.empty
            title="Semua Kolam dalam Parameter Optimal"
            description="Tingkat salinitas, dissolved oxygen (DO), dan pH air tambak berada pada ambang batas standar."
        />
    </x-ds.card>
</div>
@endsection

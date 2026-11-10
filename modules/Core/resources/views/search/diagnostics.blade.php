@extends('layouts.app')

@section('title', 'Diagnostik & Sandbox Pencarian Global — Superweb Enterprise')

@section('content')
<div class="px-4 py-8 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
    {{-- Header Halaman --}}
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-700/60 pb-5">
        <div>
            <div class="flex items-center space-x-3">
                <div class="p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-white flex items-center space-x-3">
                        <span>Diagnostik &amp; Sandbox Pencarian Global</span>
                        <x-ds.badge variant="primary" size="sm">Algoritma A4-A9</x-ds.badge>
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Pemeriksaan parameter Okapi BM25, Trigram Jaccard, Policy-First RBAC, dan integritas inverted index tanpa dependensi luar.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-4 sm:mt-0 flex items-center space-x-3">
            <a href="{{ route('core.search.analytics') }}" class="inline-flex items-center px-4 py-2 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300 bg-slate-800 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Kembali ke Analitik
            </a>
        </div>
    </div>

    {{-- Gauge Kesehatan Komposit --}}
    @include('core::search.partials.health-gauge-card')

    {{-- BM25 Interactive Sandbox --}}
    @include('core::search.partials.bm25-sandbox')

    {{-- Manajer Sinonim --}}
    @include('core::search.partials.synonyms-manager')
</div>
@endsection


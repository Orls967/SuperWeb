@extends('layouts.app')

@section('title', 'Pencarian Global Enterprise — Superweb')

@section('content')
<div class="px-4 py-8 sm:px-6 lg:px-8 max-w-6xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="text-center max-w-2xl mx-auto space-y-2">
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-semibold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <span>Pencarian Cerdas Multi-Modul</span>
        </div>
        <h1 class="text-3xl font-extrabold text-white tracking-tight">
            Pencarian Terpadu &amp; Navigasi Global
        </h1>
        <p class="text-sm text-slate-400">
            Temukan kontrak, aset, manifest penerbangan, kapal maritim, invoice, tugas, dan entitas bisnis dalam hitungan milidetik.
        </p>
    </div>

    {{-- Kotak Input Pencarian Interaktif --}}
    <div class="max-w-3xl mx-auto">
        <form method="GET" action="{{ route('core.api.global-search') }}" id="searchForm" class="relative">
            <div class="relative flex items-center">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input
                    type="text"
                    id="searchQueryInput"
                    name="q"
                    placeholder="Ketik kata kunci, nomor kontrak, kode WO, atau operator (contoh: module:Asset mesin)..."
                    class="w-full bg-slate-800/90 border-2 border-slate-700/80 rounded-2xl pl-12 pr-28 py-3.5 text-base text-white placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 shadow-xl transition-all"
                    autofocus
                >
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center space-x-1.5">
                    <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-semibold bg-slate-700/60 text-slate-400 border border-slate-600">
                        Ctrl+K
                    </span>
                    <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition-colors">
                        Cari
                    </button>
                </div>
            </div>
        </form>

        {{-- Cheatsheet Operator Cepat --}}
        <div class="mt-3 flex flex-wrap items-center justify-center gap-2 text-[11px] text-slate-400">
            <span class="font-semibold text-slate-400">Operator Cepat:</span>
            <code class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-blue-300 font-mono">module:Contract</code>
            <code class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-blue-300 font-mono">module:Asset</code>
            <code class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-blue-300 font-mono">"frasa eksak"</code>
            <code class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 text-blue-300 font-mono">-term</code>
        </div>
    </div>

    {{-- Area Hasil Penelusuran Real-Time --}}
    <div id="searchResultsContainer" class="max-w-3xl mx-auto space-y-4">
        {{-- Kontainer Hasil Dinamis via Javascript --}}
        <div id="resultsList" class="space-y-3"></div>

        <div id="searchPlaceholderState" class="py-12 text-center text-slate-500">
            <svg class="w-12 h-12 mx-auto text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 16l2.879-2.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm font-medium text-slate-400">Mulai mengetik untuk mencari dokumen di seluruh sistem.</p>
            <p class="text-xs text-slate-400 mt-1">Ditenagai oleh perankingan Okapi BM25 dan filter anti-IDOR RBAC.</p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('searchQueryInput');
        const form = document.getElementById('searchForm');
        const resultsList = document.getElementById('resultsList');
        const placeholder = document.getElementById('searchPlaceholderState');

        let debounceTimer;

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            if (query.length < 2) {
                resultsList.innerHTML = '';
                placeholder.classList.remove('hidden');
                return;
            }

            debounceTimer = setTimeout(() => {
                fetchSearchResults(query);
            }, 250);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const query = input.value.trim();
            if (query.length >= 2) {
                fetchSearchResults(query);
            }
        });

        function fetchSearchResults(query) {
            fetch(`{{ route('core.api.global-search') }}?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    placeholder.classList.add('hidden');
                    renderResults(data);
                })
                .catch(() => {
                    resultsList.innerHTML = `<div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs">Gagal memuat hasil kueri. Silakan coba kembali.</div>`;
                });
        }

        function renderResults(data) {
            resultsList.innerHTML = '';
            const items = data.data || [];

            if (data.suggestion) {
                const suggDiv = document.createElement('div');
                suggDiv.className = 'p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl text-xs text-amber-300 flex items-center space-x-2';
                suggDiv.innerHTML = `<span>Mungkin maksud Anda:</span> <button type="button" class="font-bold underline hover:text-white" onclick="document.getElementById('searchQueryInput').value='${data.suggestion}'; document.getElementById('searchQueryInput').dispatchEvent(new Event('input'));">${data.suggestion}</button>`;
                resultsList.appendChild(suggDiv);
            }

            if (items.length === 0) {
                resultsList.innerHTML += `<div class="p-8 text-center bg-slate-800/40 border border-slate-700/60 rounded-xl"><p class="text-sm font-semibold text-white">Tidak ada hasil ditemukan</p><p class="text-xs text-slate-400 mt-1">Coba gunakan kata kunci umum atau periksa ejaan Anda.</p></div>`;
                return;
            }

            items.forEach(item => {
                const card = document.createElement('div');
                card.className = 'p-4 bg-slate-800/80 border border-slate-700/60 rounded-xl hover:border-blue-500/60 transition-all flex items-start justify-between gap-4';

                const targetUrl = item.metadata?.url || '#';
                const badgeText = item.metadata?.badge || item.module || 'DOC';

                card.innerHTML = `
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center space-x-2 mb-1">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-700 text-slate-300 uppercase">${badgeText}</span>
                            <span class="text-xs text-slate-400">${item.module || 'General'}</span>
                        </div>
                        <a href="${targetUrl}" class="text-sm font-bold text-white hover:text-blue-400 transition-colors block truncate">
                            ${item.title}
                        </a>
                        <p class="text-xs text-slate-400 mt-1 line-clamp-2">
                            ${item.content || ''}
                        </p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <span class="text-[11px] font-mono text-slate-400 block">Skor: ${(item.score || 1.0).toFixed(2)}</span>
                        <a href="${targetUrl}" class="mt-2 inline-flex items-center px-2.5 py-1 rounded bg-blue-600/20 hover:bg-blue-600 text-blue-300 hover:text-white text-xs font-semibold transition-colors">
                            Buka &rarr;
                        </a>
                    </div>
                `;
                resultsList.appendChild(card);
            });
        }
    });
</script>
@endsection


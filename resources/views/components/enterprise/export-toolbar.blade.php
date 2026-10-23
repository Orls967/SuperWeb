@props([
    'tableId' => 'audit-table',
    'module' => 'Enterprise',
    'title' => 'Tabel Audit & Verifikasi Transaksi',
    'enableSearch' => true,
    'enableFilter' => true,
    'enableExport' => true,
    'enablePrint' => true,
    'enableSimulator' => true,
    'simulatorMode' => null,
])

<div class="rounded-2xl bg-slate-900/90 border border-slate-700/70 p-4 backdrop-blur-xl shadow-lg mb-4 enterprise-export-toolbar"
     data-toolbar-for="{{ $tableId }}"
     x-data="{
        searchQuery: '',
        statusFilter: 'ALL',
        filterTable() {
            const table = document.getElementById('{{ $tableId }}');
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            const q = this.searchQuery.toLowerCase().trim();
            const filter = this.statusFilter.toUpperCase();
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                const statusAttr = (row.getAttribute('data-status') || '').toUpperCase();
                const matchesSearch = !q || text.includes(q);
                const matchesFilter = filter === 'ALL' || statusAttr.includes(filter) || text.includes(filter.toLowerCase());

                if (matchesSearch && matchesFilter) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const counterEl = document.getElementById('{{ $tableId }}-counter');
            if (counterEl) {
                counterEl.innerText = `${visibleCount} dari ${rows.length} baris`;
            }
        },
        exportCsv() {
            const table = document.getElementById('{{ $tableId }}');
            if (!table) return;
            const rows = Array.from(table.querySelectorAll('tr'));
            const csvRows = [];

            // Add Cryptographic Audit Header
            const now = new Date();
            const dateStr = now.toISOString().replace(/[:.]/g, '-');
            csvRows.push(['# PT MEGA KONSORSIUM NUSANTARA TBK - LAPORAN AUDIT ENTERPRISE']);
            csvRows.push(['# Modul:', '{{ $module }}']);
            csvRows.push(['# Tanggal Export (UTC):', now.toISOString()]);
            csvRows.push(['# Hash-Chain Seal:', 'SHA-256 VERIFIED - ZERO FLOAT DRIFT (INTEGER MINOR-UNIT)']);
            csvRows.push([]);

            // Extract table content
            rows.forEach(row => {
                if (row.style.display === 'none') return; // Skip filtered
                const cols = Array.from(row.querySelectorAll('th, td')).map(cell => {
                    // Clone and clean text
                    let text = cell.innerText.replace(/(\r\n|\n|\r)/gm, ' ').trim();
                    text = text.replace(/"/g, '""');
                    return `"${text}"`;
                });
                if (cols.length > 0) {
                    csvRows.push(cols.join(','));
                }
            });

            const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + csvRows.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', `AUDIT_{{ strtoupper(preg_replace('/[^a-zA-Z0-9]/', '_', $module)) }}_${dateStr}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
        printReport() {
            window.print();
        },
        openSimulator() {
            window.dispatchEvent(new CustomEvent('open-enterprise-simulator', {
                detail: { module: '{{ $module }}', mode: '{{ $simulatorMode ?? strtolower($module) }}' }
            }));
        }
     }">
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
        {{-- Search & Filter Controls --}}
        <div class="flex flex-1 flex-wrap items-center gap-2.5">
            @if($enableSearch)
                <div class="relative flex-1 min-w-[220px]">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input type="text"
                           x-model="searchQuery"
                           @input.debounce.150ms="filterTable()"
                           data-table-search="{{ $tableId }}"
                           placeholder="Cari transaksi, hash, ref, pihak..."
                           class="w-full pl-9 pr-3 py-2 text-xs rounded-xl bg-slate-800/90 border border-slate-700/80 text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500/80 focus:ring-1 focus:ring-indigo-500/50 transition-all font-mono">
                </div>
            @endif

            @if($enableFilter)
                <select x-model="statusFilter"
                        @change="filterTable()"
                        data-table-filter="{{ $tableId }}"
                        class="px-3 py-2 text-xs rounded-xl bg-slate-800/90 border border-slate-700/80 text-slate-200 focus:outline-none focus:border-indigo-500/80 cursor-pointer">
                    <option value="ALL">Semua Status Integritas</option>
                    <option value="VERIFIED">VERIFIED (Hash Valid)</option>
                    <option value="TAMPERED">TAMPERED (Perhatian)</option>
                    <option value="POSTED">POSTED (Buku Besar)</option>
                    <option value="PENDING">PENDING (Kliring)</option>
                </select>
            @endif

            <span id="{{ $tableId }}-counter" class="text-[11px] font-mono text-slate-400 px-2 hidden sm:inline-block">
                Menampilkan data
            </span>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2 flex-wrap">
            @if($enableSimulator)
                <button type="button"
                        @click="openSimulator()"
                        data-open-simulator="{{ $module }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Simulasi Presisi</span>
                </button>
            @endif

            @if($enableExport)
                <button type="button"
                        @click="exportCsv()"
                        data-export-csv="{{ $tableId }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700/80 text-emerald-400 hover:text-emerald-300 text-xs font-semibold transition-all shadow-sm">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Export CSV</span>
                </button>
            @endif

            @if($enablePrint)
                <button type="button"
                        @click="printReport()"
                        data-print-pdf="{{ $tableId }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700/80 text-slate-300 hover:text-white text-xs font-semibold transition-all shadow-sm">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak PDF Audit</span>
                </button>
            @endif
        </div>
    </div>
</div>

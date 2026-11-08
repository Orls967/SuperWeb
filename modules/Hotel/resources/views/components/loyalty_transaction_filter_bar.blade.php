@props([
    'activeLine' => 'ALL',
    'activeType' => 'ALL',
])

<div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4 text-xs"
     x-data="{
        selectedLine: '{{ $activeLine }}',
        selectedType: '{{ $activeType }}',
        dateRange: 'LAST_90_DAYS'
     }">
    <div class="flex items-center space-x-2 font-bold text-slate-700 dark:text-slate-300">
        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
        <span>Penyaring Mutasi Poin:</span>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div>
            <select x-model="selectedType" class="px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-semibold">
                <option value="ALL">Semua Jenis Transaksi</option>
                <option value="EARN">Perolehan (Earn)</option>
                <option value="REDEEM">Penebusan (Redeem)</option>
                <option value="EXPIRE">Breakage (Expired)</option>
                <option value="TRANSFER">Transfer Aliansi (Mitra)</option>
            </select>
        </div>

        <div>
            <select x-model="selectedLine" class="px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-semibold">
                <option value="ALL">Semua 17 Lini Ekosistem</option>
                <option value="HTL_ROOM">Kamar Hotel & Resor</option>
                <option value="HTL_FNB">F&B & Room Service</option>
                <option value="VEN_TICKET">Tiket Festival Venue</option>
                <option value="SPA_TREAT">Spa & Wellness</option>
                <option value="MOB_TRANS">Transportasi Aliansi</option>
            </select>
        </div>

        <div>
            <select x-model="dateRange" class="px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-semibold">
                <option value="LAST_30_DAYS">30 Hari Terakhir</option>
                <option value="LAST_90_DAYS">90 Hari Terakhir</option>
                <option value="LAST_365_DAYS">1 Tahun Penuh (Rolling)</option>
            </select>
        </div>

        <button type="button" class="px-3.5 py-1.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition shadow-sm">
            Terapkan
        </button>
    </div>
</div>


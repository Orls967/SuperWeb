@props([
    'csvUrl' => null,
    'pdfUrl' => null,
    'excelUrl' => null,
    'totalRecords' => null,
])

<div class="flex flex-col sm:flex-row items-center justify-between gap-3 py-2">
    <div>
        @if($totalRecords !== null)
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Menampilkan <span class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">{{ number_format($totalRecords, 0, ',', '.') }}</span> data
            </p>
        @endif
    </div>

    <div class="flex items-center gap-2">
        @if($csvUrl)
            <x-ds.button variant="secondary" size="sm" :href="$csvUrl">
                <svg class="h-4 w-4 mr-1 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                CSV
            </x-ds.button>
        @endif

        @if($excelUrl)
            <x-ds.button variant="secondary" size="sm" :href="$excelUrl">
                <svg class="h-4 w-4 mr-1 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Excel
            </x-ds.button>
        @endif

        @if($pdfUrl)
            <x-ds.button variant="secondary" size="sm" :href="$pdfUrl">
                <svg class="h-4 w-4 mr-1 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                PDF
            </x-ds.button>
        @endif
    </div>
</div>

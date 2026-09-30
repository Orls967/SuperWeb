<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('logistics.shipments.index') }}" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h2 class="font-bold text-2xl text-white tracking-tight">Upload Kargo Massal (Bulk CSV)</h2>
                <p class="text-sm text-slate-400">Proses pemesanan hingga 5.000 pengiriman sekaligus dengan antrean background terisolasi</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-900/40 border border-emerald-500/40 text-emerald-300 text-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                    @if(session('batch_id') && file_exists(storage_path('app/logistics/bulk_reports/' . session('batch_id') . '_errors.csv')))
                        <a href="{{ route('logistics.shipments.errors', session('batch_id')) }}" class="px-3 py-1.5 rounded-lg bg-rose-800 hover:bg-rose-700 text-white text-xs font-semibold transition whitespace-nowrap">
                            Unduh Laporan Error
                        </a>
                    @endif
                </div>
            @endif

            @if($errors->any())
                <div class="p-4 rounded-xl bg-rose-900/40 border border-rose-500/40 text-rose-300 text-sm">
                    <div class="font-semibold mb-1">Upload gagal:</div>
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Step Guide Card -->
            <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-md space-y-4">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-sky-500/20 text-sky-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    Panduan Format CSV
                </h3>
                <ol class="list-decimal pl-5 space-y-2 text-sm text-slate-300">
                    <li>Unduh template CSV resmi di bawah ini untuk memastikan urutan kolom benar.</li>
                    <li>Pastikan kolom <code class="px-1.5 py-0.5 rounded bg-slate-800 font-mono text-sky-400 text-xs">origin_code</code> dan <code class="px-1.5 py-0.5 rounded bg-slate-800 font-mono text-sky-400 text-xs">destination_code</code> menggunakan kode hub jaringan yang valid (mis. <span class="font-mono">HUB-BDJ</span>, <span class="font-mono">HUB-BJB</span>).</li>
                    <li>Maksimal baris per file adalah <strong class="text-white">5.000 baris</strong> data pengiriman.</li>
                    <li>Jika Anda memiliki akun B2B Pascabayar aktif, tagihan akan otomatis dibukukan ke akun kredit Anda.</li>
                </ol>

                <div class="pt-2">
                    <a href="{{ route('logistics.shipments.template') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-semibold transition border border-slate-700">
                        <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Unduh Template CSV Contoh
                    </a>
                </div>
            </div>

            <!-- Upload Box -->
            <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl backdrop-blur-md">
                <form method="POST" action="{{ route('logistics.shipments.bulk.process') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Pilih File CSV Kargo</label>
                        <input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required class="block w-full text-sm text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-sky-600 file:text-white hover:file:bg-sky-500 cursor-pointer bg-slate-950/60 rounded-xl border border-slate-700/80 p-2">
                        <p class="text-xs text-slate-500 mt-2">Maksimal ukuran file: 10 MB (.csv)</p>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white text-sm font-semibold shadow-lg shadow-sky-600/30 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            Mulai Proses Upload Massal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

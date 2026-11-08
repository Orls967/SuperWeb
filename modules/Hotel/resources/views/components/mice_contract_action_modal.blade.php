@props([
    'modalId' => 'modal-contract-action',
    'title' => 'Konfirmasi Aksi Kontrak MICE'
])

<div id="{{ $modalId }}" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm hidden p-4">
    <div class="bg-slate-900 border border-slate-750 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="text-lg font-bold text-white">{{ $title }}</h3>
            <button type="button" onclick="document.getElementById('{{ $modalId }}').classList.add('hidden')" class="text-slate-400 hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="space-y-3 text-sm text-slate-300">
            {{ $slot }}
        </div>

        <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-3">
            <button type="button" onclick="document.getElementById('{{ $modalId }}').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm font-medium transition">
                Batal
            </button>
            <button type="button" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition shadow-lg shadow-indigo-600/30">
                Simpan & Konfirmasi
            </button>
        </div>
    </div>
</div>


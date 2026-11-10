<x-ds.card class="bg-slate-800/60 border-slate-700/60 p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-700 pb-3">
        <h2 class="text-base font-semibold text-white flex items-center space-x-2">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <span>Thread Diskusi &amp; Catatan Internal Tim</span>
        </h2>
        <span class="text-xs text-slate-400 font-normal">Terekam di Audit Trail</span>
    </div>

    {{-- Daftar Komentar yang Ada --}}
    <div class="space-y-4 max-h-96 overflow-y-auto pr-1">
        @forelse($task->comments ?? [] as $comment)
        <div class="bg-slate-900/70 border border-slate-800 rounded-xl p-4 space-y-2">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center space-x-2">
                    <span class="w-6 h-6 rounded-full bg-blue-500/20 text-blue-400 font-bold flex items-center justify-center text-[10px]">
                        {{ substr($comment->author_name, 0, 1) }}
                    </span>
                    <span class="font-bold text-white">{{ $comment->author_name }}</span>
                </div>
                <span class="text-slate-500">{{ $comment->created_at->diffForHumans() }}</span>
            </div>
            <p class="text-xs text-slate-300 leading-relaxed pl-8">
                {{ $comment->comment }}
            </p>
        </div>
        @empty
        <div class="py-6 text-center text-xs text-slate-500">
            Belum ada catatan diskusi internal pada tugas ini. Gunakan formulir di bawah untuk menambahkan instruksi atau klarifikasi.
        </div>
        @endforelse
    </div>

    {{-- Form Tambah Catatan Diskusi --}}
    <div class="pt-4 border-t border-slate-700/60">
        <label for="new_comment_text" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
            Tambah Catatan Kolaborasi Baru
        </label>
        <div class="flex gap-2">
            <input
                type="text"
                id="new_comment_text"
                placeholder="Tuliskan catatan teknis atau klarifikasi dokumen..."
                class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <button type="button" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-semibold transition-colors">
                Kirim Catatan
            </button>
        </div>
    </div>
</x-ds.card>


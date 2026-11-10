<div id="delegationModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-delegation-title">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" onclick="closeDelegationModal()"></div>

    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-slate-900 border border-slate-700 p-6 text-left shadow-2xl transition-all">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div class="flex items-center space-x-3">
                    <div class="p-2 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="modal-delegation-title" class="text-base font-bold text-white">Delegasikan Wewenang Tugas</h3>
                        <p class="text-xs text-slate-400">Alihkan tanggung jawab tindakan kerja kepada anggota tim terotorisasi</p>
                    </div>
                </div>
                <button type="button" onclick="closeDelegationModal()" class="text-slate-400 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Form Delegasi --}}
            <form id="delegationForm" method="POST" action="" class="space-y-4 pt-4">
                @csrf
                <input type="hidden" name="action" value="delegate">

                <div>
                    <label for="modal_delegatee_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Pilih Target Anggota Tim (Assignee)
                    </label>
                    <select id="modal_delegatee_id" name="delegatee_id" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Pilih Staf Terotorisasi...</option>
                        @foreach($delegatableUsers ?? [] as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }}) — {{ $u->email }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="delegation_reason" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Alasan / Justifikasi Pendelegasian
                    </label>
                    <textarea id="delegation_reason" name="notes" rows="3" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Contoh: Dilimpahkan karena cuti operasional atau pelimpahan wewenang teknis spesialis..." required></textarea>
                </div>

                <div class="rounded-xl bg-slate-950/60 border border-slate-800 p-3 text-[11px] text-slate-400 flex items-start space-x-2">
                    <svg class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Pendelegasian akan tercatat permanen pada Audit Trail ledger dengan identitas akun Anda sebagai pelimpah.</span>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-2 border-t border-slate-800">
                    <button type="button" onclick="closeDelegationModal()" class="px-4 py-2 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300 hover:bg-slate-800 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-xs font-semibold transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                        Konfirmasi Delegasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openDelegationModal(taskId) {
        const modal = document.getElementById('delegationModal');
        const form = document.getElementById('delegationForm');
        if (modal && form) {
            form.action = `/inbox/${taskId}/action`;
            modal.classList.remove('hidden');
        }
    }

    function closeDelegationModal() {
        const modal = document.getElementById('delegationModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
</script>


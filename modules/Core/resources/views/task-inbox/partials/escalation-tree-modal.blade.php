{{-- modules/Core/resources/views/task-inbox/partials/escalation-tree-modal.blade.php --}}
@props([
    'task' => null,
])

<div x-data="{ openEscalationTree: false }"
     class="inline-block"
     role="region"
     aria-label="Modal Struktur Alur Eskalasi">

    {{-- Trigger Button --}}
    <button type="button"
            @click="openEscalationTree = true"
            class="inline-flex items-center px-3 py-1.5 border border-purple-200 text-xs font-semibold rounded-lg text-purple-700 bg-purple-50 hover:bg-purple-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors shadow-sm">
        <svg class="w-4 h-4 mr-1.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
        Pohon Eskalasi Wewenang
    </button>

    {{-- Modal Backing --}}
    <div x-show="openEscalationTree"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;"
         role="dialog"
         aria-modal="true"
         aria-labelledby="escalation-tree-modal-title">
        
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="openEscalationTree"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-gray-900 bg-opacity-70 backdrop-blur-sm transition-opacity"
                 @click="openEscalationTree = false"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="openEscalationTree"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-200">

                <div class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-purple-100 text-purple-700 rounded-xl">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <h3 id="escalation-tree-modal-title" class="text-lg font-bold text-gray-900 leading-6">
                                    Matriks Rantai Eskalasi Multi-Tingkat
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Kebijakan penanganan batas waktu dan delegasi bertingkat untuk modul {{ $task->module_code ?? 'Core' }}.
                                </p>
                            </div>
                        </div>

                        <button type="button"
                                @click="openEscalationTree = false"
                                class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Escalation Tiers Steps --}}
                    <div class="mt-6 space-y-6">
                        {{-- Tier 1: Staf / Pelaksana Awal --}}
                        <div class="flex items-start">
                            <div class="flex flex-col items-center mr-4">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-emerald-500 bg-emerald-50 text-emerald-700 font-bold text-xs shadow-sm">
                                    T1
                                </div>
                                <div class="w-0.5 h-12 bg-gray-200 mt-2"></div>
                            </div>
                            <div class="flex-1 bg-gray-50 rounded-xl p-4 border border-gray-200/80">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-bold text-gray-900">Tingkat 1: Petugas Pelaksana / Operator</h4>
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">SLA: 0 - 24 Jam</span>
                                </div>
                                <p class="text-xs text-gray-600 mt-1">
                                    Penerima awal tugas memegang hak akses untuk menelaah dokumen pendukung, meminta klarifikasi, atau menyelesaikan eksekusi langsung.
                                </p>
                            </div>
                        </div>

                        {{-- Tier 2: Penyelia / Supervisor Tim --}}
                        <div class="flex items-start">
                            <div class="flex flex-col items-center mr-4">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-blue-500 bg-blue-50 text-blue-700 font-bold text-xs shadow-sm">
                                    T2
                                </div>
                                <div class="w-0.5 h-12 bg-gray-200 mt-2"></div>
                            </div>
                            <div class="flex-1 bg-gray-50 rounded-xl p-4 border border-gray-200/80">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-bold text-gray-900">Tingkat 2: Penyelia Unit (Supervisor)</h4>
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-800">SLA: 24 - 48 Jam</span>
                                </div>
                                <p class="text-xs text-gray-600 mt-1">
                                    Eskalasi otomatis dipicu bila status tugas masih dalam penantian melebihi 24 jam. Penyelia berwenang mengubah rute tugas atau mengambil alih.
                                </p>
                            </div>
                        </div>

                        {{-- Tier 3: Kepala Divisi / Manajer --}}
                        <div class="flex items-start">
                            <div class="flex flex-col items-center mr-4">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-amber-500 bg-amber-50 text-amber-700 font-bold text-xs shadow-sm">
                                    T3
                                </div>
                                <div class="w-0.5 h-12 bg-gray-200 mt-2"></div>
                            </div>
                            <div class="flex-1 bg-gray-50 rounded-xl p-4 border border-gray-200/80">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-bold text-gray-900">Tingkat 3: Manajer Departemen / Kepala Divisi</h4>
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-amber-100 text-amber-800">SLA: 48 - 72 Jam</span>
                                </div>
                                <p class="text-xs text-gray-600 mt-1">
                                    Pemberitahuan darurat dikirimkan via digest instan. Manajer dapat memveto approval atau menunjuk penanggung jawab pengganti berlisensi.
                                </p>
                            </div>
                        </div>

                        {{-- Tier 4: Direksi / Dewan Eksekutif --}}
                        <div class="flex items-start">
                            <div class="flex flex-col items-center mr-4">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-rose-500 bg-rose-50 text-rose-700 font-bold text-xs shadow-sm">
                                    T4
                                </div>
                            </div>
                            <div class="flex-1 bg-rose-50/50 rounded-xl p-4 border border-rose-200">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-bold text-rose-900">Tingkat 4: Direksi Eksekutif & Komite Audit</h4>
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-rose-100 text-rose-800">> 72 Jam (Batas Kritis)</span>
                                </div>
                                <p class="text-xs text-rose-700 mt-1">
                                    Tugas masuk ke log deviasi kepatuhan sistemik (Audit Non-Compliance Ledger). Memerlukan tindakan intervensi khusus dewan.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Policy Note --}}
                    <div class="mt-6 p-4 rounded-xl bg-purple-50 border border-purple-100 text-xs text-purple-900 flex items-start space-x-3">
                        <svg class="w-5 h-5 text-purple-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <span class="font-bold">Ketentuan Otomasi:</span> Daemon eskalasi terjadwal (<code class="bg-purple-100 px-1 py-0.5 rounded">inbox:escalate</code>) memindai antrean setiap jam. Setiap lompatan tingkat eskalasi dicatat secara permanen di buku besar audit tugas.
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-2xl border-t border-gray-100">
                    <button type="button"
                            @click="openEscalationTree = false"
                            class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 shadow-sm transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


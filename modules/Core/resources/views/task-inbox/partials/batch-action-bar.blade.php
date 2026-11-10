{{-- modules/Core/resources/views/task-inbox/partials/batch-action-bar.blade.php --}}
@props([
    'selectedCount' => 0,
])

<div x-data="{
        selectedTasks: [],
        showConfirmModal: false,
        actionType: null,
        rejectionReason: '',
        delegationUserId: '',
        isProcessing: false,
        selectAll: false,
        toggleAll(taskIds) {
            if (this.selectAll) {
                this.selectedTasks = [...taskIds];
            } else {
                this.selectedTasks = [];
            }
        },
        openConfirm(action) {
            this.actionType = action;
            this.showConfirmModal = true;
        },
        closeConfirm() {
            this.showConfirmModal = false;
            this.actionType = null;
            this.rejectionReason = '';
            this.delegationUserId = '';
        },
        submitBatchAction() {
            if (this.selectedTasks.length === 0) return;
            this.isProcessing = true;
            $refs.batchForm.submit();
        }
    }"
    x-show="selectedTasks.length > 0"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform translate-y-4"
    x-transition:enter-end="opacity-100 transform translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 transform translate-y-0"
    x-transition:leave-end="opacity-0 transform translate-y-4"
    class="fixed bottom-6 inset-x-0 z-40 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8"
    role="region"
    aria-label="Bilah Aksi Massal Tugas">

    <div class="bg-gray-900 text-white rounded-2xl shadow-2xl border border-gray-700/80 p-4 sm:p-5 backdrop-blur-md bg-opacity-95 flex flex-col sm:flex-row items-center justify-between gap-4">
        {{-- Selected Count Badge --}}
        <div class="flex items-center space-x-3">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white font-bold text-sm shadow-inner"
                  x-text="selectedTasks.length">
                0
            </span>
            <div>
                <p class="text-sm font-semibold tracking-wide">
                    <span x-text="selectedTasks.length">0</span> tugas terpilih
                </p>
                <p class="text-xs text-gray-400">Pilih tindakan massal yang ingin Anda terapkan secara serentak.</p>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- Bulk Approve Button --}}
            <button type="button"
                    @click="openConfirm('approve')"
                    :disabled="isProcessing"
                    class="inline-flex items-center px-3.5 py-2 border border-emerald-600 text-xs font-medium rounded-lg text-emerald-100 bg-emerald-900/60 hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Setujui Massal
            </button>

            {{-- Bulk Reject Button --}}
            <button type="button"
                    @click="openConfirm('reject')"
                    :disabled="isProcessing"
                    class="inline-flex items-center px-3.5 py-2 border border-rose-600 text-xs font-medium rounded-lg text-rose-100 bg-rose-900/60 hover:bg-rose-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Tolak Massal
            </button>

            {{-- Bulk Delegate Button --}}
            <button type="button"
                    @click="openConfirm('delegate')"
                    :disabled="isProcessing"
                    class="inline-flex items-center px-3.5 py-2 border border-amber-600 text-xs font-medium rounded-lg text-amber-100 bg-amber-900/60 hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                Delegasi Massal
            </button>

            {{-- Bulk Export Button --}}
            <button type="button"
                    @click="openConfirm('export')"
                    :disabled="isProcessing"
                    class="inline-flex items-center px-3 py-2 border border-gray-600 text-xs font-medium rounded-lg text-gray-200 bg-gray-800 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Ekspor
            </button>

            {{-- Cancel / Deselect All --}}
            <button type="button"
                    @click="selectedTasks = []; selectAll = false"
                    class="p-2 text-gray-400 hover:text-white rounded-lg hover:bg-gray-800 focus:outline-none transition-colors"
                    title="Batal Memilih">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Confirmation Modal --}}
    <div x-show="showConfirmModal"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;"
         role="dialog"
         aria-modal="true"
         aria-labelledby="batch-confirm-title">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showConfirmModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity"
                 @click="closeConfirm()"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showConfirmModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-200">
                
                <form x-ref="batchForm" method="POST" action="{{ route('core.task-inbox.batch') }}">
                    @csrf
                    <template x-for="id in selectedTasks" :key="id">
                        <input type="hidden" name="task_ids[]" :value="id">
                    </template>
                    <input type="hidden" name="action" :value="actionType">

                    <div class="p-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-xl"
                                 :class="{
                                     'bg-emerald-100 text-emerald-600': actionType === 'approve',
                                     'bg-rose-100 text-rose-600': actionType === 'reject',
                                     'bg-amber-100 text-amber-600': actionType === 'delegate',
                                     'bg-blue-100 text-blue-600': actionType === 'export'
                                 }">
                                <template x-if="actionType === 'approve'">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </template>
                                <template x-if="actionType === 'reject'">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </template>
                                <template x-if="actionType === 'delegate'">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                </template>
                                <template x-if="actionType === 'export'">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </template>
                            </div>

                            <div class="ml-4 text-left">
                                <h3 id="batch-confirm-title" class="text-lg font-bold text-gray-900 leading-6">
                                    Konfirmasi Tindakan Massal
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Anda akan menerapkan aksi 
                                    <span class="font-bold uppercase text-gray-800" x-text="actionType"></span> 
                                    pada <span class="font-bold text-gray-900" x-text="selectedTasks.length"></span> tugas yang dipilih.
                                </p>
                            </div>
                        </div>

                        {{-- Reason input if Rejection --}}
                        <div x-show="actionType === 'reject'" class="mt-4">
                            <label for="batch_rejection_reason" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                Alasan Penolakan <span class="text-rose-500">*</span>
                            </label>
                            <textarea id="batch_rejection_reason"
                                      name="rejection_reason"
                                      x-model="rejectionReason"
                                      rows="3"
                                      placeholder="Jelaskan alasan penolakan massal ini secara objektif..."
                                      class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-rose-500 focus:ring-rose-500"></textarea>
                        </div>

                        {{-- Delegation user select if Delegate --}}
                        <div x-show="actionType === 'delegate'" class="mt-4">
                            <label for="batch_assignee_id" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                                Pilih Penerima Delegasi Baru <span class="text-amber-500">*</span>
                            </label>
                            <input type="text"
                                   id="batch_assignee_id"
                                   name="new_assignee_id"
                                   x-model="delegationUserId"
                                   placeholder="Masukkan User ID atau surel penanggung jawab baru"
                                   class="w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                        </div>
                    </div>

                    <div class="bg-gray-50 px-6 py-4 flex flex-row-reverse gap-3 rounded-b-2xl border-t border-gray-100">
                        <button type="submit"
                                :disabled="isProcessing || (actionType === 'reject' && !rejectionReason) || (actionType === 'delegate' && !delegationUserId)"
                                class="inline-flex justify-center items-center px-4 py-2 text-sm font-semibold text-white rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 transition-colors"
                                :class="{
                                    'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500': actionType === 'approve',
                                    'bg-rose-600 hover:bg-rose-700 focus:ring-rose-500': actionType === 'reject',
                                    'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500': actionType === 'delegate',
                                    'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500': actionType === 'export'
                                }">
                            <span x-show="!isProcessing">Eksekusi Sekarang</span>
                            <span x-show="isProcessing" class="inline-flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Memproses...
                            </span>
                        </button>

                        <button type="button"
                                @click="closeConfirm()"
                                :disabled="isProcessing"
                                class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-colors">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


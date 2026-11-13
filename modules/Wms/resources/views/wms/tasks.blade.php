<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">WMS Handheld Tasks (375px Ready)</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-3 sm:px-6 space-y-4">
            <x-ds.card title="Daftar Tugas Operator Gudang">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @forelse($tasks as $task)
                        <div class="p-3 rounded-lg border border-gray-200 bg-white space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-sm text-gray-900">#{{ $task->id }} · {{ strtoupper((string) $task->kind) }}</span>
                                <x-ds.badge :variant="$task->status === 'closed' ? 'success' : 'warning'">
                                    {{ $task->status }}
                                </x-ds.badge>
                            </div>
                            <p class="text-xs text-gray-600">Produk #{{ $task->product_id }} · Qty: {{ $task->qty }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada tugas gudang aktif.</p>
                    @endforelse
                </div>
            </x-ds.card>
        </div>
    </div>
</x-app-layout>

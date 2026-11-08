<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                Pusat Kendali Disrupsi Operasional & Hak Penumpang (IRROPS)
            </h2>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                Kepatuhan PM 89/2015 Terotomasi
            </span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm dark:bg-emerald-950 dark:border-emerald-800 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Kartu Statistik Insiden -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Kasus Aktif</div>
                    <div class="mt-2 text-2xl font-extrabold text-red-600 dark:text-red-400">{{ $vm->activeCasesCount() }}</div>
                    <p class="mt-1 text-xs text-gray-400">Dalam proses penanganan</p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Penumpang Terdampak</div>
                    <div class="mt-2 text-2xl font-extrabold text-amber-600 dark:text-amber-400">{{ $vm->impactedPassengersCount() }}</div>
                    <p class="mt-1 text-xs text-gray-400">Total terdata pada manifes</p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Voucher Terbit</div>
                    <div class="mt-2 text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ $vm->vouchersIssuedCount() }}</div>
                    <p class="mt-1 text-xs text-gray-400">Konsumsi & kompensasi PM 89</p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Kamar Hotel Dipesan</div>
                    <div class="mt-2 text-2xl font-extrabold text-blue-600 dark:text-blue-400">{{ $vm->hotelRoomsBookedCount() }}</div>
                    <p class="mt-1 text-xs text-gray-400">Fasilitas menginap darurat</p>
                </div>
            </div>

            <!-- Daftar Kasus Disrupsi -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 sm:p-6 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Daftar Kasus Keterlambatan & Pembatalan</h3>
                        <p class="text-xs text-gray-500">Pelacakan status penanganan kepedulian penumpang real-time</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-750">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Nomor Kasus</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Penerbangan</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Kategori & Delay</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Status Siklus</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium text-gray-500">Tindakan Cepat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($vm->cases as $case)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $case->case_number }}
                                        <span class="block text-xs font-normal text-gray-400">{{ $case->detected_at->diffForHumans() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-800 dark:text-gray-200 font-medium">
                                        {{ $case->flight->flight_number ?? 'N/A' }}
                                        <span class="block text-xs text-gray-400">{{ $case->flight->airline->name ?? '-' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        <span class="font-medium text-amber-600 dark:text-amber-400">{{ $case->regulatory_category }}</span> ({{ $case->delay_minutes }} mnt)
                                        <span class="block text-xs text-gray-400 truncate max-w-xs">{{ $case->incident_description }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                            {{ str_replace('_', ' ', $case->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <form method="POST" action="{{ route('aviation.irrops.dispatch-care') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="disruption_case_id" value="{{ $case->id }}">
                                            <button type="submit" class="inline-flex items-center px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-medium transition">
                                                Kirim Voucher
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('aviation.irrops.reaccommodate') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="disruption_case_id" value="{{ $case->id }}">
                                            <button type="submit" class="inline-flex items-center px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-medium transition">
                                                Re-akomodasi & Hotel
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-400">Tidak ada kasus disrupsi aktif saat ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-750">
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-xs text-gray-500 text-right">
                                    Total Insiden Terdata: {{ $vm->cases->count() }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if ($vm->cases->hasPages())
                    <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                        {{ $vm->cases->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

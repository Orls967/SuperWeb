<x-app-layout>
    <x-slot name="header">
        <div class="yield-top-bar flex items-baseline justify-between border-b pb-2">
            <h1 class="text-xl font-extrabold text-gray-900 dark:text-gray-50">
                Optimasi Pendapatan & Ember Tarif Aviasi
            </h1>
            <em class="text-xs text-emerald-700 bg-emerald-50 px-2 py-1 rounded not-italic font-mono">
                EMSR-b Littlewood Mode
            </em>
        </div>
    </x-slot>

    <div class="py-5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 space-y-5">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 p-3 border border-green-200 text-green-800 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Ringkasan Metrik Hasil -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Faktor Muat Rata-Rata</div>
                    <div class="mt-2 text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">{{ number_format($vm->averageLoadFactor(), 1) }}%</div>
                    <p class="mt-1 text-xs text-gray-400">Target minimal maskapai: 75.0%</p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ember Tarif Terbuka</div>
                    <div class="mt-2 text-2xl font-extrabold text-blue-600 dark:text-blue-400">{{ $vm->activeBucketsCount() }}</div>
                    <p class="mt-1 text-xs text-gray-400">Kelas tarif bersarang aktif</p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Estimasi Omzet Siklus</div>
                    <div class="mt-2 text-2xl font-extrabold text-purple-600 dark:text-purple-400">Rp{{ number_format($vm->totalRevenueMinorUnit() / 100, 0, ',', '.') }}</div>
                    <p class="mt-1 text-xs text-gray-400">Penerimaan kotor belum dikliring</p>
                </div>
            </div>

            <!-- Tabel Penerbangan & Optimasi Hasil -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 sm:p-6 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Jadwal Penerbangan & Alokasi Ember Tarif</h3>
                        <p class="text-xs text-gray-500">Evaluasi proteksi kursi dan batas pemesanan bertingkat</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-750">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Penerbangan</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Rute & Waktu</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Kapasitas</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Jumlah Bucket</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($vm->flights as $flight)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $flight->flight_number }}
                                        <span class="block text-xs font-normal text-gray-400">{{ $flight->airline->name ?? '-' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ $flight->departureAirport->iata_code ?? 'ORG' }} &rarr; {{ $flight->arrivalAirport->iata_code ?? 'DST' }}
                                        <span class="block text-xs text-gray-400">{{ $flight->scheduled_departure->format('d M Y H:i') }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200 font-mono">
                                        {{ $flight->capacity_seats }} Kursi
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            {{ $flight->yieldBuckets->count() }} Ember
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('aviation.yield.optimize') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="flight_id" value="{{ $flight->id }}">
                                            <button type="submit" class="inline-flex items-center px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-medium transition">
                                                Optimalkan EMSR-b
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-400">Tidak ada jadwal penerbangan untuk dioptimasi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-750">
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-xs text-gray-500 text-right">
                                    Total Penerbangan Ditampilkan: {{ $vm->flights->count() }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if ($vm->flights->hasPages())
                    <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                        {{ $vm->flights->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

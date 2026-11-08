<x-app-layout>
    <x-slot name="header">
        <header class="page-codeshare-header">
            <h1 class="text-2xl font-black text-gray-900 tracking-tight dark:text-gray-100">
                Codeshare & Interline Clearing
            </h1>
            <p class="text-xs text-cyan-600 font-semibold dark:text-cyan-400">
                Prorate Manual Passenger IATA Multilateral Matrix
            </p>
        </header>
    </x-slot>

    <main class="py-5">
        <section class="max-w-7xl mx-auto px-4 space-y-5">
            @if (session('status'))
                <aside class="p-3 bg-emerald-100 border border-emerald-300 text-emerald-900 rounded-md text-xs dark:bg-emerald-900 dark:border-emerald-700 dark:text-emerald-100">
                    {{ session('status') }}
                </aside>
            @endif

            <!-- Metrik Aliansi -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Perjanjian Aktif</div>
                    <div class="mt-2 text-2xl font-extrabold text-cyan-600 dark:text-cyan-400">{{ $vm->activeAgreementsCount() }}</div>
                    <p class="mt-1 text-xs text-gray-400">Kerjasama antar-maskapai mitra</p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Rute Terpetakan</div>
                    <div class="mt-2 text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">{{ $vm->totalCodeshareRoutes() }}</div>
                    <p class="mt-1 text-xs text-gray-400">Nomor penerbangan marketing aktif</p>
                </div>
            </div>

            <!-- Tabel Perjanjian Codeshare -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 sm:p-6 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Katalog Perjanjian Codeshare</h3>
                    <p class="text-xs text-gray-500">Struktur bagi hasil, aturan prorasi, dan status keabsahan</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-750">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Kode Perjanjian</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Mitra Maskapai</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Skema & Split Bps</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500">Masa Berlaku</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium text-gray-500">Status & Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($vm->agreements as $agreement)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $agreement->agreement_code }}
                                        <span class="block text-xs font-normal text-gray-400">{{ $agreement->codeshareFlights->count() }} Penerbangan</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        <div class="text-xs text-gray-400">Operating: <strong class="text-gray-800 dark:text-gray-200">{{ $agreement->operatingAirline->name ?? '-' }}</strong></div>
                                        <div class="text-xs text-gray-400">Marketing: <strong class="text-gray-800 dark:text-gray-200">{{ $agreement->marketingAirline->name ?? '-' }}</strong></div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        <span class="font-medium text-indigo-600 dark:text-indigo-400">{{ strtoupper($agreement->agreement_type) }}</span>
                                        <span class="block text-xs font-mono text-gray-400">{{ $agreement->revenue_split_bps }} bps ({{ $agreement->revenue_split_bps / 100 }}%)</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs">
                                        {{ $agreement->valid_from->format('d/m/Y') }} &mdash; {{ $agreement->valid_until->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($agreement->status !== 'active')
                                            <form method="POST" action="{{ route('aviation.codeshare.activate', $agreement->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-3 py-1 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium transition">
                                                    Aktivasi
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                                                Aktif
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-400">Belum ada perjanjian codeshare yang terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-750">
                            <tr>
                                <td colspan="5" class="px-4 py-2 text-xs text-gray-500 text-right">
                                    Total Perjanjian Ditampilkan: {{ $vm->agreements->count() }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if ($vm->agreements->hasPages())
                    <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                        {{ $vm->agreements->links() }}
                    </div>
                @endif
            </div>
        </section>
    </main>
</x-app-layout>

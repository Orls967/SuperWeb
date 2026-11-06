<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-lg md:text-xl font-semibold leading-tight text-gray-800">
                {{ __('Portal Pelanggan Energi & Utilitas') }}
            </h2>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs md:text-xs font-medium bg-emerald-100 text-emerald-800">
                Self-Service Konsumen
            </span>
        </div>
    </x-slot>

    <div class="py-4 md:py-6">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-4 md:space-y-6">
            <!-- Property Selector & Live Profile -->
            <div class="bg-gradient-to-r from-indigo-700 to-indigo-900 rounded-xl p-4 md:p-6 text-white shadow-lg">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
                    <div>
                        <div class="text-xs text-indigo-200">ID Konsumen Terdaftar</div>
                        <h3 class="text-lg md:text-2xl font-bold">{{ $customerId }}</h3>
                        <p class="text-xs text-indigo-300 mt-0.5">Properti Aktif: {{ $propertyId }}</p>
                    </div>
                    <div class="mt-2 md:mt-0 text-left md:text-right">
                        <div class="text-xs text-indigo-200">Tagihan Belum Dibayar</div>
                        <div class="text-xl md:text-2xl font-bold font-mono text-amber-300">
                            Rp {{ number_format($outstandingTotal ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Meters & Consumption Breakdown Card -->
            <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
                <h3 class="text-sm md:text-base font-semibold text-gray-900 mb-3">Meter & Penggunaan Daya Real-Time</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 md:gap-4">
                    @forelse($meters as $m)
                        <div class="border border-gray-200 rounded-lg p-3 md:p-4 bg-gray-50/70">
                            <div class="flex justify-between items-center text-xs">
                                <span class="font-bold text-gray-800 font-mono">{{ $m->meter_number }}</span>
                                <span class="px-2 py-0.5 rounded-full text-2xs uppercase font-semibold {{ $m->utility_type === 'electricity' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $m->utility_type }}
                                </span>
                            </div>
                            <div class="mt-3">
                                <div class="text-2xs text-gray-500 uppercase">Akumulasi Bacaan AMI</div>
                                <div class="text-base md:text-lg font-bold font-mono text-gray-900">
                                    {{ number_format(($m->latest_reading_wh ?? 0) / 1000, 2) }} kWh
                                </div>
                            </div>
                            <div class="mt-2 text-2xs text-gray-400">
                                Sinkron: {{ $m->last_telemetry_at ? \Carbon\Carbon::parse($m->last_telemetry_at)->diffForHumans() : 'Standar' }}
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center text-xs text-gray-400 py-4">
                            Tidak ada meter yang terhubung ke akun Anda.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- TOU Consumption Breakdown Visualizer (Mobile 375px Friendly) -->
            <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
                <h3 class="text-sm md:text-base font-semibold text-gray-900 mb-2">Profil Konsumsi TOU (Time of Use)</h3>
                <p class="text-xs text-gray-500 mb-4">Distribusi beban listrik Anda berdasarkan zona tarif waktu:</p>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-medium text-emerald-700">Waktu Luar Beban Puncak (Off-Peak: 22:00-06:00)</span>
                            <span class="font-mono font-bold text-gray-800">55% (Hemat Tarif)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-emerald-500 h-2.5 rounded-full" style="width: 55%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-medium text-amber-700">Waktu Normal (Shoulder: 06:00-17:00)</span>
                            <span class="font-mono font-bold text-gray-800">30% (Tarif Standar)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-amber-500 h-2.5 rounded-full" style="width: 30%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-medium text-rose-700">Waktu Beban Puncak (Peak: 17:00-22:00)</span>
                            <span class="font-mono font-bold text-gray-800">15% (Tarif Premium)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-rose-500 h-2.5 rounded-full" style="width: 15%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Invoices for Customer -->
            <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 border border-gray-100">
                <h3 class="text-sm md:text-base font-semibold text-gray-900 mb-3">Riwayat Tagihan & Pembayaran</h3>
                <div class="overflow-x-auto -mx-4 sm:mx-0">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 text-gray-500 uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left">Invoice #</th>
                                <th class="px-3 py-2 text-left">Periode</th>
                                <th class="px-3 py-2 text-right">Jumlah Tagihan</th>
                                <th class="px-3 py-2 text-center">Status</th>
                                <th class="px-3 py-2 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($invoices as $inv)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono font-medium text-gray-900">
                                        {{ $inv->invoice_number }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-600 font-mono">
                                        {{ $inv->billing_period }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-gray-900">
                                        Rp {{ number_format($inv->total_amount_idr) }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-2xs font-semibold {{ $inv->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ strtoupper($inv->status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <a href="{{ route('egy.customer.bill', $inv->id) }}" class="text-indigo-600 hover:text-indigo-900 font-semibold">
                                            Buka Rincian
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-center text-gray-400 italic">
                                        Belum ada tagihan untuk akun Anda.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

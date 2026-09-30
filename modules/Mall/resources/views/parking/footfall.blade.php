<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">Analitik Kunjungan Mall</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Tren pengunjung per hari, jam sibuk, sebaran gate, dan konversi ke transaksi tenant
                </p>
            </div>
            <div class="flex gap-2 text-[11px] font-bold">
                @foreach([7 => '7 Hari', 30 => '30 Hari', 90 => '90 Hari'] as $range => $label)
                    <a href="{{ route('mall.parking.footfall', ['property_id' => $property->id, 'days' => $range]) }}"
                       class="px-3 py-2 rounded-lg {{ $days === $range ? 'bg-slate-800 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if($analytics['total_visitors'] === 0)
                <div class="p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                    <p class="font-bold mb-1">Belum ada data kunjungan</p>
                    <p class="text-xs">
                        Jalankan <code class="px-1.5 py-0.5 rounded bg-amber-100 font-mono">php artisan mall:simulate-footfall --days=90</code>
                        untuk mengisi data penghitung pengunjung per gate per jam.
                    </p>
                </div>
            @endif

            {{-- Ringkasan --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @php
                    $peakLabel = $analytics['peak_hour'] !== null
                        ? str_pad((string) $analytics['peak_hour'], 2, '0', STR_PAD_LEFT).':00'
                        : '—';
                    $summary = [
                        ['label' => 'Total Kunjungan', 'value' => number_format($analytics['total_visitors'], 0, ',', '.'), 'sub' => "periode {$days} hari"],
                        ['label' => 'Rata-rata Harian', 'value' => number_format($analytics['avg_daily'], 0, ',', '.'), 'sub' => 'pengunjung / hari'],
                        ['label' => 'Jam Tersibuk', 'value' => $peakLabel, 'sub' => $analytics['busiest_date'] ? 'terpadat '.\Carbon\Carbon::parse($analytics['busiest_date'])->translatedFormat('d M Y') : '—'],
                        ['label' => 'Konversi Tenant', 'value' => $analytics['conversion_rate'].'%', 'sub' => number_format($analytics['tenant_transactions'], 0, ',', '.').' transaksi tenant'],
                    ];
                @endphp

                @foreach($summary as $card)
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 font-bold mb-2">{{ $card['label'] }}</p>
                        <p class="text-2xl font-black text-slate-800 leading-none">{{ $card['value'] }}</p>
                        <p class="text-[11px] text-slate-400 mt-2">{{ $card['sub'] }}</p>
                    </div>
                @endforeach
            </div>

            <div x-data="footfallCharts({
                    daily: {{ Js::from($analytics['daily']) }},
                    hourly: {{ Js::from($analytics['hourly']) }}
                })" class="space-y-6">

                {{-- Tren harian --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <h3 class="font-bold text-slate-800 mb-4">Tren Kunjungan Harian</h3>
                    <div class="h-72">
                        <canvas x-ref="dailyChart"></canvas>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    {{-- Jam sibuk --}}
                    <div class="lg:col-span-8 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <h3 class="font-bold text-slate-800 mb-1">Distribusi per Jam Operasional</h3>
                        <p class="text-xs text-slate-500 mb-4">
                            Batang berwarna kuning adalah jam tersibuk — dipakai untuk menentukan jadwal masak outlet
                            dan penempatan petugas.
                        </p>
                        <div class="h-64">
                            <canvas x-ref="hourlyChart"></canvas>
                        </div>
                    </div>

                    {{-- Sebaran gate --}}
                    <div class="lg:col-span-4 bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <h3 class="font-bold text-slate-800 mb-4">Sebaran per Gate</h3>

                        @php $gateTotal = max(1, array_sum(array_column($analytics['by_gate'], 'visitors'))); @endphp

                        <div class="space-y-4">
                            @forelse($analytics['by_gate'] as $gate)
                                @php $share = round(($gate['visitors'] / $gateTotal) * 100, 1); @endphp
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1.5">
                                        <span class="font-bold text-slate-700">{{ $gate['gate'] }}</span>
                                        <span class="font-mono text-slate-500">{{ $share }}%</span>
                                    </div>
                                    <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full bg-blue-500" style="width: {{ $share }}%"></div>
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-1">
                                        {{ number_format($gate['visitors'], 0, ',', '.') }} pengunjung
                                    </p>
                                </div>
                            @empty
                                <p class="text-sm text-slate-400 text-center py-4">Belum ada data gate.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

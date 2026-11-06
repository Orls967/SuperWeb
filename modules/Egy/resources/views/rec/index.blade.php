<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Registri Sertifikat Energi Terbarukan (REC / I-REC)') }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('egy.rec.export') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Unduh Registri (CSV)
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Metrik Saldo REC -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-indigo-500">
                    <div class="text-sm font-medium text-gray-500">Total REC Dicetak (Lifetime)</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ number_format(($total_minted_wh ?? 0) / 1000000000, 2, ',', '.') }} MWh</div>
                    <div class="mt-1 text-xs text-indigo-600">Dari Pembangkit Surya & Biogas WTE</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-emerald-500">
                    <div class="text-sm font-medium text-gray-500">Inventori Aktif (Tersedia)</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ number_format(($active_inventory_wh ?? 0) / 1000000000, 2, ',', '.') }} MWh</div>
                    <div class="mt-1 text-xs text-emerald-600">Dapat diperdagangkan / ditransfer</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-amber-500">
                    <div class="text-sm font-medium text-gray-500">REC Dipensiunkan (Retired)</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900">{{ number_format(($total_retired_wh ?? 0) / 1000000000, 2, ',', '.') }} MWh</div>
                    <div class="mt-1 text-xs text-amber-600">Klaim Net-Zero Mall, Hotel & ESG Tenant</div>
                </div>
            </div>

            <!-- Diagram Siklus Hidup REC (SVG Flowchart) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-md font-bold text-gray-900 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Siklus Hidup Integritas Sertifikat REC (I-REC Life-Cycle Protocol)
                </h3>
                <div class="w-full overflow-x-auto">
                    <svg class="w-full h-32" viewBox="0 0 900 120">
                        <defs>
                            <marker id="arrow" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                <path d="M 0 0 L 10 5 L 0 10 z" fill="#6b7280"/>
                            </marker>
                        </defs>
                        <!-- Node 1: Minted -->
                        <rect x="30" y="35" width="160" height="50" rx="8" fill="#e0e7ff" stroke="#4f46e5" stroke-width="2"/>
                        <text x="110" y="58" font-size="12" font-weight="bold" fill="#312e81" text-anchor="middle">1. MINTED</text>
                        <text x="110" y="74" font-size="10" fill="#4338ca" text-anchor="middle">1 MWh / Unit Terverifikasi</text>

                        <!-- Panah 1 -->
                        <line x1="190" y1="60" x2="250" y2="60" stroke="#6b7280" stroke-width="2" marker-end="url(#arrow)"/>

                        <!-- Node 2: Listed -->
                        <rect x="260" y="35" width="160" height="50" rx="8" fill="#ecfdf5" stroke="#10b981" stroke-width="2"/>
                        <text x="340" y="58" font-size="12" font-weight="bold" fill="#064e3b" text-anchor="middle">2. LISTED</text>
                        <text x="340" y="74" font-size="10" fill="#047857" text-anchor="middle">Katalog Bursa & Pasar</text>

                        <!-- Panah 2 -->
                        <line x1="420" y1="60" x2="480" y2="60" stroke="#6b7280" stroke-width="2" marker-end="url(#arrow)"/>

                        <!-- Node 3: Transferred -->
                        <rect x="490" y="35" width="160" height="50" rx="8" fill="#fef3c7" stroke="#f59e0b" stroke-width="2"/>
                        <text x="570" y="58" font-size="12" font-weight="bold" fill="#78350f" text-anchor="middle">3. TRANSFERRED</text>
                        <text x="570" y="74" font-size="10" fill="#b45309" text-anchor="middle">Kepemilikan Berpindah</text>

                        <!-- Panah 3 -->
                        <line x1="650" y1="60" x2="710" y2="60" stroke="#6b7280" stroke-width="2" marker-end="url(#arrow)"/>

                        <!-- Node 4: Retired -->
                        <rect x="720" y="35" width="160" height="50" rx="8" fill="#f3f4f6" stroke="#9ca3af" stroke-width="2"/>
                        <text x="800" y="58" font-size="12" font-weight="bold" fill="#1f2937" text-anchor="middle">4. RETIRED (TERMINAL)</text>
                        <text x="800" y="74" font-size="10" fill="#4b5563" text-anchor="middle">Klaim Hangus Anti-Double</text>
                    </svg>
                </div>
            </div>

            <!-- Tabel Inventori REC & Modal Pensiun -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-md font-bold text-gray-900 mb-4">Daftar Sertifikat REC Terdaftar</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Nomor Seri I-REC</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Sumber Energi</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Periode / Vintage</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Energi (MWh)</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Pemilik Legal</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Status</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-500">Aksi Klaim Pensiun</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($certificates as $c)
                                <tr>
                                    <td class="px-4 py-3 font-mono font-bold text-indigo-600">{{ $c->certificate_serial }}</td>
                                    <td class="px-4 py-3 uppercase">{{ str_replace('_', ' ', $c->energy_source_type) }}</td>
                                    <td class="px-4 py-3 text-center font-mono">{{ $c->period_month }} ({{ $c->vintage_year }})</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold">{{ number_format($c->energy_wh / 1000000000, 2, ',', '.') }} MWh</td>
                                    <td class="px-4 py-3">Party #{{ $c->owner_party_id }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($c->status === 'retired')
                                            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700 font-bold">RETIRED</span>
                                        @elseif($c->status === 'listed')
                                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-800 font-bold">LISTED</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-xs bg-indigo-100 text-indigo-800 font-bold">{{ strtoupper($c->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($c->status !== 'retired')
                                            <form action="{{ route('egy.rec.retire') }}" method="POST" class="inline-flex space-x-1">
                                                @csrf
                                                <input type="hidden" name="certificate_id" value="{{ $c->id }}">
                                                <input type="hidden" name="beneficiary_name" value="Tenant Green Lease Holding">
                                                <input type="hidden" name="retirement_reason" value="Klaim Penggunaan 100% Energi Hijau Gedung Pusat">
                                                <button type="submit" onclick="return confirm('Pensiunkan permanen unit REC ini?')" class="px-2 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-semibold">
                                                    Pensiunkan (Claim)
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-400 italic">Diklaim: {{ $c->retirement_beneficiary }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">Belum ada unit REC tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

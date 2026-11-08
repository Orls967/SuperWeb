<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Layanan Pelabuhan, Jendela Tambat & Akun Pengeluaran (PDA/FDA)') }}
            </h2>
            <a href="{{ route('port.fleet.index') }}" class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">Kembali ke Armada</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Tabel Kunjungan Pelabuhan (Port Calls) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Daftar Kunjungan Pelabuhan & Layanan Maritim</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Pelabuhan / Tujuan</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Pelayaran</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Perkiraan Tiba (ETA)</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Sarat Air (Draft Fwd/Aft)</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Pandu & Tunda</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($portCalls as $call)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-900">
                                        {{ $call->port_name }} ({{ $call->port_code }})
                                        <span class="block text-xs text-gray-400 font-normal">Tujuan: {{ $call->call_purpose }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-indigo-600 font-mono">{{ $call->voyage->voyage_code ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $call->estimated_arrival_at->format('d M Y H:i') }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700">{{ $call->arrival_draft_forward_m }}m / {{ $call->arrival_draft_aft_m }}m</td>
                                    <td class="px-4 py-3 text-center text-xs">
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700">Pandu: {{ $call->pilotageRequests->count() }}</span>
                                        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 ml-1">Tunda: {{ $call->tugServices->count() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-xs rounded font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            {{ $call->call_status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada data kunjungan pelabuhan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Akun Pengeluaran Pelabuhan (PDA vs FDA) -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Riwayat Akun Pengeluaran Pelabuhan (PDA & FDA)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Referensi Akun</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Tipe Akun</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Uang Labuh / Tambat</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Pandu & Tunda</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Total Pengeluaran (USD)</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Selisih vs PDA</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($disbursements as $disb)
                                <tr>
                                    <td class="px-4 py-3 font-mono font-semibold text-gray-800">{{ $disb->account_reference }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 text-xs font-bold rounded {{ $disb->disbursement_type === 'FDA' ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $disb->disbursement_type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-600">${{ number_format($disb->port_dues_usd + $disb->berth_hire_usd, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-600">${{ number_format($disb->pilotage_usd + $disb->towage_usd, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-gray-900">${{ number_format($disb->total_disbursement_usd, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono {{ $disb->variance_with_pda_usd > 0 ? 'text-red-500' : 'text-emerald-500' }}">
                                        {{ $disb->variance_with_pda_usd != 0 ? (($disb->variance_with_pda_usd > 0 ? '+' : '') . number_format($disb->variance_with_pda_usd, 2)) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-700">
                                            {{ $disb->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">Belum ada akun pengeluaran pelabuhan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


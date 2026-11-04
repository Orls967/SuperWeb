<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Asisten Penawaran Tender (Bid Assistant) — KONSEP §7F Slice 3</h2>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800 text-sm">{{ session('success') }}</div>@endif
            @if (session('info'))<div class="rounded-md bg-blue-50 p-4 text-blue-800 text-sm">{{ session('info') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800 text-sm"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-4 text-xs sm:text-sm text-indigo-900 leading-relaxed">
                <strong>KONSEP §7F Slice 3 &amp; Fase 84:</strong> Asisten penawaran tender terautomasi menggunakan pengoptimalan expected profit diskrit deterministik, perakitan klausul standar teruji via kontrak K13 <code>ClauseTemplateReader</code> di modul Contract, dan penegakan persetujuan four-eyes oleh <code>procurement_manager</code> sebelum penawaran diajukan ke lelang.
            </div>

            <!-- KPI Cards responsif mobile 375px -->
            <div class="grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-3 lg:grid-cols-6 text-center">
                <div class="rounded-lg bg-white p-3 sm:p-4 shadow border border-gray-100">
                    <div class="text-xs text-gray-500">Agent Konfigurasi</div>
                    <div class="text-lg sm:text-xl font-bold text-gray-800">{{ number_format($summary['total_agents'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-3 sm:p-4 shadow border border-gray-100">
                    <div class="text-xs text-gray-500">Evaluasi Run</div>
                    <div class="text-lg sm:text-xl font-bold text-indigo-600">{{ number_format($summary['total_evaluations'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-3 sm:p-4 shadow border border-gray-100">
                    <div class="text-xs text-gray-500">Menunggu Approval</div>
                    <div class="text-lg sm:text-xl font-bold text-amber-600">{{ number_format($summary['pending_approval_count'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-3 sm:p-4 shadow border border-gray-100">
                    <div class="text-xs text-gray-500">Penawaran Submit</div>
                    <div class="text-lg sm:text-xl font-bold text-blue-600">{{ number_format($summary['submitted_count'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-3 sm:p-4 shadow border border-gray-100">
                    <div class="text-xs text-gray-500">Win Rate</div>
                    <div class="text-lg sm:text-xl font-bold text-emerald-600">{{ $summary['win_rate_percent'] ?? 0 }}%</div>
                </div>
                <div class="rounded-lg bg-white p-3 sm:p-4 shadow border border-gray-100">
                    <div class="text-xs text-gray-500">Nilai Kontrak Menang</div>
                    <div class="text-base sm:text-lg font-bold text-purple-600 truncate">Rp {{ number_format($summary['total_won_value_idr'] ?? 0) }}</div>
                </div>
            </div>

            <!-- Form Evaluasi Lelang Baru -->
            <div class="rounded-lg bg-white p-4 sm:p-6 shadow border border-gray-100">
                <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-4">Evaluasi Penawaran Lelang Tender Baru</h3>
                <form action="{{ route('procurement.bid-assistant.evaluate') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Pilih Agent</label>
                        <select name="bidding_agent_id" class="mt-1 block w-full rounded-md border-gray-300 text-xs sm:text-sm py-2 px-3 shadow-sm min-h-[44px]" required>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}">{{ $agent->agent_code }} ({{ $agent->commodity_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Kode Referensi Tender</label>
                        <input type="text" name="tender_reference_code" placeholder="TND-PLN-2026-X" class="mt-1 block w-full rounded-md border-gray-300 text-xs sm:text-sm py-2 px-3 shadow-sm min-h-[44px]" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Harga Komoditas (IDR)</label>
                        <input type="number" name="commodity_feed_price_idr" placeholder="800000000" class="mt-1 block w-full rounded-md border-gray-300 text-xs sm:text-sm py-2 px-3 shadow-sm min-h-[44px]" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Estimasi Logistik (IDR)</label>
                        <input type="number" name="estimated_logistics_cost_idr" placeholder="50000000" class="mt-1 block w-full rounded-md border-gray-300 text-xs sm:text-sm py-2 px-3 shadow-sm min-h-[44px]" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Skor Risiko Buyer (0-1.0)</label>
                        <input type="number" step="0.01" name="buyer_risk_score" placeholder="0.20" class="mt-1 block w-full rounded-md border-gray-300 text-xs sm:text-sm py-2 px-3 shadow-sm min-h-[44px]" required>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-5 flex justify-end">
                        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-xs sm:text-sm font-semibold text-white shadow hover:bg-indigo-700 min-h-[44px]">
                            Hitung Penawaran Optimal (AI)
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabel Daftar Penawaran & Persetujuan Four-Eyes -->
            <div class="rounded-lg bg-white p-4 sm:p-6 shadow border border-gray-100">
                <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-4">Daftar Penawaran &amp; Antrean Persetujuan Four-Eyes</h3>
                <div class="overflow-x-auto -mx-4 sm:mx-0">
                    <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-4 py-3 text-left font-medium text-gray-500">Kode Pengajuan</th>
                                <th class="px-3 sm:px-4 py-3 text-left font-medium text-gray-500">Tender &amp; Agent</th>
                                <th class="px-3 sm:px-4 py-3 text-right font-medium text-gray-500">Harga Penawaran</th>
                                <th class="px-3 sm:px-4 py-3 text-center font-medium text-gray-500">Status</th>
                                <th class="px-3 sm:px-4 py-3 text-left font-medium text-gray-500">Kontrak / Feedback</th>
                                <th class="px-3 sm:px-4 py-3 text-center font-medium text-gray-500">Aksi Four-Eyes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($submissions as $sub)
                                <tr>
                                    <td class="px-3 sm:px-4 py-3 font-mono font-medium text-gray-900 whitespace-nowrap">{{ $sub->submission_code }}</td>
                                    <td class="px-3 sm:px-4 py-3 text-gray-600 whitespace-nowrap">
                                        {{ $sub->run->tender_reference_code ?? '—' }}
                                        <div class="text-[10px] text-gray-400">{{ $sub->run->agent->agent_code ?? '—' }}</div>
                                    </td>
                                    <td class="px-3 sm:px-4 py-3 text-right font-medium text-gray-900 whitespace-nowrap">Rp {{ number_format($sub->submitted_bid_price_idr) }}</td>
                                    <td class="px-3 sm:px-4 py-3 text-center whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold
                                            @if ($sub->status === 'WON') bg-emerald-100 text-emerald-800
                                            @elseif ($sub->status === 'SUBMITTED') bg-blue-100 text-blue-800
                                            @elseif ($sub->status === 'LOST') bg-gray-100 text-gray-800
                                            @else bg-amber-100 text-amber-800 @endif">
                                            {{ $sub->status }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-3 text-gray-500 text-xs">
                                        @if ($sub->created_contract_code)
                                            <span class="font-mono text-purple-700 font-semibold">{{ $sub->created_contract_code }}</span>
                                        @elseif ($sub->feedback_reason)
                                            <span class="italic text-gray-600">{{ $sub->feedback_reason }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 sm:px-4 py-3 text-center whitespace-nowrap">
                                        @if ($sub->status === 'PENDING_APPROVAL')
                                            <form action="{{ route('procurement.bid-assistant.approve', $sub) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 min-h-[36px]">
                                                    Setujui (Four-Eyes)
                                                </button>
                                            </form>
                                        @elseif ($sub->status === 'SUBMITTED')
                                            <div class="flex items-center justify-center space-x-1">
                                                <form action="{{ route('procurement.bid-assistant.result', $sub) }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="result" value="WON">
                                                    <button type="submit" class="rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700">
                                                        Menang
                                                    </button>
                                                </form>
                                                <form action="{{ route('procurement.bid-assistant.result', $sub) }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="result" value="LOST">
                                                    <input type="hidden" name="feedback_reason" value="Kalah harga pada lelang final">
                                                    <button type="submit" class="rounded bg-gray-600 px-2 py-1 text-xs font-semibold text-white hover:bg-gray-700">
                                                        Kalah
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">Tuntas</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada penawaran lelang yang diajukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


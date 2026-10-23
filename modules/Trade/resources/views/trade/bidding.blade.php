<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">AI Contract Bidding Agent (Lelang Pengadaan Otomatis) — Fase 84</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if (session('info'))<div class="rounded-md bg-blue-50 p-4 text-blue-800">{{ session('info') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-cyan-50 px-4 py-3 text-sm text-cyan-800">
                <strong>Fase 84:</strong> AI Bidding Agent deterministik untuk lelang tender komoditas global, perayapan feed harga &amp; logistik, draf klausul komersial standar, persetujuan four-eyes wajib manusia, dan otomatisasi kontrak pasca-menang dengan komitmen anggaran ledger.
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-6 text-center">
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Agent Aktif</div>
                    <div class="text-xl font-bold text-gray-800">{{ number_format($summary['total_agents'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Total Evaluasi Run</div>
                    <div class="text-xl font-bold text-cyan-600">{{ number_format($summary['total_evaluations'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Pending Four-Eyes</div>
                    <div class="text-xl font-bold text-amber-600">{{ number_format($summary['pending_approval_count'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Tender Disubmit</div>
                    <div class="text-xl font-bold text-blue-600">{{ number_format($summary['submitted_count'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Win Rate Lelang</div>
                    <div class="text-xl font-bold text-emerald-600">{{ $summary['win_rate_percent'] ?? 0 }}%</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Nilai Kontrak Menang</div>
                    <div class="text-xl font-bold text-purple-600">Rp {{ number_format($summary['total_won_value_idr'] ?? 0) }}</div>
                </div>
            </div>

            <!-- Interactive Forms -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Configure Agent -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">84.1 Konfigurasi AI Bidding Agent</h3>
                    <form method="POST" action="{{ route('trade.bidding.agents.store') }}" class="mt-3 space-y-3 text-sm">
                        @csrf
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Kode Agent</label>
                                <input name="agent_code" value="AGT-COAL-{{ time() % 1000 }}" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Entitas Bisnis</label>
                                <input name="entity_code" value="TRD-GLOBAL-ID" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Komoditas</label>
                                <input name="commodity_code" value="COAL_GAR5000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Batas Bawah (Floor IDR)</label>
                                <input name="min_price_floor_idr" type="number" min="1000" value="800000000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Batas Atas (Ceiling IDR)</label>
                                <input name="max_price_ceiling_idr" type="number" min="1000" value="1500000000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Target Margin (%)</label>
                                <input name="target_margin_pct" type="number" step="0.5" min="1" max="100" value="20.0" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Toleransi Risiko Maks (0-1.0)</label>
                                <input name="max_risk_score" type="number" step="0.05" min="0.01" max="1.0" value="0.35" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded bg-cyan-600 py-2 text-xs font-semibold text-white">Daftarkan AI Bidding Agent</button>
                    </form>
                </section>

                <!-- Run Evaluation -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">84.2 Evaluasi Rekomendasi Penawaran Lelang</h3>
                    <form method="POST" action="{{ route('trade.bidding.runs.evaluate') }}" class="mt-3 space-y-3 text-sm">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Pilih Agent</label>
                                <select name="bidding_agent_id" class="w-full rounded border-gray-300 text-sm">
                                    @foreach ($agents as $ag)
                                        <option value="{{ $ag->id }}">{{ $ag->agent_code }} ({{ $ag->commodity_code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Kode Tender / Lelang</label>
                                <input name="tender_reference_code" value="TND-PLN-{{ time() % 10000 }}" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs text-gray-600">Harga Komoditas Feed</label>
                                <input name="commodity_feed_price_idr" type="number" min="1000" value="800000000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Biaya Logistik Rate Card</label>
                                <input name="estimated_logistics_cost_idr" type="number" min="0" value="50000000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Skor Risiko Buyer (0-1.0)</label>
                                <input name="buyer_risk_score" type="number" step="0.05" min="0" max="1.0" value="0.20" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded bg-emerald-600 py-2 text-xs font-semibold text-white">Jalankan Evaluasi Deterministik AI</button>
                    </form>
                </section>
            </div>

            <!-- Submissions Table -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold text-gray-800">Daftar Penawaran Lelang &amp; Persetujuan Four-Eyes</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 uppercase">
                            <tr>
                                <th class="p-2">Kode Pengajuan</th>
                                <th class="p-2">Tender Ref</th>
                                <th class="p-2">Agent</th>
                                <th class="p-2">Nilai Penawaran</th>
                                <th class="p-2">Status</th>
                                <th class="p-2">Persetujuan Manusia</th>
                                <th class="p-2">Kontrak Terbit</th>
                                <th class="p-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-gray-700">
                            @forelse ($submissions as $sub)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-2 font-mono font-bold">{{ $sub->submission_code }}</td>
                                    <td class="p-2 font-mono">{{ $sub->run->tender_reference_code ?? '—' }}</td>
                                    <td class="p-2">{{ $sub->run->agent->agent_code ?? '—' }}</td>
                                    <td class="p-2 font-bold text-emerald-700">Rp {{ number_format($sub->submitted_bid_price_idr) }}</td>
                                    <td class="p-2">
                                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $sub->status === 'WON' ? 'bg-purple-100 text-purple-800' : ($sub->status === 'SUBMITTED' ? 'bg-blue-100 text-blue-800' : ($sub->status === 'LOST' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800')) }}">
                                            {{ $sub->status }}
                                        </span>
                                    </td>
                                    <td class="p-2">
                                        @if ($sub->approved_by_user_id)
                                            <span class="text-xs text-emerald-700 font-semibold">User #{{ $sub->approved_by_user_id }} ({{ $sub->approved_at?->format('H:i d/m') }})</span>
                                        @else
                                            <span class="text-xs text-amber-600">Belum disetujui</span>
                                        @endif
                                    </td>
                                    <td class="p-2 font-mono">{{ $sub->created_contract_code ?? ($sub->feedback_reason ? 'Feedback: '.$sub->feedback_reason : '—') }}</td>
                                    <td class="p-2 flex gap-1">
                                        @if ($sub->status === 'PENDING_APPROVAL')
                                            <!-- Four-eyes approval button -->
                                            <form method="POST" action="{{ route('trade.bidding.submissions.approve', $sub) }}">
                                                @csrf
                                                <button class="rounded bg-indigo-600 px-2 py-1 text-xs text-white">Approve Four-Eyes</button>
                                            </form>
                                        @elseif ($sub->status === 'SUBMITTED')
                                            <!-- Win button -->
                                            <form method="POST" action="{{ route('trade.bidding.submissions.result', $sub) }}">
                                                @csrf
                                                <input type="hidden" name="result" value="WON">
                                                <button class="rounded bg-purple-600 px-2 py-1 text-xs text-white">Tandai Menang</button>
                                            </form>
                                            <!-- Lost button -->
                                            <form method="POST" action="{{ route('trade.bidding.submissions.result', $sub) }}">
                                                @csrf
                                                <input type="hidden" name="result" value="LOST">
                                                <input type="hidden" name="feedback_reason" value="Kalah harga dari kompetitor lokal">
                                                <button class="rounded bg-rose-600 px-2 py-1 text-xs text-white">Tandai Kalah</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-400">Selesai</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="p-4 text-center text-gray-400">Belum ada pengajuan penawaran tender.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Evaluation Runs Table -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-lg font-semibold text-gray-800">Riwayat Evaluasi Run Deterministik AI</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 uppercase">
                            <tr>
                                <th class="p-2">Kode Run</th>
                                <th class="p-2">Tender Ref</th>
                                <th class="p-2">Komoditas Feed</th>
                                <th class="p-2">Logistik</th>
                                <th class="p-2">Risiko Buyer</th>
                                <th class="p-2">Rekomendasi Harga</th>
                                <th class="p-2">SHA-256 Hash</th>
                                <th class="p-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-gray-700">
                            @forelse ($runs as $r)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-2 font-mono font-bold">{{ $r->run_code }}</td>
                                    <td class="p-2 font-mono">{{ $r->tender_reference_code }}</td>
                                    <td class="p-2">Rp {{ number_format($r->commodity_feed_price_idr) }}</td>
                                    <td class="p-2">Rp {{ number_format($r->estimated_logistics_cost_idr) }}</td>
                                    <td class="p-2 font-semibold">{{ $r->buyer_risk_score }}</td>
                                    <td class="p-2 font-bold text-emerald-700">Rp {{ number_format($r->optimal_bid_price_idr) }}</td>
                                    <td class="p-2 font-mono text-[10px] text-gray-500">{{ substr($r->evaluation_hash, 0, 16) }}...</td>
                                    <td class="p-2">
                                        <form method="POST" action="{{ route('trade.bidding.runs.prepare', $r) }}">
                                            @csrf
                                            <button class="rounded bg-cyan-600 px-2 py-1 text-xs text-white">Siapkan Draft</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="p-4 text-center text-gray-400">Belum ada evaluasi run.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

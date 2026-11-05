<x-app-layout>
    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Permohonan Restrukturisasi: {{ $req->request_ref }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Akad: {{ $req->contract_type }} &bull; Status: <span class="font-bold">{{ $req->status }}</span></p>
            </div>
            <a href="{{ route('syariah.ops.restructuring.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Daftar</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">Total Hutang Restrukturisasi</div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white">Rp {{ number_format($req->total_restructured_debt_minor / 100, 0, ',', '.') }}</div>
                <div class="text-xs text-gray-500 mt-2">Pokok: Rp {{ number_format($req->revised_principal_minor / 100, 0, ',', '.') }} | Margin: Rp {{ number_format($req->revised_margin_minor / 100, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">Potongan Margin (Haircut)</div>
                <div class="text-2xl font-bold text-emerald-600">{{ number_format($req->margin_haircut_bps / 100, 1) }}%</div>
                <div class="text-xs text-gray-500 mt-2">Margin Lama: Rp {{ number_format($req->original_margin_minor / 100, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="text-xs text-gray-500 font-semibold uppercase">Struktur Tenor</div>
                <div class="text-2xl font-bold text-indigo-600">{{ $req->new_tenor_months }} Bulan</div>
                <div class="text-xs text-gray-500 mt-2">Masa Tenggang (Grace): {{ $req->grace_period_months }} Bulan</div>
            </div>
        </div>

        <!-- Approval Action Cards -->
        @if($req->status === 'REQUESTED')
            <div class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-850 p-6 rounded-xl mb-8">
                <h3 class="font-bold text-amber-900 dark:text-amber-200 mb-2">Penelaahan Dewan Pengawas Syariah (DPS Four-Eyes Review)</h3>
                <p class="text-sm text-amber-800 dark:text-amber-300 mb-4">Pastikan tidak ada klausul penambahan margin bunga ilegal (riba) sebelum memberi rekomendasi persetujuan.</p>
                <form method="POST" action="{{ route('syariah.ops.restructuring.dps-review', $req->id) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-700 dark:text-gray-300">Catatan Fatwa DPS</label>
                        <textarea name="notes" rows="2" class="w-full mt-1 rounded-md border-gray-300 dark:bg-gray-900 text-sm" placeholder="Catatan kepatuhan syariah...">Telah diverifikasi tidak mengandung unsur bunga kapitalisasi atau penambahan margin di atas akad awal.</textarea>
                    </div>
                    <div class="flex gap-4">
                        <button type="submit" name="is_approved" value="1" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-semibold">Setujui Kepatuhan Syariah</button>
                        <button type="submit" name="is_approved" value="0" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-md text-sm font-semibold">Tolak</button>
                    </div>
                </form>
            </div>
        @elseif($req->status === 'DPS_REVIEWED')
            <div class="bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-850 p-6 rounded-xl mb-8 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-blue-900 dark:text-blue-200">Rekomendasi DPS Disetujui</h3>
                    <p class="text-sm text-blue-800 dark:text-blue-300">Catatan: {{ $req->dps_notes }}</p>
                </div>
                <form method="POST" action="{{ route('syariah.ops.restructuring.execute', $req->id) }}">
                    @csrf
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-sm">Eksekusi Jadwal & Ledger Baru</button>
                </form>
            </div>
        @endif

        <!-- Installments Schedule -->
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Jadwal Angsuran Amortisasi Baru (Algoritma B9 Bersertifikat)</h2>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Bulan Ke</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Jatuh Tempo</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Cicilan Pokok</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Cicilan Margin</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Total Angsuran</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Sisa Pokok</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($req->installments as $ins)
                        <tr>
                            <td class="px-6 py-4 font-bold">Bulan {{ $ins->month_number }}</td>
                            <td class="px-6 py-4">{{ $ins->due_date->format('d/m/Y') }}</td>
                            <td class="px-6 py-4">Rp {{ number_format($ins->principal_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">Rp {{ number_format($ins->margin_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">Rp {{ number_format($ins->total_installment_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">Rp {{ number_format($ins->remaining_principal_minor / 100, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 text-xs rounded-full font-semibold {{ $ins->payment_status === 'PAID' ? 'bg-emerald-100 text-emerald-800' : ($ins->is_grace ? 'bg-gray-100 text-gray-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $ins->is_grace ? 'GRACE PERIOD' : $ins->payment_status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-6 text-center text-gray-500">Jadwal amortisasi akan dibentuk setelah permohonan disetujui dan dieksekusi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

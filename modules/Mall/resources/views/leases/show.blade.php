<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-extrabold text-2xl text-slate-800 leading-tight">
                        Kontrak Sewa {{ $lease->lease_number }}
                    </h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $lease->status->badgeClass() }}">
                        {{ $lease->status->label() }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 mt-1">{{ $lease->property?->name }} &bull; Unit {{ $lease->unit?->unit_number }} (Lantai {{ $lease->unit?->floor }})</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('mall.leases.index') }}" class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-xl hover:bg-slate-50">
                    &larr; Daftar Sewa
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ activateModal: false, terminateModal: false, renewModal: false }">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between">
                    <span>{{ session('success') }}</span>
                    <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-500">&times;</button>
                </div>
            @endif

            @if($errors->any())
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Action Status Banner -->
            @if($lease->status->value === 'draft')
                <div class="p-5 rounded-2xl bg-amber-50 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h4 class="font-bold text-amber-900 text-sm">Kontrak Belum Aktif — Menunggu Pembayaran Deposit Jaminan</h4>
                        <p class="text-xs text-amber-700 mt-0.5">Deposit jaminan sebesar <strong>Rp {{ number_format($lease->security_deposit_amount, 0, ',', '.') }}</strong> akan dipotong dari saldo dompet tenant dan ditahan sebagai pos titipan liabilitas.</p>
                    </div>
                    <button @click="activateModal = true" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-md shadow-amber-600/20 whitespace-nowrap transition-all">
                        Aktivasi & Bayar Deposit &rarr;
                    </button>
                </div>
            @elseif($lease->status->value === 'active')
                <div class="p-5 rounded-2xl bg-emerald-50 border border-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h4 class="font-bold text-emerald-900 text-sm">Kontrak Sedang Aktif Berjalan</h4>
                        <p class="text-xs text-emerald-700 mt-0.5">
                            Diaktifkan pada {{ $lease->activated_at?->format('d/m/Y H:i') }} WIB &bull; Deposit jaminan: <strong>Rp {{ number_format($lease->security_deposit_amount, 0, ',', '.') }}</strong> (Tersimpan di Escrow/Liabilitas)
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="renewModal = true" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all">
                            Perpanjang (Renew)
                        </button>
                        <button @click="terminateModal = true" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all">
                            Terminasi Sewa
                        </button>
                    </div>
                </div>
            @endif

            <!-- Main Content: Tenant, Unit, and Pricing -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Col 1 & 2: Details & Escalation -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Basic Info Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                        <h3 class="font-bold text-base text-slate-900 border-b border-slate-100 pb-3">Informasi Pokok Perjanjian Sewa</h3>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                            <div>
                                <span class="text-slate-400 block mb-1">Tenant Mitra</span>
                                <span class="font-bold text-slate-800 text-sm block">{{ $lease->tenant?->brand_name }}</span>
                                <span class="text-slate-500">{{ $lease->tenant?->company_name }}</span>
                            </div>

                            <div>
                                <span class="text-slate-400 block mb-1">Unit & Lokasi</span>
                                <span class="font-bold text-slate-800 text-sm block">Unit {{ $lease->unit?->unit_number }}</span>
                                <span class="text-slate-500">Lantai {{ $lease->unit?->floor }} &bull; {{ $lease->unit?->area_sqm }} m²</span>
                            </div>

                            <div>
                                <span class="text-slate-400 block mb-1">Model Sewa</span>
                                <span class="font-bold text-blue-700 text-sm block">{{ $lease->rent_model->label() }}</span>
                                @if($lease->revenue_share_percent)
                                    <span class="text-slate-500">Rev Share: {{ $lease->revenue_share_percent }}%</span>
                                @endif
                            </div>

                            <div>
                                <span class="text-slate-400 block mb-1">Periode Kontrak</span>
                                <span class="font-semibold text-slate-800 block">{{ $lease->start_date->format('d/m/Y') }}</span>
                                <span class="text-slate-500">s/d {{ $lease->end_date->format('d/m/Y') }}</span>
                            </div>

                            <div>
                                <span class="text-slate-400 block mb-1">Masa Fit-Out</span>
                                <span class="font-semibold text-slate-800 block">{{ $lease->fit_out_days }} Hari Bebas Sewa</span>
                                <span class="text-slate-500">{{ $lease->isInFitOut() ? 'Sedang Renovasi' : 'Selesai Fit-Out' }}</span>
                            </div>

                            <div>
                                <span class="text-slate-400 block mb-1">Siklus Billing & Denda</span>
                                <span class="font-semibold text-slate-800 block">Tgl {{ $lease->billing_day }} tiap bulan</span>
                                <span class="text-slate-500">Grace: {{ $lease->grace_days }} hari &bull; Denda: {{ $lease->penalty_rate_daily_percent }}%/hari</span>
                            </div>
                        </div>
                    </div>

                    <!-- Annual Escalation Schedule Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-base text-slate-900">Jadwal Proyeksi Eskalasi Tarif Sewa</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Kenaikan tahunan {{ $lease->annual_escalation_percent }}% per tahun sesuai klausul sewa</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-bold font-mono">
                                Saat Ini: Tahun ke-{{ $lease->currentLeaseYear() }}
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                                        <th class="p-3">Tahun Kontrak</th>
                                        <th class="p-3 text-right">Sewa Dasar / Bulan</th>
                                        <th class="p-3 text-right">Service Charge / Bulan</th>
                                        <th class="p-3 text-right">Total Kewajiban / Bulan</th>
                                        <th class="p-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @for($y = 1; $y <= 3; $y++)
                                        @php
                                            $yRent = $lease->calculateMonthlyRent($y);
                                            $yTotal = $yRent + $lease->service_charge_monthly;
                                            $isCurrent = $lease->currentLeaseYear() === $y;
                                        @endphp
                                        <tr class="{{ $isCurrent ? 'bg-blue-50/40 font-bold' : '' }}">
                                            <td class="p-3">Tahun ke-{{ $y }}</td>
                                            <td class="p-3 text-right font-mono text-slate-700">Rp {{ number_format($yRent, 0, ',', '.') }}</td>
                                            <td class="p-3 text-right font-mono text-slate-500">Rp {{ number_format($lease->service_charge_monthly, 0, ',', '.') }}</td>
                                            <td class="p-3 text-right font-mono font-black text-slate-900">Rp {{ number_format($yTotal, 0, ',', '.') }}</td>
                                            <td class="p-3 text-center">
                                                @if($isCurrent)
                                                    <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">Tahun Berjalan</span>
                                                @else
                                                    <span class="text-slate-400 text-[10px]">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Col 3: Financial Summary & Deposit Status -->
                <div class="space-y-6">

                    <!-- Billing Summary Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                        <h3 class="font-bold text-base text-slate-900 border-b border-slate-100 pb-3">Ringkasan Tagihan Bulanan</h3>

                        <div class="space-y-3 text-xs">
                            <div class="flex items-center justify-between text-slate-600">
                                <span>Sewa Dasar Unit</span>
                                <span class="font-mono font-bold text-slate-800">Rp {{ number_format($lease->currentMonthlyRent(), 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-600">
                                <span>Service Charge Mall</span>
                                <span class="font-mono font-bold text-slate-800">Rp {{ number_format($lease->service_charge_monthly, 0, ',', '.') }}</span>
                            </div>
                            <div class="pt-3 border-t border-slate-100 flex items-center justify-between font-black text-sm text-slate-900">
                                <span>Total / Bulan</span>
                                <span class="font-mono text-blue-700">Rp {{ number_format($lease->totalMonthlyBill(), 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Security Deposit Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="font-bold text-base text-slate-900">Deposit Jaminan</h3>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $lease->deposit_status->value === 'held' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ $lease->deposit_status->label() }}
                            </span>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <span class="text-xs text-slate-400 block">Nominal Deposit Disepakati</span>
                                <span class="text-2xl font-black text-slate-900 font-mono">Rp {{ number_format($lease->security_deposit_amount, 0, ',', '.') }}</span>
                            </div>
                            <p class="text-xs text-slate-500">
                                Setara dengan {{ round($lease->security_deposit_amount / max(1, $lease->base_monthly_rent)) }} bulan sewa dasar. Berfungsi sebagai garansi atas kerusakan unit, tunggakan sewa, atau utilitas.
                            </p>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Modal Aktivasi Kontrak & Bayar Deposit -->
            <div x-show="activateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
                <div x-show="activateModal" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="activateModal = false"></div>
                <div class="flex min-h-full items-center justify-center p-4">
                    <form method="POST" action="{{ route('mall.leases.activate', $lease) }}" x-show="activateModal" x-transition class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-100 space-y-4">
                        @csrf
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="font-bold text-base text-slate-900">Aktivasi Kontrak & Tarik Deposit</h3>
                            <button type="button" @click="activateModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                        </div>
                        <p class="text-xs text-slate-600">
                            Sistem akan mendebet saldo dompet Anda sebesar <strong>Rp {{ number_format($lease->security_deposit_amount, 0, ',', '.') }}</strong> dan mengkreditkan ke pos titipan jaminan sewa tenant.
                        </p>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">PIN Dompet (Opsional)</label>
                            <input type="password" name="pin" maxlength="6" class="w-full rounded-xl border-slate-200 text-xs text-center font-mono tracking-widest focus:ring-blue-500 focus:border-blue-500" placeholder="••••••">
                        </div>
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="activateModal = false" class="px-4 py-2 border rounded-xl text-xs font-semibold text-slate-600">Batal</button>
                            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20">Konfirmasi Aktivasi</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Terminasi Sewa -->
            <div x-show="terminateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
                <div x-show="terminateModal" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="terminateModal = false"></div>
                <div class="flex min-h-full items-center justify-center p-4">
                    <form method="POST" action="{{ route('mall.leases.terminate', $lease) }}" x-show="terminateModal" x-transition class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-100 space-y-4">
                        @csrf
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="font-bold text-base text-rose-900">Terminasi Kontrak Sewa</h3>
                            <button type="button" @click="terminateModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Potongan Tunggakan / Kerusakan (IDR)</label>
                            <input type="number" name="outstanding_deductions" value="0" min="0" max="{{ $lease->security_deposit_amount }}" class="w-full rounded-xl border-slate-200 text-xs focus:ring-rose-500 focus:border-rose-500" placeholder="0">
                            <span class="text-[11px] text-slate-400 mt-1 block">Maksimal: Rp {{ number_format($lease->security_deposit_amount, 0, ',', '.') }}. Sisanya akan direfund ke dompet tenant.</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Alasan Terminasi *</label>
                            <input type="text" name="reason" value="Kontrak sewa berakhir sesuai tanggal" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-rose-500 focus:border-rose-500">
                        </div>
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="terminateModal = false" class="px-4 py-2 border rounded-xl text-xs font-semibold text-slate-600">Batal</button>
                            <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-md shadow-rose-500/20">Terminasi Sekarang</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Perpanjangan (Renew) -->
            <div x-show="renewModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
                <div x-show="renewModal" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="renewModal = false"></div>
                <div class="flex min-h-full items-center justify-center p-4">
                    <form method="POST" action="{{ route('mall.leases.renew', $lease) }}" x-show="renewModal" x-transition class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-100 space-y-4">
                        @csrf
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="font-bold text-base text-blue-900">Perpanjangan Kontrak (Renewal)</h3>
                            <button type="button" @click="renewModal = false" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Berakhir Kontrak Baru *</label>
                            <input type="date" name="new_end_date" value="{{ date('Y-m-d', strtotime($lease->end_date->toDateString().' +1 year')) }}" required class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Eskalasi Tahunan (%) (Opsional)</label>
                            <input type="number" step="0.1" name="annual_escalation_percent" value="{{ $lease->annual_escalation_percent }}" class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="renewModal = false" class="px-4 py-2 border rounded-xl text-xs font-semibold text-slate-600">Batal</button>
                            <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20">Buat Perpanjangan</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

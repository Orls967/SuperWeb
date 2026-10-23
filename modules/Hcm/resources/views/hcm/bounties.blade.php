<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Internal Gig Economy &amp; Talent Bounty — Fase 85</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))<div class="rounded-md bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
            @if (session('info'))<div class="rounded-md bg-blue-50 p-4 text-blue-800">{{ session('info') }}</div>@endif
            @if ($errors->any())<div class="rounded-md bg-red-50 p-4 text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="rounded-lg bg-teal-50 px-4 py-3 text-sm text-teal-800">
                <strong>Fase 85:</strong> Internal Gig Economy &amp; Talent Marketplace: pertukaran shift lintas unit bisnis (Resto, Gudang, Mall, Cuci Armada), matching patuh UU Ketenagakerjaan (maks 8 jam/hari &amp; 14 jam lembur/minggu), izin kerja berisiko K3 &amp; asuransi tersemat, upah lembur 1.5x via Core Banking ledger double-entry, serta insentif poin mobilitas internal.
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-6 text-center">
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Bounty Terbuka</div>
                    <div class="text-xl font-bold text-teal-600">{{ number_format($summary['open_bounties'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Sedang Ditugaskan</div>
                    <div class="text-xl font-bold text-amber-600">{{ number_format($summary['claimed_bounties'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Selesai Dibayar</div>
                    <div class="text-xl font-bold text-emerald-600">{{ number_format($summary['completed_bounties'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Total Upah Gig Dibayar</div>
                    <div class="text-xl font-bold text-purple-600">Rp {{ number_format($summary['total_payout_idr'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Poin Mobilitas Talenta</div>
                    <div class="text-xl font-bold text-blue-600">+{{ number_format($summary['total_mobility_points'] ?? 0) }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="text-xs text-gray-500">Skor Rating Unit</div>
                    <div class="text-xl font-bold text-indigo-600">★ {{ number_format((float) ($summary['average_unit_rating'] ?? 5.0), 2) }}</div>
                </div>
            </div>

            <!-- Interactive Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Post Bounty -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-md font-semibold text-gray-800">85.1 Pasang Bounty Shift Baru</h3>
                    <form method="POST" action="{{ route('hcm.bounties.store') }}" class="mt-3 space-y-3 text-sm">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600">Unit Bisnis / Cost Center</label>
                                <select name="business_unit_cost_center" required class="w-full rounded border-gray-300 text-sm">
                                    <option value="WMS_CIKARANG_01">WMS Cikarang (Bongkar Muat)</option>
                                    <option value="RESTO_CK01">Resto Central Kitchen (Prep Cook)</option>
                                    <option value="MALL_DUTA_EVENT">Mall Duta (Event Setup &amp; Crowd)</option>
                                    <option value="LOGISTICS_FLEET">Armada Fleet (Cuci &amp; Inspeksi)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Judul Penugasan</label>
                                <input name="title" value="Bongkar Muat Kontainer Ekstra" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-600">Deskripsi Pekerjaan</label>
                            <textarea name="description" rows="2" required class="w-full rounded border-gray-300 text-sm">Bantuan bongkar 40ft reefer container cold storage dan penataan palet ke staging area gudang.</textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600">Mulai Shift</label>
                                <input type="datetime-local" name="shift_start" value="{{ now()->addHours(2)->format('Y-m-d\TH:i') }}" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Selesai Shift</label>
                                <input type="datetime-local" name="shift_end" value="{{ now()->addHours(6)->format('Y-m-d\TH:i') }}" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600">Tarif Pokok (IDR/Jam)</label>
                                <input type="number" name="hourly_rate_idr" value="75000" min="1000" required class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Bonus Kecepatan (IDR)</label>
                                <input type="number" name="speed_bonus_idr" value="50000" min="0" class="w-full rounded border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600">Sertifikasi Wajib</label>
                                <select name="required_certification" class="w-full rounded border-gray-300 text-sm">
                                    <option value="">Tanpa Sertifikat</option>
                                    <option value="K3_LOGISTICS">K3 Logistics &amp; Warehousing</option>
                                    <option value="FOOD_SAFETY_HACCP">HACCP Food Safety</option>
                                    <option value="FORKLIFT_OPERATOR">Operator Forklift Kelas II</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <input type="checkbox" id="requires_high_risk_permit" name="requires_high_risk_permit" value="1" class="rounded border-gray-300">
                            <label for="requires_high_risk_permit" class="text-xs text-gray-700">Wajib Izin Kerja Berisiko Tinggi (Confined space / cold storage ekstrem)</label>
                        </div>

                        <button type="submit" class="w-full rounded-md bg-teal-600 px-4 py-2 text-white font-medium hover:bg-teal-700">
                            Publikasikan Bounty ke Talent Marketplace
                        </button>
                    </form>
                </section>

                <!-- Claim / Approval Actions Information -->
                <section class="rounded-lg bg-white p-6 shadow space-y-4">
                    <h3 class="text-md font-semibold text-gray-800">85.2 &amp; 85.3 Mekanisme Pertukaran &amp; Pembayaran</h3>
                    <div class="space-y-3 text-sm text-gray-600">
                        <div class="p-3 bg-gray-50 rounded border border-gray-200">
                            <span class="font-semibold text-gray-800">1. Matching &amp; Guardrail UU 22/2009:</span>
                            <p class="text-xs mt-1">Sistem otomatis menolak klaim jika total jam kerja hari itu melampaui 8.0 jam, lembur mingguan melampaui 14.0 jam, atau jadwal bertabrakan dengan shift primer aktif.</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded border border-gray-200">
                            <span class="font-semibold text-gray-800">2. Asuransi Kecelakaan Kerja Tersemat:</span>
                            <p class="text-xs mt-1">Setiap bounty yang dipublikasikan langsung menerbitkan nomor polis perlindungan mikro secara otomatis untuk menjamin keselamatan talenta lintas unit.</p>
                        </div>
                        <div class="p-3 bg-gray-50 rounded border border-gray-200">
                            <span class="font-semibold text-gray-800">3. Proof of Work (POF) &amp; Payout 1.5x:</span>
                            <p class="text-xs mt-1">Penyelia memverifikasi hash lokasi geo dan foto bukti. Payout upah lembur 1.5x + bonus kecepatan diposting real-time ke akun dompet karyawan dan dibebankan ke cost center pemesan.</p>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Open Bounties List -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-md font-semibold text-gray-800">Daftar Bounty Aktif di Talent Marketplace</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Kode Bounty</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Unit Pemesan</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Judul &amp; Durasi</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Tarif / Jam</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Kepatuhan K3 / Izin</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Status</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($bounties as $bnt)
                                <tr>
                                    <td class="px-3 py-2 font-mono font-bold text-teal-700">{{ $bnt->bounty_code }}</td>
                                    <td class="px-3 py-2">{{ $bnt->business_unit_cost_center }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-gray-800">{{ $bnt->title }}</div>
                                        <div class="text-gray-500">{{ $bnt->duration_hours }} jam ({{ $bnt->shift_start?->format('d M H:i') }} - {{ $bnt->shift_end?->format('H:i') }})</div>
                                    </td>
                                    <td class="px-3 py-2">Rp {{ number_format($bnt->hourly_rate_idr) }}</td>
                                    <td class="px-3 py-2">
                                        @if ($bnt->required_certification)
                                            <span class="inline-block px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 font-mono">{{ $bnt->required_certification }}</span>
                                        @endif
                                        @if ($bnt->requires_high_risk_permit)
                                            <span class="inline-block px-1.5 py-0.5 rounded bg-red-100 text-red-800 font-mono">Wajib Izin K3</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold
                                            {{ $bnt->status === 'OPEN' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                            {{ $bnt->status === 'CLAIMED' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $bnt->status === 'COMPLETED' ? 'bg-purple-100 text-purple-800' : '' }}
                                        ">{{ $bnt->status }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($bnt->status === 'OPEN')
                                            <form method="POST" action="{{ route('hcm.bounties.claim', $bnt) }}" class="inline-flex space-x-1">
                                                @csrf
                                                <input type="hidden" name="employee_id" value="101">
                                                <input type="hidden" name="certifications" value="{{ $bnt->required_certification }}">
                                                @if ($bnt->requires_high_risk_permit)
                                                    <input type="hidden" name="work_permit_code" value="PERMIT-K3-DEMO-99">
                                                @endif
                                                <button type="submit" class="rounded bg-teal-600 px-2.5 py-1 text-white hover:bg-teal-700">
                                                    Klaim Shift
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-4 text-center text-gray-500">Belum ada bounty shift yang terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Active Claims & Supervisor Approval -->
            <section class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-md font-semibold text-gray-800">Klaim Shift &amp; Verifikasi Proof of Work (Penyelia)</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Kode Klaim</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Bounty</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">ID Karyawan</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Waktu Klaim</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Poin Mobilitas</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Status</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Approval POF</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($claims as $clm)
                                <tr>
                                    <td class="px-3 py-2 font-mono font-bold text-gray-700">{{ $clm->claim_code }}</td>
                                    <td class="px-3 py-2">{{ $clm->bounty?->title }} ({{ $clm->bounty?->bounty_code }})</td>
                                    <td class="px-3 py-2 font-mono">EMP-{{ $clm->employee_id }}</td>
                                    <td class="px-3 py-2">{{ $clm->claimed_at?->format('d M H:i') }}</td>
                                    <td class="px-3 py-2 text-blue-600 font-bold">+{{ $clm->mobility_points_earned }}</td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold
                                            {{ $clm->status === 'ASSIGNED' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $clm->status === 'PAID' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                        ">{{ $clm->status }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($clm->status === 'ASSIGNED')
                                            <form method="POST" action="{{ route('hcm.bounties.payout', $clm) }}" class="inline-flex space-x-1">
                                                @csrf
                                                <input type="hidden" name="actual_hours_worked" value="{{ $clm->bounty?->duration_hours ?? 4.0 }}">
                                                <input type="hidden" name="geo_location_hash" value="GEO-WH-GPS-{{ $clm->id }}">
                                                <button type="submit" class="rounded bg-emerald-600 px-2.5 py-1 text-white hover:bg-emerald-700">
                                                    Setujui &amp; Bayar 1.5x
                                                </button>
                                            </form>
                                        @elseif ($clm->pof)
                                            <span class="text-purple-700 font-mono font-semibold">Rp {{ number_format($clm->pof->calculated_overtime_pay_idr) }} ({{ $clm->pof->pof_code }})</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-4 text-center text-gray-500">Belum ada klaim shift gig aktif.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

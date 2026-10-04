@extends('layouts.app')

@section('title', $party->name . ' — 360° Profile')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
    {{-- Header & Breadcrumb --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('party.index') }}" class="text-slate-400 hover:text-slate-200 text-sm transition">← Party Directory</a>
                <span class="text-slate-600">/</span>
                <span class="text-slate-400 text-sm">{{ $party->short_name ?? substr($party->id, 0, 8) }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-white">{{ $party->name }}</h1>
                @if($party->short_name)
                    <span class="text-slate-400 text-lg font-normal">({{ $party->short_name }})</span>
                @endif
                <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider
                    @if($party->status->value === 'verified') bg-emerald-500/20 text-emerald-300 border border-emerald-500/30
                    @elseif($party->status->value === 'blacklisted') bg-rose-500/20 text-rose-300 border border-rose-500/30
                    @elseif($party->status->value === 'suspended') bg-amber-500/20 text-amber-300 border border-amber-500/30
                    @else bg-slate-700 text-slate-300 @endif">
                    {{ ucfirst($party->status->value) }}
                </span>
            </div>
            <p class="text-slate-400 text-xs mt-1">UUID: {{ $party->id }} | Tipe: {{ ucfirst($party->type->value) }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <form action="{{ route('party.screen', $party) }}" method="POST">
                @csrf
                <button type="submit" id="btn-screen-sanctions" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm font-medium transition shadow-sm">
                    🛡️ Scan Sanctions
                </button>
            </form>
            <form action="{{ route('party.rescore', $party) }}" method="POST">
                @csrf
                <button type="submit" id="btn-rescore-credit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition shadow-sm">
                    📊 Hitung Skor Kredit
                </button>
            </form>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="p-4 bg-emerald-500/20 border border-emerald-500/40 rounded-xl text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-rose-500/20 border border-rose-500/40 rounded-xl text-rose-300 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Top Overview Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        {{-- Legal Entity --}}
        <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-4">
            <span class="text-xs text-slate-400 font-medium uppercase tracking-wider block mb-1">Badan Hukum / Entitas</span>
            <div class="text-base font-semibold text-white">
                {{ $party->legalEntity?->name ?? 'Independen / Eksternal' }}
            </div>
            <div class="text-xs text-slate-500 mt-1">
                {{ $party->legalEntity ? strtoupper($party->legalEntity->entity_type) : 'Non-internal' }}
            </div>
        </div>

        {{-- KYC & Risk Tier --}}
        <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-4">
            <span class="text-xs text-slate-400 font-medium uppercase tracking-wider block mb-1">Status KYB / Risiko</span>
            <div class="text-base font-semibold text-white flex items-center gap-2">
                <span>{{ ucfirst($party->kyb_status->value) }}</span>
            </div>
            <div class="text-xs text-slate-400 mt-1">
                Risk Tier: <span class="font-semibold text-slate-200">{{ ucfirst($party->creditProfile?->risk_tier?->value ?? 'medium') }}</span>
            </div>
        </div>

        {{-- Credit Profile --}}
        <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-4">
            <span class="text-xs text-slate-400 font-medium uppercase tracking-wider block mb-1">Limit & Exposure Kredit</span>
            <div class="text-base font-semibold text-white">
                Rp {{ number_format($party->creditProfile?->credit_limit_idr ?? 0, 0, ',', '.') }}
            </div>
            <div class="text-xs text-slate-400 mt-1">
                Terpakai: Rp {{ number_format($party->creditProfile?->current_exposure_idr ?? 0, 0, ',', '.') }}
                ({{ $party->creditProfile?->utilizationPercent() ?? 0 }}%)
            </div>
        </div>

        {{-- Sanction Status --}}
        <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-4">
            <span class="text-xs text-slate-400 font-medium uppercase tracking-wider block mb-1">Sanctions Screening</span>
            @php $latestCheck = $party->latestSanctionCheck(); @endphp
            @if($latestCheck)
                <div class="text-base font-semibold {{ $latestCheck->status->value === 'clear' ? 'text-emerald-400' : ($latestCheck->status->value === 'hit' ? 'text-rose-400' : 'text-amber-400') }}">
                    {{ ucfirst(str_replace('_', ' ', $latestCheck->status->value)) }}
                </div>
                <div class="text-xs text-slate-500 mt-1">
                    {{ $latestCheck->created_at?->diffForHumans() ?? 'Belum dicek' }}
                </div>
            @else
                <div class="text-base font-semibold text-slate-400">Belum Diskrining</div>
                <div class="text-xs text-slate-500 mt-1">Klik Scan Sanctions</div>
            @endif
        </div>
    </div>

    {{-- Main Content 2 Columns --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column (2 spans): Identitas, Roles, KYC --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Identitas Detail --}}
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5">
                <h2 class="text-base font-semibold text-white mb-4">Informasi Legal & Pajak</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-xs text-slate-400 block mb-0.5">NPWP Terdaftar</span>
                        <span class="font-mono text-slate-200">{{ $party->npwp_masked ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block mb-0.5">NIB</span>
                        <span class="font-mono text-slate-200">{{ $party->nib ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-slate-400 block mb-0.5">NIK (KTP)</span>
                        <span class="font-mono text-slate-200">{{ $party->nik_masked ?? '—' }}</span>
                    </div>
                </div>
            </div>

            {{-- Roles Matrix --}}
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5">
                <h2 class="text-base font-semibold text-white mb-3">Peran Bisnis (Roles)</h2>
                <div class="flex flex-wrap gap-2">
                    @forelse($party->roles as $role)
                        <span class="px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs font-medium text-slate-300 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $role->is_active ? 'bg-emerald-400' : 'bg-slate-500' }}"></span>
                            {{ ucfirst(str_replace('_', ' ', $role->role)) }}
                        </span>
                    @empty
                        <span class="text-slate-500 text-sm">Belum ada role yang ditetapkan.</span>
                    @endforelse
                </div>
            </div>

            {{-- KYC Documents & Verification Workflow --}}
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-white">Dokumen KYC / Legalitas</h2>
                        <p class="text-xs text-slate-400">Verifikasi berkas legalitas dan kepatuhan</p>
                    </div>
                </div>

                {{-- Document List Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-slate-700 text-slate-400 uppercase text-left">
                                <th class="pb-2">Tipe Dokumen</th>
                                <th class="pb-2">Nomor</th>
                                <th class="pb-2">Penerbit</th>
                                <th class="pb-2">Masa Berlaku</th>
                                <th class="pb-2">Status</th>
                                <th class="pb-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            @forelse($party->kycDocuments as $doc)
                                <tr>
                                    <td class="py-2.5 font-medium text-white">{{ strtoupper(str_replace('_', ' ', $doc->document_type->value)) }}</td>
                                    <td class="py-2.5 font-mono text-slate-300">{{ $doc->document_number ?? '—' }}</td>
                                    <td class="py-2.5 text-slate-300">{{ $doc->issuer ?? '—' }}</td>
                                    <td class="py-2.5 text-slate-300">
                                        @if($doc->expires_at)
                                            <span class="{{ $doc->isExpired() ? 'text-rose-400 font-semibold' : ($doc->expiresWithinDays(30) ? 'text-amber-400 font-medium' : '') }}">
                                                {{ $doc->expires_at->format('d M Y') }}
                                            </span>
                                        @else
                                            <span class="text-slate-500">Permanen</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase
                                            @if($doc->status->value === 'approved') bg-emerald-500/20 text-emerald-300
                                            @elseif($doc->status->value === 'rejected') bg-rose-500/20 text-rose-300
                                            @elseif($doc->status->value === 'expired') bg-slate-700 text-slate-400
                                            @else bg-amber-500/20 text-amber-300 @endif">
                                            {{ ucfirst($doc->status->value) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-right space-x-1">
                                        @if($doc->status->value === 'pending')
                                            <form action="{{ route('party.kyc.approve', [$party, $doc]) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-[10px] transition">
                                                    Setujui
                                                </button>
                                            </form>
                                            <form action="{{ route('party.kyc.reject', [$party, $doc]) }}" method="POST" class="inline" onsubmit="return confirm('Tolak dokumen ini?')">
                                                @csrf
                                                <input type="hidden" name="reason" value="Dokumen tidak sesuai / tidak valid">
                                                <button type="submit" class="px-2 py-1 bg-rose-600 hover:bg-rose-500 text-white rounded text-[10px] transition">
                                                    Tolak
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-center text-slate-500">Belum ada dokumen KYC yang diunggah.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Form Unggah KYC Cepat --}}
                <div class="pt-4 border-t border-slate-700/50">
                    <h3 class="text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Unggah / Catat Dokumen KYC Baru</h3>
                    <form action="{{ route('party.kyc.submit', $party) }}" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-2">
                        @csrf
                        <select name="document_type" required class="px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-xs text-white">
                            <option value="ktp">KTP</option>
                            <option value="npwp">NPWP</option>
                            <option value="nib">NIB</option>
                            <option value="siup">SIUP</option>
                            <option value="akta">Akta Pendirian</option>
                            <option value="sertifikat_halal">Sertifikat Halal</option>
                            <option value="iso">ISO</option>
                        </select>
                        <input type="text" name="document_number" placeholder="Nomor Dokumen" class="px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-xs text-white">
                        <input type="text" name="issuer" placeholder="Instansi Penerbit" class="px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-xs text-white">
                        <input type="date" name="expires_at" class="px-2 py-1.5 bg-slate-900 border border-slate-700 rounded text-xs text-white">
                        <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs font-medium transition">
                            + Submit KYC
                        </button>
                    </form>
                </div>
            </div>

            {{-- Riwayat Sanctions Checks --}}
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5">
                <h2 class="text-base font-semibold text-white mb-3">Riwayat Sanctions & AML Screening</h2>
                <div class="space-y-2">
                    @forelse($party->sanctionsChecks->sortByDesc('created_at')->take(5) as $sc)
                        <div class="p-3 bg-slate-900/60 border border-slate-700/40 rounded-lg flex items-center justify-between text-xs">
                            <div>
                                <span class="font-medium text-slate-200">Konteks: {{ ucfirst($sc->trigger) }}</span>
                                <span class="text-slate-500 block">{{ $sc->created_at?->format('d M Y H:i') }} | Match Score: {{ $sc->match_score }}%</span>
                            </div>
                            <span class="px-2 py-1 rounded text-[10px] font-semibold uppercase
                                @if($sc->status->value === 'clear') bg-emerald-500/20 text-emerald-300
                                @elseif($sc->status->value === 'hit') bg-rose-500/20 text-rose-300
                                @else bg-amber-500/20 text-amber-300 @endif">
                                {{ ucfirst(str_replace('_', ' ', $sc->status->value)) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-slate-500 text-xs">Belum ada riwayat screening.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right Column (1 span): Alamat, Kontak, Bank, Hubungan Ekosistem --}}
        <div class="space-y-6">
            {{-- Credit Score Card --}}
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5">
                <h2 class="text-base font-semibold text-white mb-2">Profil Kredit</h2>
                <div class="flex items-center justify-between py-2 border-b border-slate-700/40">
                    <span class="text-xs text-slate-400">Skor Kelayakan</span>
                    <span class="text-xl font-bold {{ ($party->creditProfile?->score ?? 0) >= 70 ? 'text-emerald-400' : 'text-amber-400' }}">
                        {{ $party->creditProfile?->score ?? '—' }} / 100
                    </span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-700/40 text-xs">
                    <span class="text-slate-400">Limit Tersedia</span>
                    <span class="font-mono text-white">Rp {{ number_format($party->creditProfile?->availableCredit() ?? 0, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between py-2 text-xs">
                    <span class="text-slate-400">Term Pembayaran</span>
                    <span class="text-slate-200">{{ $party->creditProfile?->payment_terms_days ?? 30 }} Hari (NET {{ $party->creditProfile?->payment_terms_days ?? 30 }})</span>
                </div>
            </div>

            {{-- Kontak --}}
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5">
                <h2 class="text-base font-semibold text-white mb-3">Kontak Narahubung</h2>
                <div class="space-y-2">
                    @forelse($party->contacts as $contact)
                        <div class="p-2.5 bg-slate-900/60 border border-slate-700/40 rounded-lg text-xs">
                            <div class="font-semibold text-white">{{ $contact->name }} <span class="text-slate-400 font-normal">({{ $contact->title ?? 'PIC' }})</span></div>
                            <div class="text-slate-400 mt-0.5">{{ $contact->email ?? '—' }} | {{ $contact->phone ?? '—' }}</div>
                        </div>
                    @empty
                        <p class="text-slate-500 text-xs">Belum ada data kontak terdaftar.</p>
                    @endforelse
                </div>
            </div>

            {{-- Rekening Bank --}}
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5">
                <h2 class="text-base font-semibold text-white mb-3">Rekening Bank Terdaftar</h2>
                <div class="space-y-2">
                    @forelse($party->bankAccounts as $bank)
                        <div class="p-2.5 bg-slate-900/60 border border-slate-700/40 rounded-lg text-xs">
                            <div class="font-semibold text-white">{{ $bank->bank_name }} <span class="text-slate-400 font-mono">({{ $bank->account_number_masked }})</span></div>
                            <div class="text-slate-400 mt-0.5">a.n. {{ $bank->account_holder_name }}</div>
                        </div>
                    @empty
                        <p class="text-slate-500 text-xs">Belum ada rekening bank terdaftar.</p>
                    @endforelse
                </div>
            </div>

            {{-- Alamat --}}
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5">
                <h2 class="text-base font-semibold text-white mb-3">Alamat</h2>
                <div class="space-y-2">
                    @forelse($party->addresses as $addr)
                        <div class="p-2.5 bg-slate-900/60 border border-slate-700/40 rounded-lg text-xs">
                            <span class="text-[10px] uppercase font-bold text-indigo-400 block mb-1">{{ $addr->label }}</span>
                            <div class="text-slate-200">{{ $addr->line1 }}</div>
                            <div class="text-slate-400">{{ $addr->city }}, {{ $addr->province }} {{ $addr->postal_code }}</div>
                        </div>
                    @empty
                        <p class="text-slate-500 text-xs">Belum ada alamat terdaftar.</p>
                    @endforelse
                </div>
            </div>

            {{-- Integrasi Ekosistem AutoServe --}}
            <div class="bg-slate-800/60 backdrop-blur border border-slate-700/50 rounded-xl p-5">
                <h2 class="text-base font-semibold text-white mb-2">Tautan Modul Ekosistem</h2>
                <p class="text-xs text-slate-400 mb-3">Identitas party tersambung ke seluruh modul operasional</p>
                <div class="space-y-1.5 text-xs">
                    <div class="p-2 bg-slate-900/40 rounded border border-slate-700/30 flex justify-between items-center text-slate-300">
                        <span>Logistics Carrier & Shipper</span>
                        <span class="text-emerald-400 font-semibold">Tersambung</span>
                    </div>
                    <div class="p-2 bg-slate-900/40 rounded border border-slate-700/30 flex justify-between items-center text-slate-300">
                        <span>Mall Tenant Master</span>
                        <span class="text-emerald-400 font-semibold">Tersambung</span>
                    </div>
                    <div class="p-2 bg-slate-900/40 rounded border border-slate-700/30 flex justify-between items-center text-slate-300">
                        <span>Resto Supplier Network</span>
                        <span class="text-emerald-400 font-semibold">Tersambung</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

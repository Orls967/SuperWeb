@extends('layouts.app')

@section('title', 'Enterprise Mobile Suite & Offline-First')
@section('subtitle', 'Pusat Tata Kelola PWA, Sinkronisasi Idempoten, dan Verifikasi Bukti Digital e-POD (Fase 65)')

@section('content')
<div class="space-y-8" x-data="{ activeTab: 'overview', signatureData: '' }">

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center justify-between backdrop-blur-xl">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="font-bold text-emerald-400 hover:text-emerald-300">&times;</button>
        </div>
    @endif

    {{-- Top KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Perangkat Terdaftar</p>
                <p class="text-2xl font-bold text-slate-100">{{ number_format($stats['total_devices']) }}</p>
                <p class="text-xs text-emerald-400 mt-0.5">{{ $stats['active_devices'] }} Aktif, {{ $stats['total_devices'] - $stats['active_devices'] }} Dicabut</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Event Mutasi Offline</p>
                <p class="text-2xl font-bold text-slate-100">{{ number_format($stats['total_sync_events']) }}</p>
                <p class="text-xs text-cyan-400 mt-0.5">{{ $stats['duplicates_prevented'] }} Duplikasi Dicegah (Idempoten)</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Bukti Digital e-POD</p>
                <p class="text-2xl font-bold text-slate-100">{{ number_format($stats['total_epod']) }}</p>
                <p class="text-xs text-emerald-400 mt-0.5">SHA-256 Valid ({{ $stats['tampered_epod'] }} Tampered)</p>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Tindakan 4 Peran</p>
                <p class="text-2xl font-bold text-slate-100">{{ number_format($stats['field_actions']) }}</p>
                <p class="text-xs text-slate-400 mt-0.5">Operator, WMS, Driver, Agen</p>
            </div>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <div class="border-b border-slate-700/60 flex gap-2">
        <button type="button" @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'border-indigo-500 text-indigo-400 bg-indigo-500/10' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 font-semibold text-sm border-b-2 rounded-t-xl transition-all">
            Perangkat & Ringkasan
        </button>
        <button type="button" @click="activeTab = 'sync'" :class="activeTab === 'sync' ? 'border-indigo-500 text-indigo-400 bg-indigo-500/10' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 font-semibold text-sm border-b-2 rounded-t-xl transition-all">
            Simulasi Offline Sync (Idempotensi)
        </button>
        <button type="button" @click="activeTab = 'epod'" :class="activeTab === 'epod' ? 'border-indigo-500 text-indigo-400 bg-indigo-500/10' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 font-semibold text-sm border-b-2 rounded-t-xl transition-all">
            Driver e-POD (Tanda Tangan Digital)
        </button>
        <button type="button" @click="activeTab = 'roles'" :class="activeTab === 'roles' ? 'border-indigo-500 text-indigo-400 bg-indigo-500/10' : 'border-transparent text-slate-400 hover:text-slate-200'" class="px-5 py-3 font-semibold text-sm border-b-2 rounded-t-xl transition-all">
            Aksi Lapangan 4 Peran
        </button>
    </div>

    {{-- TAB 1: Overview & Device Registry --}}
    <div x-show="activeTab === 'overview'" class="space-y-6">
        <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
            <h3 class="text-lg font-bold text-slate-100 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Daftar Perangkat Mobile Lapangan Terdaftar
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="py-3 px-4">Device UUID</th>
                            <th class="py-3 px-4">Role Scope</th>
                            <th class="py-3 px-4">Platform</th>
                            <th class="py-3 px-4">Versi App</th>
                            <th class="py-3 px-4">Terakhir Aktif</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        @forelse($devices as $dev)
                            <tr class="hover:bg-slate-700/20">
                                <td class="py-3 px-4 font-mono text-xs text-indigo-300">{{ $dev->device_uuid }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300">
                                        {{ $dev->role_scope }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">{{ $dev->platform }}</td>
                                <td class="py-3 px-4">{{ $dev->app_version }}</td>
                                <td class="py-3 px-4 text-xs">{{ $dev->last_seen_at ? $dev->last_seen_at->diffForHumans() : 'Belum pernah' }}</td>
                                <td class="py-3 px-4">
                                    @if($dev->is_revoked)
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/20 text-rose-400">REVOKED</span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-400">ACTIVE</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right">
                                    @if(! $dev->is_revoked)
                                        <form method="POST" action="{{ route('admin.mobile.revoke-device', $dev) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1 rounded-lg text-xs font-semibold bg-rose-500/20 text-rose-300 hover:bg-rose-500/30 transition">
                                                Cabut Akses
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-500">Tercabut</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-slate-400">Belum ada perangkat terdaftar. Gunakan tab simulasi sync untuk mendaftarkan otomatis.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TAB 2: Offline Queue Sync Simulator --}}
    <div x-show="activeTab === 'sync'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-lg font-bold text-slate-100 mb-2">Simulasikan Transmisi Antrean Offline</h3>
                <p class="text-xs text-slate-400 mb-5">Simulasikan saat perangkat kembali mendapatkan koneksi internet dan mengirimkan batch mutasi lokal dengan Idempotency-Key.</p>

                <form method="POST" action="{{ route('admin.mobile.sync-batch') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Device UUID</label>
                        <input type="text" name="device_uuid" value="DEV-OPERATOR-PABRIK-01" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 font-mono">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Role Scope</label>
                            <select name="role_scope" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                <option value="operator">Operator Pabrik</option>
                                <option value="hub_operator">Petugas WMS</option>
                                <option value="driver">Driver Logistik</option>
                                <option value="agent">Sales Agen Lapangan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Tipe Event</label>
                            <select name="event_type" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                <option value="MFG_OUTPUT_RECORDED">MFG_OUTPUT_RECORDED</option>
                                <option value="MFG_DOWNTIME_LOGGED">MFG_DOWNTIME_LOGGED</option>
                                <option value="WMS_PUTAWAY_CONFIRMED">WMS_PUTAWAY_CONFIRMED</option>
                                <option value="WMS_PICK_WAVE_CONFIRMED">WMS_PICK_WAVE_CONFIRMED</option>
                                <option value="AGY_OFFLINE_ORDER_CREATED">AGY_OFFLINE_ORDER_CREATED</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Reference ID</label>
                            <input type="text" name="reference_id" value="REF-BATCH-{{ date('His') }}" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Petugas</label>
                            <input type="text" name="operator_name" value="Budi Operator Lapangan" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Detail Payload (JSON String)</label>
                        <textarea name="details" rows="3" class="w-full px-3.5 py-2 rounded-xl bg-slate-900/60 border border-slate-700 text-xs text-slate-100 focus:outline-none focus:border-indigo-500 font-mono">{"units": 450, "quality_score": 98.5, "notes": "Produksi shift 1 selesai lancar"}</textarea>
                    </div>
                    <button type="submit" class="w-full py-3 rounded-xl font-bold text-sm bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white shadow-lg shadow-indigo-500/25 transition">
                        Kirim Mutasi Offline (Idempotent Sync)
                    </button>
                </form>
            </div>

            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-lg font-bold text-slate-100 mb-4">Log Sinkronisasi Terbaru</h3>
                <div class="space-y-3 max-h-[440px] overflow-y-auto pr-1">
                    @forelse($recentSyncs as $sync)
                        <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-700/50 flex flex-col gap-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-mono text-indigo-300 font-semibold truncate max-w-[200px]">{{ $sync->idempotency_key }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $sync->conflict_status === 'SKIPPED_DUPLICATE' ? 'bg-cyan-500/20 text-cyan-400' : ($sync->conflict_status === 'RESOLVED_LWW' ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400') }}">
                                    {{ $sync->conflict_status }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-slate-400">
                                <span>{{ $sync->event_type }} ({{ $sync->role_scope }})</span>
                                <span>{{ $sync->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 text-center py-6">Belum ada mutasi sinkronisasi tercatat.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 3: Driver e-POD (Tanda Tangan Digital) --}}
    <div x-show="activeTab === 'epod'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-lg font-bold text-slate-100 mb-2">Simulasi e-POD Serah Terima Barang</h3>
                <p class="text-xs text-slate-400 mb-5">Pengemudi mengambil tanda tangan digital penerima barang secara offline. Server akan menghitung hash SHA-256 untuk memvalidasi keaslian.</p>

                <form method="POST" action="{{ route('admin.mobile.submit-epod') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nomor Pengiriman (Shipment Ref)</label>
                            <input type="text" name="shipment_ref" value="SHP-JKT-LOG-{{ date('His') }}" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">ID Pengemudi (Driver Ref)</label>
                            <input type="text" name="driver_ref" value="DRV-ARIFIN-09" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Penerima</label>
                            <input type="text" name="recipient_name" value="Dewi Lestari" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Jabatan Penerima</label>
                            <input type="text" name="recipient_role" value="Supervisor Gudang Tujuan" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tanda Tangan Digital (Base64 SVG / Vector)</label>
                        <input type="text" name="signature_payload_b64" value="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIj48cGF0aCBkPSJNMTAgMTBDMjAgNDAgNDAgODAgODAgODAiIHN0cm9rZT0iYmxhY2siIGZpbGw9Im5vbmUiLz48L3N2Zz4=" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-xs text-slate-100 focus:outline-none focus:border-indigo-500 font-mono">
                    </div>
                    <button type="submit" class="w-full py-3 rounded-xl font-bold text-sm bg-gradient-to-r from-cyan-500 to-cyan-600 hover:from-cyan-600 hover:to-cyan-700 text-white shadow-lg shadow-cyan-500/25 transition">
                        Submit e-POD & Hitung Hash SHA-256
                    </button>
                </form>
            </div>

            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-lg font-bold text-slate-100 mb-4">Arsip Bukti Digital e-POD</h3>
                <div class="space-y-3 max-h-[440px] overflow-y-auto pr-1">
                    @forelse($recentEpods as $epod)
                        <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-700/50 flex flex-col gap-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-100">{{ $epod->epod_code }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $epod->is_tampered ? 'bg-rose-500/20 text-rose-400' : 'bg-emerald-500/20 text-emerald-400' }}">
                                    {{ $epod->is_tampered ? 'TAMPERED' : 'VERIFIED' }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-300">
                                Penerima: <strong>{{ $epod->recipient_name }}</strong> ({{ $epod->recipient_role ?? 'Staf' }})
                            </div>
                            <div class="text-[10px] font-mono text-cyan-300 truncate">
                                SHA-256: {{ $epod->signature_hash }}
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 text-center py-6">Belum ada bukti e-POD yang disubmit.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 4: Aksi Lapangan 4 Peran --}}
    <div x-show="activeTab === 'roles'" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-lg font-bold text-slate-100 mb-2">Pencatatan Tindakan Lapangan</h3>
                <p class="text-xs text-slate-400 mb-5">Simulasi tindakan spesifik untuk peran Operator Pabrik, Petugas WMS, Driver Logistik, atau Sales Agen.</p>

                <form method="POST" action="{{ route('admin.mobile.record-action') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Peran Lapangan</label>
                            <select name="role_scope" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                                <option value="operator">Operator Pabrik</option>
                                <option value="hub_operator">Petugas WMS</option>
                                <option value="driver">Driver Logistik</option>
                                <option value="agent">Sales Agen Lapangan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Jenis Tindakan</label>
                            <input type="text" name="action_type" value="WORK_ORDER_LOG" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Reference ID</label>
                            <input type="text" name="reference_id" value="WO-{{ date('His') }}" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500 font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Petugas</label>
                            <input type="text" name="operator_name" value="Operator Syaifullah" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Catatan Tambahan</label>
                        <input type="text" name="notes" value="Tindakan lapangan tercatat otomatis via mobile app" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900/60 border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-indigo-500">
                    </div>
                    <button type="submit" class="w-full py-3 rounded-xl font-bold text-sm bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white shadow-lg shadow-amber-500/25 transition">
                        Catat Aksi Lapangan
                    </button>
                </form>
            </div>

            <div class="p-6 rounded-2xl bg-slate-800/40 border border-slate-700/50 backdrop-blur-xl">
                <h3 class="text-lg font-bold text-slate-100 mb-4">Riwayat Aksi 4 Peran</h3>
                <div class="space-y-3 max-h-[440px] overflow-y-auto pr-1">
                    @forelse($recentActions as $act)
                        <div class="p-3.5 rounded-xl bg-slate-900/60 border border-slate-700/50 flex flex-col gap-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-mono text-indigo-300 font-semibold">{{ $act->action_code }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-400">
                                    {{ $act->role_scope }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-300">
                                {{ $act->action_type }} — {{ $act->operator_name }} (Ref: {{ $act->reference_id }})
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 text-center py-6">Belum ada aksi lapangan tercatat.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

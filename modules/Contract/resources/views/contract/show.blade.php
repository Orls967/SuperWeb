@extends('layouts.app')

@section('title', $contract->contract_number . ' – Detail Kontrak')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <a href="{{ route('contract.index') }}" class="text-slate-400 hover:text-white text-sm transition">← Daftar Kontrak</a>
            <h1 class="text-xl font-bold text-white mt-1">{{ $contract->contract_number }}</h1>
            <p class="text-slate-300 mt-0.5">{{ $contract->title }}</p>
        </div>
        <div class="flex items-center gap-3">
            @php
                $statusColor = match($contract->status->value) {
                    'draft'       => 'text-slate-300 bg-slate-700',
                    'review'      => 'text-yellow-300 bg-yellow-400/20',
                    'negotiation' => 'text-orange-300 bg-orange-400/20',
                    'approved'    => 'text-blue-300 bg-blue-400/20',
                    'signed'      => 'text-indigo-300 bg-indigo-400/20',
                    'active'      => 'text-emerald-300 bg-emerald-400/20',
                    'suspended'   => 'text-amber-300 bg-amber-400/20',
                    'expired'     => 'text-slate-500 bg-slate-700/30',
                    'terminated'  => 'text-red-300 bg-red-400/20',
                    'renewed'     => 'text-teal-300 bg-teal-400/20',
                    default       => 'text-slate-300 bg-slate-700',
                };
            @endphp
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColor }}">
                {{ $contract->status->label() }}
            </span>
            @if(in_array($contract->status->value, ['draft','negotiation']))
                <a href="{{ route('contract.edit', $contract) }}"
                   class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm transition">
                    ✏️ Edit
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-500/20 border border-emerald-500/40 rounded-lg text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-500/20 border border-red-500/40 rounded-lg text-red-300 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Tabs --}}
    <div x-data="{ tab: 'overview' }" class="space-y-6">
        <div class="flex gap-1 bg-slate-800/50 border border-slate-700 rounded-xl p-1.5">
            @foreach(['overview' => '📋 Overview', 'parties' => '👥 Pihak', 'clauses' => '📄 Klausul', 'milestones' => '🎯 Obligasi', 'finance' => '💰 Keuangan', 'attachments' => '📎 Lampiran', 'versions' => '🔗 Versi'] as $key => $label)
                <button @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'"
                        class="flex-1 px-3 py-2 rounded-lg text-sm font-medium transition">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Overview Tab --}}
        <div x-show="tab === 'overview'" x-transition>
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Entitas Hukum</p>
                    <p class="text-white font-medium mt-1">{{ $contract->legalEntity?->name ?? '—' }}</p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Jenis Kontrak</p>
                    <p class="text-white font-medium mt-1 capitalize">{{ str_replace('_', ' ', $contract->contract_type->value) }}</p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Nilai Kontrak</p>
                    <p class="text-white font-medium mt-1">
                        {{ $contract->currency }} {{ $contract->total_value_idr > 0 ? number_format($contract->total_value_idr) : '—' }}
                    </p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Periode</p>
                    <p class="text-white font-medium mt-1">
                        {{ $contract->start_date?->format('d M Y') ?? '—' }} →
                        {{ $contract->end_date?->format('d M Y') ?? '—' }}
                    </p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Notice Period</p>
                    <p class="text-white font-medium mt-1">{{ $contract->notice_period_days }} hari</p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Auto Renew</p>
                    <p class="text-white font-medium mt-1">
                        {{ $contract->auto_renew ? "Ya ({$contract->renewal_period_months} bulan)" : 'Tidak' }}
                    </p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Governing Law</p>
                    <p class="text-white font-medium mt-1">{{ $contract->governing_law }}</p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Forum Sengketa</p>
                    <p class="text-white font-medium mt-1">{{ $contract->dispute_forum }}</p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Hash Chain</p>
                    <p class="text-indigo-300 font-mono text-xs mt-1 truncate" title="{{ $contract->current_hash }}">
                        {{ substr($contract->current_hash ?? '—', 0, 16) }}...
                    </p>
                </div>
            </div>

            {{-- Isi Kontrak --}}
            @if($contract->current_body)
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-slate-300 mb-3">📄 Isi Kontrak (Versi Terkini)</h3>
                    <div class="prose prose-invert prose-sm max-w-none whitespace-pre-wrap text-slate-300 text-sm leading-relaxed font-mono bg-slate-900/50 rounded-lg p-4 max-h-64 overflow-y-auto">{{ $contract->current_body }}</div>
                </div>
            @endif

            {{-- State Machine Actions --}}
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 mt-4">
                <h3 class="text-sm font-semibold text-slate-300 mb-4">🔄 Transisi Status</h3>
                <div class="flex flex-wrap gap-3">
                    @if($contract->status->value === 'draft' && $contract->parties->count() >= 2)
                        <form method="POST" action="{{ route('contract.submit-approval', $contract) }}" class="inline">
                            @csrf
                            <button type="submit" id="btn-submit-approval"
                                    class="px-4 py-2 bg-yellow-600 hover:bg-yellow-500 text-white rounded-lg text-sm transition">
                                📤 Ajukan Persetujuan
                            </button>
                        </form>
                    @endif

                    @foreach($nextStatus as $s)
                        <form method="POST" action="{{ route('contract.transition', $contract) }}"
                              x-data="{ showReason: {{ in_array($s->value, ['terminated','suspended']) ? 'true' : 'false' }} }"
                              class="inline-block">
                            @csrf
                            <input type="hidden" name="target_status" value="{{ $s->value }}">
                            <div x-show="showReason" class="mb-2">
                                <input name="reason" placeholder="Alasan wajib..." required
                                       class="px-3 py-1.5 bg-slate-900 border border-slate-600 rounded text-white text-sm w-64">
                            </div>
                            @php
                                $btnColor = match($s->value) {
                                    'review'      => 'bg-yellow-600 hover:bg-yellow-500',
                                    'negotiation' => 'bg-orange-600 hover:bg-orange-500',
                                    'approved'    => 'bg-blue-600 hover:bg-blue-500',
                                    'signed'      => 'bg-indigo-600 hover:bg-indigo-500',
                                    'active'      => 'bg-emerald-600 hover:bg-emerald-500',
                                    'suspended'   => 'bg-amber-600 hover:bg-amber-500',
                                    'expired'     => 'bg-slate-600 hover:bg-slate-500',
                                    'terminated'  => 'bg-red-600 hover:bg-red-500',
                                    'renewed'     => 'bg-teal-600 hover:bg-teal-500',
                                    default       => 'bg-slate-600 hover:bg-slate-500',
                                };
                            @endphp
                            <button type="submit" class="px-4 py-2 {{ $btnColor }} text-white rounded-lg text-sm transition">
                                → {{ $s->label() }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Parties Tab --}}
        <div x-show="tab === 'parties'" x-transition>
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden mb-4">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/50 text-slate-400 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Pihak</th>
                            <th class="px-4 py-3 text-left">Peran</th>
                            <th class="px-4 py-3 text-left">Urutan TTD</th>
                            <th class="px-4 py-3 text-left">Status Tanda Tangan</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        @forelse($contract->parties as $cp)
                            <tr class="hover:bg-slate-700/20">
                                <td class="px-4 py-3 text-white">{{ $cp->party?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-300 capitalize">{{ str_replace('_',' ',$cp->role) }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $cp->signing_order }}</td>
                                <td class="px-4 py-3">
                                    @if($cp->is_signed)
                                        <span class="text-emerald-400 text-xs">✅ Ditandatangani</span>
                                        <span class="text-slate-500 text-xs ml-2">oleh {{ $cp->signer_name }}</span>
                                    @else
                                        <span class="text-slate-500 text-xs">⏳ Menunggu</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if(!$cp->is_signed && in_array($contract->status->value, ['approved','signed']))
                                        <form method="POST" action="{{ route('contract.sign-party', [$contract, $cp]) }}"
                                              x-data="{ n:'', t:'' }" class="flex gap-2 items-center">
                                            @csrf
                                            <input x-model="n" name="signer_name" placeholder="Nama Penandatangan" required
                                                   class="px-2 py-1 bg-slate-900 border border-slate-600 rounded text-white text-xs w-40">
                                            <input x-model="t" name="signer_title" placeholder="Jabatan" required
                                                   class="px-2 py-1 bg-slate-900 border border-slate-600 rounded text-white text-xs w-32">
                                            <button type="submit" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-xs transition">
                                                ✍️ TTD
                                            </button>
                                        </form>
                                    @endif
                                    @if($contract->status->value === 'draft')
                                        <form method="POST" action="{{ route('contract.party.remove', [$contract, $cp]) }}" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" onclick="return confirm('Hapus pihak ini?')"
                                                    class="text-red-400 hover:text-red-300 text-xs">Hapus</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500 text-sm">Belum ada pihak.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(in_array($contract->status->value, ['draft','negotiation']))
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-slate-300 mb-3">+ Tambah Pihak</h3>
                    <form method="POST" action="{{ route('contract.party.add', $contract) }}" class="flex gap-3 flex-wrap">
                        @csrf
                        <select name="party_id" required class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm flex-1 min-w-48">
                            <option value="">Pilih pihak...</option>
                            @foreach($allParties as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <select name="role" required class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                            @foreach($roles as $r)
                                <option value="{{ $r->value }}">{{ ucfirst(str_replace('_',' ',$r->value)) }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="signing_order" placeholder="Urutan TTD" min="1" value="{{ $contract->parties->count() + 1 }}"
                               class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm w-28">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition">Tambah</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Clauses Tab --}}
        <div x-show="tab === 'clauses'" x-transition>
            <div class="space-y-3">
                @forelse($contract->clauses as $clause)
                    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs text-slate-500 font-mono">Klausul {{ $clause->display_order }}</span>
                            @if($clause->is_negotiated)
                                <span class="text-xs text-orange-400 bg-orange-400/10 px-2 py-0.5 rounded">Dinegosiasi</span>
                            @endif
                        </div>
                        <h4 class="text-sm font-semibold text-white mb-2">{{ $clause->title }}</h4>
                        <p class="text-slate-400 text-sm whitespace-pre-wrap">{{ $clause->body }}</p>
                    </div>
                @empty
                    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-8 text-center text-slate-500 text-sm">
                        Belum ada klausul. Gunakan template saat membuat kontrak, atau tambahkan manual.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Milestones Tab --}}
        <div x-show="tab === 'milestones'" x-transition>
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden mb-4">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/50 text-slate-400 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Milestone</th>
                            <th class="px-4 py-3 text-left">Jatuh Tempo</th>
                            <th class="px-4 py-3 text-left">Penanggung Jawab</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Nilai (IDR)</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        @forelse($contract->milestones as $ms)
                            @php
                                $msColor = match($ms->status->value) {
                                    'pending'     => 'text-slate-400 bg-slate-700/50',
                                    'in_progress' => 'text-yellow-400 bg-yellow-400/10',
                                    'completed'   => 'text-emerald-400 bg-emerald-400/10',
                                    'overdue'     => 'text-red-400 bg-red-400/10',
                                    'waived'      => 'text-slate-500 bg-slate-700/30',
                                    default       => 'text-slate-400 bg-slate-700/50',
                                };
                            @endphp
                            <tr class="hover:bg-slate-700/20">
                                <td class="px-4 py-3 text-white">{{ $ms->title }}</td>
                                <td class="px-4 py-3 text-slate-400 text-xs">
                                    {{ $ms->due_date->format('d M Y') }}
                                    @if($ms->due_date->isPast() && $ms->status->value === 'pending')
                                        <span class="text-red-400 ml-1">⚠ Lewat</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-300 capitalize text-xs">{{ str_replace('_',' ',$ms->responsible_role) }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs {{ $msColor }}">{{ ucfirst($ms->status->value) }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-400 text-xs">
                                    {{ $ms->amount_idr > 0 ? 'Rp '.number_format($ms->amount_idr) : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($ms->status->value === 'pending')
                                        <form method="POST" action="{{ route('contract.milestone.complete', [$contract, $ms]) }}"
                                              x-data="{ notes:'' }" class="flex gap-2 items-center">
                                            @csrf
                                            <input x-model="notes" name="completion_notes" placeholder="Catatan penyelesaian..."
                                                   class="px-2 py-1 bg-slate-900 border border-slate-600 rounded text-white text-xs w-40">
                                            <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-xs transition">✅ Selesai</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500 text-sm">Belum ada milestone/obligasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Add Milestone Form --}}
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
                <h3 class="text-sm font-semibold text-slate-300 mb-3">+ Tambah Obligasi/Milestone</h3>
                <form method="POST" action="{{ route('contract.milestone.store', $contract) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <input name="title" placeholder="Judul milestone..." required
                                   class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <input name="due_date" type="date" required
                                   class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <input name="description" placeholder="Deskripsi (opsional)..."
                                   class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <input name="amount_idr" type="number" min="0" placeholder="Nilai IDR (opsional)"
                                   class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                        </div>
                        <div>
                            <select name="responsible_role" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                                <option value="second_party">Pihak Kedua</option>
                                <option value="first_party">Pihak Pertama</option>
                                <option value="both">Kedua Pihak</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition">Tambah Milestone</button>
                </form>
            </div>
        </div>

        {{-- Finance Tab (Fase 29) --}}
        <div x-show="tab === 'finance'" x-transition>
            {{-- Ringkasan plafon & utilisasi (29.5) --}}
            <div class="grid grid-cols-4 gap-4 mb-6">
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Nilai Kontrak</p>
                    <p class="text-white font-semibold mt-1">{{ number_format($contract->total_value_idr) }}</p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Terpakai</p>
                    <p class="text-white font-semibold mt-1">{{ number_format($usage['used_idr']) }}</p>
                    <p class="text-xs mt-1 {{ $usage['status'] === 'ok' ? 'text-emerald-400' : ($usage['status'] === 'warning' ? 'text-amber-400' : 'text-red-400') }}">{{ $usage['percent'] }}% plafon — {{ strtoupper($usage['status']) }}</p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Uang Muka (Advance)</p>
                    <p class="text-white font-semibold mt-1">{{ number_format($contract->advance_paid_idr) }} / {{ number_format($contract->advance_amount_idr) }}</p>
                </div>
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Retensi</p>
                    <p class="text-white font-semibold mt-1">{{ $contract->retention_percent }}%</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 mb-6">
                <form method="POST" action="{{ route('contract.schedule.build', $contract) }}" class="flex items-end gap-2">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Jumlah termin</label>
                        <input type="number" name="count" value="3" min="1" max="60" class="w-24 px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Interval (bulan)</label>
                        <input type="number" name="interval_months" value="3" min="1" max="60" class="w-24 px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    </div>
                    <label class="flex items-center gap-2 text-xs text-slate-400 pb-2">
                        <input type="checkbox" name="by_milestone" value="1" class="rounded"> per milestone
                    </label>
                    <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Bentuk Jadwal</button>
                </form>

                @if($contract->advance_amount_idr > $contract->advance_paid_idr)
                <form method="POST" action="{{ route('contract.advance.pay', $contract) }}" class="flex items-end gap-2">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Bayar advance (IDR)</label>
                        <input type="number" name="amount_idr" min="1" max="{{ $contract->advance_amount_idr - $contract->advance_paid_idr }}"
                               placeholder="{{ number_format($contract->advance_amount_idr - $contract->advance_paid_idr) }}"
                               class="w-40 px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    </div>
                    <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm">Bayar Advance</button>
                </form>
                @endif
            </div>

            {{-- Tabel termin --}}
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden mb-6">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/60 text-slate-400 text-xs uppercase">
                        <tr>
                            <th class="text-left px-4 py-3">Termin</th>
                            <th class="text-left px-4 py-3">Jatuh tempo</th>
                            <th class="text-right px-4 py-3">Nilai</th>
                            <th class="text-right px-4 py-3">Retensi</th>
                            <th class="text-right px-4 py-3">Terbayar</th>
                            <th class="text-left px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/60">
                        @forelse($schedules as $sc)
                            <tr>
                                <td class="px-4 py-3 text-slate-200">
                                    {{ $sc->kind->label() }}
                                    @if($sc->milestone)<span class="text-xs text-slate-500">— {{ $sc->milestone->title }}</span>@endif
                                </td>
                                <td class="px-4 py-3 {{ $sc->due_date->isPast() && $sc->status->value !== 'paid' ? 'text-red-400' : 'text-slate-300' }}">{{ $sc->due_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-right text-white">{{ number_format($sc->amount_idr) }}</td>
                                <td class="px-4 py-3 text-right text-amber-300">{{ $sc->retention_amount_idr > 0 ? number_format($sc->retention_amount_idr) : '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-300">{{ number_format($sc->paid_amount_idr) }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-xs px-2 py-0.5 rounded {{
                                        $sc->status->value === 'paid' ? 'bg-emerald-500/15 text-emerald-300' :
                                        ($sc->status->value === 'waived' ? 'bg-slate-600/40 text-slate-300' :
                                        ($sc->status->value === 'partial' ? 'bg-amber-500/15 text-amber-300' : 'bg-red-500/15 text-red-300')) }}">
                                        {{ $sc->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    @if($sc->status->value !== 'paid' && $sc->status->value !== 'waived')
                                    <form method="POST" action="{{ route('contract.schedule.pay', [$contract, $sc]) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="number" name="amount_idr" min="1" max="{{ $sc->amount_idr - $sc->paid_amount_idr }}"
                                               value="{{ $sc->amount_idr - $sc->paid_amount_idr }}"
                                               class="w-28 px-2 py-1 bg-slate-900 border border-slate-600 rounded text-white text-xs">
                                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                        <button class="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-xs">Bayar</button>
                                    </form>

                                    @if($sc->due_date->isPast())
                                    <form method="POST" action="{{ route('contract.schedule.penalty.pay', [$contract, $sc]) }}" class="inline" title="Bayar denda keterlambatan">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                        <button class="px-2 py-1 bg-red-600/70 hover:bg-red-500 text-white rounded text-xs">Denda</button>
                                    </form>
                                    <form method="POST" action="{{ route('contract.schedule.penalty.waiver', [$contract, $sc]) }}" class="inline" title="Ajukan pembebasan denda">
                                        @csrf
                                        <input type="hidden" name="reason" value="Pembebasan denda termin {{ $sc->id }}">
                                        <button class="px-2 py-1 bg-slate-600 hover:bg-slate-500 text-white rounded text-xs">Waiver</button>
                                    </form>
                                    @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500 text-sm">Belum ada jadwal pembayaran. Bentuk jadwal di atas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Eskalasi (29.3) --}}
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 mb-6">
                <h3 class="text-sm font-semibold text-slate-300 mb-3">📈 Eskalasi Harga</h3>
                @if($contract->escalation_enabled && $contract->escalation_index_code)
                <p class="text-xs text-slate-400 mb-3">
                    Indeks <span class="text-indigo-300 font-mono">{{ $contract->escalation_index_code }}</span> ·
                    dasar <span class="text-white">{{ $contract->escalation_index_base }}</span> ·
                    kini <span class="text-white">{{ $escalation['current_index'] ?? '—' }}</span> ·
                    faktor <span class="text-white">{{ round($escalation['factor'], 6) }}</span>
                    @if($contract->escalation_cap_percent !== null) · cap ±{{ $contract->escalation_cap_percent }}% @endif
                    · formula <span class="font-mono text-slate-500">{{ $contract->escalation_formula }}</span>
                </p>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('contract.escalation.preview', $contract) }}">
                        @csrf
                        <button class="px-3 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-xs">Pratinjau</button>
                    </form>
                    <form method="POST" action="{{ route('contract.escalation.apply', $contract) }}" onsubmit="return confirm('Terapkan eskalasi? Nilai kontrak & termin belum dibayar akan berubah.')">
                        @csrf
                        <button class="px-3 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs">Terapkan Eskalasi</button>
                    </form>
                </div>
                @else
                <p class="text-xs text-slate-500">Eskalasi tidak aktif untuk kontrak ini.</p>
                @endif
            </div>

            {{-- Amandemen (29.4) --}}
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 mb-6">
                <h3 class="text-sm font-semibold text-slate-300 mb-3">✍️ Amandemen / Addendum</h3>
                @if($contract->status->value === 'active')
                <form method="POST" action="{{ route('contract.amend', $contract) }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Nilai baru (IDR)</label>
                        <input type="number" name="total_value_idr" min="0" value="{{ $contract->total_value_idr }}" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Akhir baru</label>
                        <input type="date" name="end_date" value="{{ $contract->end_date?->toDateString() }}" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Jenis</label>
                        <select name="kind" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                            <option value="amendment">Amandemen</option>
                            <option value="addendum">Addendum</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Alasan</label>
                        <input name="reason" placeholder="Alasan perubahan..." class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    </div>
                    <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">Catat</button>
                </form>
                @else
                <p class="text-xs text-slate-500">Amandemen hanya tersedia pada kontrak aktif.</p>
                @endif

                @if($contract->amendments->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach($contract->amendments as $am)
                    <div class="text-xs bg-slate-900/60 border border-slate-700 rounded p-3">
                        <span class="text-indigo-300 font-semibold">{{ $am->kind->label() }}</span>
                        <span class="text-slate-500">{{ $am->effective_date->format('d M Y') }}</span>
                        — nilai {{ number_format($am->old_value_idr) }} → {{ number_format($am->new_value_idr) }}
                        · jadwal {{ $am->schedule_recalculated ? 'dihitung ulang ✓' : 'tidak berubah' }}
                        · versi <a href="{{ route('contract.versions', $contract) }}" class="text-indigo-400 underline">chain #{{ $am->contract_version_id ? 'ada' : '—' }}</a>
                        @if($am->reason)· <span class="text-slate-400">{{ $am->reason }}</span>@endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Penggunaan plafon & risiko (29.5, 29.7) --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-slate-300">🧾 Rekonsiliasi Pemakaian</h3>
                        <form method="POST" action="{{ route('contract.usage.sync', $contract) }}">
                            @csrf
                            <button class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded text-xs">Sinkronkan</button>
                        </form>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-2.5 mb-3">
                        <div class="h-2.5 rounded-full {{ $usage['status'] === 'ok' ? 'bg-emerald-500' : ($usage['status'] === 'warning' ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ min(100, $usage['percent']) }}%"></div>
                    </div>
                    <p class="text-xs text-slate-400 mb-2">{{ number_format($usage['used_idr']) }} dari {{ number_format($usage['total_idr']) }} (sisa {{ number_format($usage['remaining_idr']) }})</p>
                    @if($usage['status'] !== 'ok')
                    <p class="text-xs {{ $usage['status'] === 'warning' ? 'text-amber-400' : 'text-red-400' }}">Early warning: plafon {{ $usage['status'] === 'exceeded' ? 'TERLEWATI' : '≥80%' }}.</p>
                    @endif
                    @if($contract->usages->isNotEmpty())
                    <ul class="mt-3 space-y-1 text-xs text-slate-500 max-h-32 overflow-y-auto">
                        @foreach($contract->usages->take(10) as $u)
                        <li>{{ $u->source_type }} #{{ $u->source_id }} — {{ number_format($u->amount_idr) }}</li>
                        @endforeach
                    </ul>
                    @endif
                </div>

                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-slate-300">⚖️ Skor Risiko (simulasi)</h3>
                        <form method="POST" action="{{ route('contract.risk.rescore', $contract) }}">
                            @csrf
                            <button class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded text-xs">Hitung Ulang</button>
                        </form>
                    </div>
                    <p class="text-3xl font-bold {{ $risk['score'] >= 60 ? 'text-red-400' : ($risk['score'] >= 30 ? 'text-amber-400' : 'text-emerald-400') }}">{{ $risk['score'] }}<span class="text-sm text-slate-500 font-normal">/100</span></p>
                    <p class="text-xs text-slate-500 mb-2">Forum: {{ $contract->arbitration_rules ?? $contract->dispute_forum ?? '—' }}</p>
                    @if($risk['flags'])
                    <ul class="space-y-1 text-xs">
                        @foreach($risk['flags'] as $flag)
                        <li class="text-amber-300">⚠ {{ $flag }}</li>
                        @endforeach
                    </ul>
                    @else
                    <p class="text-xs text-emerald-400">✓ Tidak ada flag risiko.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Attachments Tab (28.6) --}}
        <div x-show="tab === 'attachments'" x-transition>
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 mb-4">
                <h3 class="text-sm font-semibold text-slate-300 mb-3">📎 Unggah Lampiran</h3>
                <form method="POST" action="{{ route('contract.attachment.store', $contract) }}"
                      enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    @csrf
                    <div class="md:col-span-1">
                        <label class="text-xs text-slate-400 block mb-1">Berkas (pdf/jpg/png/csv/docx/xlsx/txt, maks 10 MB)</label>
                        <input type="file" name="file" required
                               class="w-full text-xs text-slate-400 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white file:text-xs file:cursor-pointer">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Label</label>
                        <input name="label" required placeholder="Mis. Annex A - SLA"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Jenis</label>
                        <select name="kind" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                            <option value="supporting">Dokumen pendukung</option>
                            <option value="annex">Lampiran / Annex</option>
                            <option value="signed_copy">Salinan berkas kontrak</option>
                            <option value="kyc">Dokumen KYC pihak</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Pihak penandatangan (opsional)</label>
                        <select name="party_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                            <option value="">— umum —</option>
                            @foreach($contract->parties as $p)
                                <option value="{{ $p->id }}">{{ $p->party?->name }} ({{ $p->role->label() }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition">Unggah</button>
                    </div>
                    @error('file')<p class="md:col-span-4 text-xs text-red-400">{{ $message }}</p>@enderror
                </form>
            </div>

            <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/60 text-slate-400 text-xs uppercase">
                        <tr>
                            <th class="text-left px-4 py-3">Label</th>
                            <th class="text-left px-4 py-3">Jenis</th>
                            <th class="text-left px-4 py-3">Pihak</th>
                            <th class="text-left px-4 py-3">Berkas</th>
                            <th class="text-left px-4 py-3">Checksum</th>
                            <th class="text-left px-4 py-3">Unggah</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/60">
                        @forelse($contract->attachments as $att)
                            <tr>
                                <td class="px-4 py-3 text-slate-200">{{ $att->label }}</td>
                                <td class="px-4 py-3 text-xs uppercase text-slate-400">{{ $att->kind }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $att->signatory?->party?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-slate-400">{{ $att->document?->original_filename ?? '—' }} ({{ $att->document?->file_size_bytes ? number_format($att->document->file_size_bytes/1024, 1).' KB' : '—' }})</td>
                                <td class="px-4 py-3 font-mono text-[10px] text-slate-500 truncate max-w-[12rem]">{{ $att->document?->checksum_sha256 ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ $att->created_at?->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if(in_array($contract->status->value, ['draft','negotiation']))
                                        <form method="POST" action="{{ route('contract.attachment.destroy', [$contract, $att]) }}" onsubmit="return confirm('Hapus lampiran ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-xs text-red-400 hover:text-red-300">Hapus</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500 text-sm">Belum ada lampiran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Versions Tab --}}
        <div x-show="tab === 'versions'" x-transition>
            <div class="mb-4 bg-indigo-500/10 border border-indigo-500/30 rounded-xl p-4 text-sm text-indigo-300">
                🔗 Hash-chain append-only — setiap versi terhubung kriptografis ke versi sebelumnya (SHA-256).
                <a href="{{ route('contract.versions', $contract) }}" class="underline ml-1">Lihat chain explorer →</a>
            </div>

            <div class="space-y-3">
                @forelse($contract->versions as $v)
                    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-mono text-indigo-300">v{{ $v->sequence }}</span>
                                <span class="text-xs text-slate-400 uppercase bg-slate-700 px-2 py-0.5 rounded">{{ $v->change_type }}</span>
                                <span class="text-xs text-slate-500">oleh {{ $v->created_by_name }}</span>
                            </div>
                            <span class="text-xs text-slate-500">{{ $v->created_at?->format('d M Y H:i') }}</span>
                        </div>
                        <p class="text-xs font-mono text-slate-500 truncate">Hash: {{ $v->hash }}</p>
                    </div>
                @empty
                    <div class="text-center text-slate-500 py-8 text-sm">Belum ada versi.</div>
                @endforelse
            </div>

            {{-- Append Version Form --}}
            @if(in_array($contract->status->value, ['negotiation','review']))
                <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 mt-4">
                    <h3 class="text-sm font-semibold text-slate-300 mb-3">+ Tambah Versi Negosiasi</h3>
                    <form method="POST" action="{{ route('contract.append-version', $contract) }}" class="space-y-3">
                        @csrf
                        <textarea name="body" rows="6" required placeholder="Isi kontrak versi baru..."
                                  class="w-full px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm font-mono">{{ $contract->current_body }}</textarea>
                        <div class="flex gap-3">
                            <select name="change_type" class="px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                                <option value="negotiation">Negosiasi</option>
                                <option value="amendment">Amandemen</option>
                                <option value="clause_update">Update Klausul</option>
                            </select>
                            <input name="notes" placeholder="Catatan perubahan..." class="flex-1 px-3 py-2 bg-slate-900 border border-slate-600 rounded-lg text-white text-sm">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition">
                                🔗 Commit Versi
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

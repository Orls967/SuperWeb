@extends('layouts.app')

@section('content')
<div class="py-12 bg-gray-900 text-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="p-6 bg-gray-800 shadow-xl rounded-xl border border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <span>🏗️</span> EPC BIM to Operational Digital Twin Handover
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        Cryptographic hash-chaining of as-built architectural models and MEP component assets.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('proptech.bim.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm font-semibold rounded-lg text-white transition">
                        BIM Twin Hub
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-emerald-900/60 border border-emerald-500 rounded-lg text-emerald-200 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Execute As-Built Handover</h3>
                    <form action="{{ route('proptech.intelligence.epc-handover.handover') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">Project Identifier</label>
                            <input type="text" name="project_code" value="PRJ-TOWER-A" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-300 mb-1">BIM Model Name</label>
                            <input type="text" name="model_name" value="Tower A Operational Digital Twin" required class="w-full bg-gray-800 border border-gray-700 rounded p-2 text-white text-sm">
                        </div>
                        <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition">
                            Finalize Digital Twin Handover
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-gray-900/80 p-5 rounded-lg border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Tamper-Evident BIM Version Ledger</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-300">
                            <thead class="bg-gray-800 text-gray-400 uppercase">
                                <tr>
                                    <th class="p-3">Model Code</th>
                                    <th class="p-3">Project</th>
                                    <th class="p-3 text-center">Version</th>
                                    <th class="p-3">Cryptographic Hash</th>
                                    <th class="p-3 text-center">Components</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                @forelse($models as $m)
                                    <tr class="hover:bg-gray-800/40">
                                        <td class="p-3 font-mono text-cyan-400">{{ $m->model_code }}</td>
                                        <td class="p-3 font-semibold text-white">{{ $m->project_code }}</td>
                                        <td class="p-3 text-center font-mono">v{{ $m->version }}</td>
                                        <td class="p-3 font-mono text-[11px] text-gray-400">{{ substr($m->current_hash, 0, 16) }}...</td>
                                        <td class="p-3 text-center font-mono text-emerald-400">{{ $m->components ? $m->components->count() : 0 }}</td>
                                        <td class="p-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-900 text-emerald-300 border border-emerald-700">
                                                {{ $m->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-gray-500">No BIM digital twin models registered yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

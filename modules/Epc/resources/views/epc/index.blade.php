<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('EPC Construction, WBS S-Curve & Asset Capitalization') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Construction & Capitalization Health</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-sky-50 p-4 rounded border border-sky-200">
                        <span class="text-xs text-gray-500 uppercase">System Status</span>
                        <p class="text-xl font-bold text-sky-700">{{ $audit['status'] }}</p>
                    </div>
                    <div class="bg-indigo-50 p-4 rounded border border-indigo-200">
                        <span class="text-xs text-gray-500 uppercase">Active Projects</span>
                        <p class="text-xl font-bold text-indigo-700">{{ $audit['projects_count'] }}</p>
                    </div>
                    <div class="bg-amber-50 p-4 rounded border border-amber-200">
                        <span class="text-xs text-gray-500 uppercase">Active CIP in Progress</span>
                        <p class="text-xl font-bold text-amber-700">Rp {{ number_format($audit['total_active_cip_idr'], 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-emerald-50 p-4 rounded border border-emerald-200">
                        <span class="text-xs text-gray-500 uppercase">Capitalized Fixed Assets</span>
                        <p class="text-xl font-bold text-emerald-700">Rp {{ number_format($audit['total_capitalized_idr'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">EPC Projects & S-Curve Progress</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Code</th>
                                <th class="px-4 py-2 text-left">Project Name</th>
                                <th class="px-4 py-2 text-left">Type</th>
                                <th class="px-4 py-2 text-right">RAB Budget</th>
                                <th class="px-4 py-2 text-right">Physical Progress</th>
                                <th class="px-4 py-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($projects as $p)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $p->project_code }}</td>
                                    <td class="px-4 py-2 font-semibold">{{ $p->project_name }}</td>
                                    <td class="px-4 py-2">{{ $p->project_type }}</td>
                                    <td class="px-4 py-2 text-right">Rp {{ number_format($p->total_rab_budget_idr, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-right font-bold text-sky-600">{{ number_format($p->actual_physical_progress_pct, 2) }}%</td>
                                    <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800 uppercase">{{ $p->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-400">No EPC projects registered.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

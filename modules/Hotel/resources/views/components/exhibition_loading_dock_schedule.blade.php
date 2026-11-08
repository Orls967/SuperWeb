@props(['docks' => [
    ['bay' => 'Dock Bay 1 (Heavy Freight)', 'contractor' => 'Apex Logistics Ind', 'time' => '06:00 - 09:00', 'status' => 'COMPLETED', 'vehicle' => '40ft Container Truck'],
    ['bay' => 'Dock Bay 2 (AV Rigging)', 'contractor' => 'StageTech Sound & Lights', 'time' => '09:00 - 12:00', 'status' => 'IN_PROGRESS', 'vehicle' => 'Wingbox 24ft'],
    ['bay' => 'Dock Bay 3 (Booth Contractor)', 'contractor' => 'Expro Stand Design', 'time' => '13:00 - 16:00', 'status' => 'SCHEDULED', 'vehicle' => 'Medium Van 12ft'],
    ['bay' => 'Dock Bay 4 (Florist & Decor)', 'contractor' => 'Botanica Creations', 'time' => '16:00 - 18:00', 'status' => 'SCHEDULED', 'vehicle' => 'Refrigerated Van'],
]])

<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-5 mt-6">
    <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-700 mb-4">
        <div class="flex items-center space-x-2">
            <span class="p-2 bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </span>
            <div>
                <h4 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Trade Show Exhibition Logistics & Loading Dock Schedule</h4>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Marshalling yard slots, forklift allocations & freight ingress</p>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-1 text-xs font-semibold bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded-md">
                Dock Capacity: 4 Bays
            </span>
            <button type="button" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-medium transition-colors">
                Book Ingress Bay
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($docks as $dock)
            <div class="p-3.5 rounded-lg border border-zinc-200 dark:border-zinc-700/80 bg-zinc-50/50 dark:bg-zinc-900/30 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $dock['bay'] }}</span>
                        @if($dock['status'] === 'COMPLETED')
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">DONE</span>
                        @elseif($dock['status'] === 'IN_PROGRESS')
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 animate-pulse">ACTIVE</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">QUEUED</span>
                        @endif
                    </div>
                    <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400 mb-1">{{ $dock['contractor'] }}</div>
                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $dock['vehicle'] }}</div>
                </div>
                <div class="mt-3 pt-2 border-t border-zinc-200 dark:border-zinc-700/60 flex items-center justify-between text-[11px] text-zinc-600 dark:text-zinc-400">
                    <span class="font-mono">{{ $dock['time'] }}</span>
                    <span class="font-medium">Slot #0{{ $loop->iteration }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>

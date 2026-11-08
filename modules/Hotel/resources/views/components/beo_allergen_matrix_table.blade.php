@props(['allergens' => [
    ['item' => 'Grilled Australian Wagyu Beef', 'gluten' => false, 'dairy' => true, 'nuts' => false, 'shellfish' => false, 'egg' => false, 'halal' => true, 'vegan' => false],
    ['item' => 'Pan-Seared Chilean Sea Bass', 'gluten' => true, 'dairy' => true, 'nuts' => false, 'shellfish' => false, 'egg' => false, 'halal' => true, 'vegan' => false],
    ['item' => 'Truffle Wild Mushroom Risotto', 'gluten' => false, 'dairy' => true, 'nuts' => false, 'shellfish' => false, 'egg' => false, 'halal' => true, 'vegan' => true],
    ['item' => 'Tiger Prawn Bisque with Brandy', 'gluten' => true, 'dairy' => true, 'nuts' => false, 'shellfish' => true, 'egg' => false, 'halal' => false, 'vegan' => false],
    ['item' => 'Chocolate Lava Cake with Berries', 'gluten' => true, 'dairy' => true, 'nuts' => true, 'shellfish' => false, 'egg' => true, 'halal' => true, 'vegan' => false],
]])

<div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-5 mt-6">
    <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-700 mb-4">
        <div class="flex items-center space-x-2">
            <span class="p-2 bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </span>
            <div>
                <h4 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">BEO Banquet Allergen & Dietary Compliance Matrix</h4>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Strict ISO 22000 cross-contamination preventive control</p>
            </div>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
            HACCP Certified
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 uppercase tracking-wider font-semibold bg-zinc-50 dark:bg-zinc-900/40">
                    <th class="py-2.5 px-3">Menu / Recipe Item</th>
                    <th class="py-2.5 px-2 text-center">Gluten</th>
                    <th class="py-2.5 px-2 text-center">Dairy</th>
                    <th class="py-2.5 px-2 text-center">Tree Nuts</th>
                    <th class="py-2.5 px-2 text-center">Shellfish</th>
                    <th class="py-2.5 px-2 text-center">Eggs</th>
                    <th class="py-2.5 px-2 text-center">Halal</th>
                    <th class="py-2.5 px-2 text-center">Vegetarian</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-zinc-700 dark:text-zinc-300">
                @foreach($allergens as $row)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/60 transition-colors">
                        <td class="py-2.5 px-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $row['item'] }}</td>
                        <td class="py-2.5 px-2 text-center">
                            @if($row['gluten'])
                                <span class="px-2 py-0.5 bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300 rounded font-semibold text-[10px]">YES</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-center">
                            @if($row['dairy'])
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 rounded font-semibold text-[10px]">YES</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-center">
                            @if($row['nuts'])
                                <span class="px-2 py-0.5 bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 rounded font-semibold text-[10px]">YES</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-center">
                            @if($row['shellfish'])
                                <span class="px-2 py-0.5 bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300 rounded font-semibold text-[10px]">YES</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-center">
                            @if($row['egg'])
                                <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300 rounded font-semibold text-[10px]">YES</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-center">
                            @if($row['halal'])
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 rounded font-semibold text-[10px]">HALAL</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-2 text-center">
                            @if($row['vegan'])
                                <span class="px-2 py-0.5 bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300 rounded font-semibold text-[10px]">VEGAN</span>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

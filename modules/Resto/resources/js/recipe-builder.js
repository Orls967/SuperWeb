/**
 * Recipe Builder Alpine.js component:
 * Mengelola baris resep dinamis (bahan baku / sub-resep) dan menghitung
 * HPP per porsi, margin laba, dan rekomendasi harga secara real-time via JSON.
 */
export default function recipeBuilder(config) {
    return {
        endpoint: config.endpoint,
        csrf: config.csrf,
        ingredients: config.ingredients || [],
        subRecipes: config.subRecipes || [],
        outletId: config.outletId || '',
        sellingPrice: Number(config.sellingPrice || 0),
        expectedPortions: Number(config.expectedPortions || 1),
        wastePercent: Number(config.wastePercent || 0),
        lines: config.initialLines && config.initialLines.length ? config.initialLines : [],

        costData: {
            batch_cost: 0,
            cost_per_portion: 0,
            cost_per_portion_idr: 0,
            margin_percent: 0,
            suggested_price: 0,
            warning: null,
            lines: []
        },
        loading: false,

        init() {
            if (this.lines.length === 0) {
                this.addLine('ingredient');
            }
            this.recalculate();
        },

        addLine(type = 'ingredient') {
            this.lines.push({
                line_type: type,
                id: '',
                qty_base_unit: 100,
                note: ''
            });
            this.recalculate();
        },

        removeLine(index) {
            this.lines.splice(index, 1);
            if (this.lines.length === 0) {
                this.addLine('ingredient');
            }
            this.recalculate();
        },

        async recalculate() {
            this.loading = true;
            try {
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        outlet_id: this.outletId,
                        selling_price: this.sellingPrice,
                        expected_portions: this.expectedPortions,
                        waste_percent: this.wastePercent,
                        lines: this.lines
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.costData = data;
                }
            } catch (err) {
                console.error('Failed to calculate recipe cost', err);
            } finally {
                this.loading = false;
            }
        },

        formatIdr(val) {
            return 'Rp ' + Math.round(Number(val || 0)).toLocaleString('id-ID');
        }
    };
}

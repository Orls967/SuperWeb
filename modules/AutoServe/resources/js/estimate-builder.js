/**
 * Baris dinamis untuk penyusunan estimasi perbaikan oleh mekanik.
 * Dipakai di view serve::bookings._estimate-panel.
 */
export default function estimateBuilder(services = [], parts = []) {
    return {
        services,
        parts,
        rows: [],
        nextKey: 1,

        optionsFor(type) {
            return type === 'service' ? this.services : this.parts;
        },

        addRow(type) {
            this.rows.push({
                key: this.nextKey++,
                type,
                ref_id: '',
                qty: 1,
                unit_price: 0,
            });
        },

        removeRow(index) {
            this.rows.splice(index, 1);
        },

        syncPrice(row) {
            const option = this.optionsFor(row.type).find(
                (item) => Number(item.id) === Number(row.ref_id)
            );

            if (option) {
                row.unit_price = option.price;
            }
        },

        get total() {
            return this.rows.reduce(
                (sum, row) => sum + Number(row.qty || 0) * Number(row.unit_price || 0),
                0
            );
        },
    };
}

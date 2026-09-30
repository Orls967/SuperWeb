/**
 * Komponen Alpine untuk layar operator gate parkir Duta Mall.
 *
 * Tanggung jawab:
 *  - polling okupansi zona setiap 30 detik supaya operator tahu zona penuh
 *  - hitung kembalian tunai secara langsung saat kasir mengetik nominal
 *  - membuat idempotency key sekali saat layar dibuka agar submit ganda /
 *    refresh tidak menghasilkan dua posting pembayaran
 */
export default (config = {}) => ({
    occupancyUrl: config.occupancyUrl || '',
    pollInterval: config.pollInterval || 30000,

    zones: config.zones || [],
    totalCapacity: config.totalCapacity || 0,
    totalOccupied: config.totalOccupied || 0,
    updatedAt: '',
    loading: false,

    totalFee: config.totalFee || 0,
    paymentMethod: 'cash',
    cashTendered: '',
    submitting: false,
    idempotencyKey: '',

    init() {
        this.idempotencyKey = this.generateKey();

        if (this.occupancyUrl) {
            this.refreshOccupancy();
            this.timer = setInterval(() => this.refreshOccupancy(), this.pollInterval);
        }
    },

    destroy() {
        if (this.timer) {
            clearInterval(this.timer);
        }
    },

    async refreshOccupancy() {
        if (this.loading) {
            return;
        }

        this.loading = true;

        try {
            const response = await fetch(this.occupancyUrl, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            this.zones = data.zones || [];
            this.totalCapacity = data.total_capacity || 0;
            this.totalOccupied = data.total_occupied || 0;
            this.updatedAt = data.updated_at || '';
        } catch (error) {
            // Gate tetap harus bisa dipakai walau polling gagal; angka terakhir dipertahankan.
        } finally {
            this.loading = false;
        }
    },

    get occupancyRate() {
        if (!this.totalCapacity) {
            return 0;
        }

        return Math.round((this.totalOccupied / this.totalCapacity) * 1000) / 10;
    },

    get tenderedValue() {
        const parsed = parseInt(String(this.cashTendered).replace(/\D/g, ''), 10);

        return Number.isNaN(parsed) ? 0 : parsed;
    },

    get change() {
        return Math.max(0, this.tenderedValue - this.totalFee);
    },

    get cashShortfall() {
        return Math.max(0, this.totalFee - this.tenderedValue);
    },

    get canSubmit() {
        if (this.submitting) {
            return false;
        }

        if (this.paymentMethod === 'cash') {
            return this.tenderedValue >= this.totalFee;
        }

        return true;
    },

    quickTender(amount) {
        this.cashTendered = String(amount);
    },

    exactTender() {
        this.cashTendered = String(this.totalFee);
    },

    formatIdr(value) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(value || 0);
    },

    zoneBarClass(zone) {
        if (zone.is_full) {
            return 'bg-rose-500';
        }

        return zone.rate >= 85 ? 'bg-amber-500' : 'bg-emerald-500';
    },

    generateKey() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'gate-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
    },
});

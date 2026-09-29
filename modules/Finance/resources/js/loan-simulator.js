/**
 * Simulator HODL-to-Drive: hitung DP, tenor, kebutuhan kolateral, dan cicilan
 * per bulan secara real-time sebelum pengajuan dikirim.
 */
export default function loanSimulator(config) {
    return {
        grandTotal: config.grandTotal,
        tenors: config.tenors,
        prices: config.prices,
        holdings: config.holdings,
        maxLtv: config.maxLtv,
        annualRate: config.annualRate,
        walletBalance: config.walletBalance,

        downPayment: config.downPayment ?? 0,
        tenorMonths: config.tenors[1] ?? config.tenors[0],
        symbol: config.defaultSymbol,
        pin: '',
        submitting: false,
        idempotencyKey: crypto.randomUUID(),

        get principal() {
            return Math.max(0, this.grandTotal - Number(this.downPayment || 0));
        },

        get totalInterest() {
            return Math.round((this.principal * this.annualRate * this.tenorMonths) / 12);
        },

        get monthlyAmount() {
            if (this.tenorMonths <= 0) {
                return 0;
            }

            return Math.round((this.principal + this.totalInterest) / this.tenorMonths);
        },

        get collateralPrice() {
            return Number(this.prices[this.symbol] ?? 0);
        },

        get requiredCollateral() {
            if (this.collateralPrice <= 0) {
                return 0;
            }

            return this.principal / (this.collateralPrice * this.maxLtv);
        },

        get holding() {
            return Number(this.holdings[this.symbol] ?? 0);
        },

        get hasEnoughCollateral() {
            return this.holding >= this.requiredCollateral;
        },

        get hasEnoughCash() {
            return this.walletBalance >= Number(this.downPayment || 0);
        },

        get canSubmit() {
            return (
                this.principal > 0 &&
                this.pin.length === 6 &&
                this.hasEnoughCollateral &&
                this.hasEnoughCash &&
                !this.submitting
            );
        },

        formatIdr(value) {
            return 'Rp ' + Math.round(Number(value || 0)).toLocaleString('id-ID');
        },

        formatQty(value) {
            return Number(value || 0).toFixed(8);
        },

        /** Jadwal ringkas untuk ditampilkan tanpa memanggil server. */
        get schedulePreview() {
            const rows = [];
            const principalPer = Math.floor(this.principal / this.tenorMonths);
            const interestPer = Math.floor(this.totalInterest / this.tenorMonths);

            for (let i = 1; i <= Math.min(this.tenorMonths, 6); i++) {
                rows.push({
                    sequence: i,
                    principal: principalPer,
                    interest: interestPer,
                    amount: principalPer + interestPer,
                });
            }

            return rows;
        },
    };
}

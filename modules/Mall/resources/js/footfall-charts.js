/**
 * Grafik analitik kunjungan mall.
 *
 * Chart.js dimuat lewat CDN (pola yang sama dipakai modul Crypto), komponen ini
 * hanya bertugas menggambar dan membersihkan instance grafik.
 */
export default (config = {}) => ({
    daily: config.daily || [],
    hourly: config.hourly || [],
    charts: {},

    init() {
        if (typeof window.Chart === 'undefined') {
            return;
        }

        this.renderDaily();
        this.renderHourly();
    },

    destroy() {
        Object.values(this.charts).forEach((chart) => chart.destroy());
        this.charts = {};
    },

    renderDaily() {
        const canvas = this.$refs.dailyChart;

        if (!canvas) {
            return;
        }

        this.charts.daily = new window.Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: this.daily.map((row) => this.shortDate(row.date)),
                datasets: [
                    {
                        label: 'Kunjungan',
                        data: this.daily.map((row) => row.visitors),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    },
                ],
            },
            options: this.baseOptions(),
        });
    },

    renderHourly() {
        const canvas = this.$refs.hourlyChart;

        if (!canvas) {
            return;
        }

        const peak = Math.max(...this.hourly.map((row) => row.visitors), 0);

        this.charts.hourly = new window.Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: this.hourly.map((row) => String(row.hour).padStart(2, '0') + ':00'),
                datasets: [
                    {
                        label: 'Kunjungan',
                        data: this.hourly.map((row) => row.visitors),
                        backgroundColor: this.hourly.map((row) =>
                            row.visitors === peak ? '#f59e0b' : 'rgba(37, 99, 235, 0.55)'
                        ),
                        borderRadius: 6,
                    },
                ],
            },
            options: this.baseOptions(),
        });
    },

    baseOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) =>
                            new Intl.NumberFormat('id-ID').format(context.parsed.y) + ' pengunjung',
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8', font: { size: 10 }, maxRotation: 0, autoSkipPadding: 12 },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(148, 163, 184, 0.15)' },
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 10 },
                        callback: (value) => new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value),
                    },
                },
            },
        };
    },

    shortDate(value) {
        const date = new Date(value);

        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
    },
});

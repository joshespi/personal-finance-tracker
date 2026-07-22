import { themeColors } from './chart-utils';

// Debt-payoff Alpine component: renders the snowball/avalanche projection chart and
// drives the "extra payment" what-if slider. Registered on 'alpine:init' like the
// other page-specific Alpine.data() factories in app.js.
//
// The snowball/avalanche simulation itself is NOT reimplemented here — recalculate()
// calls the same DebtPayoffService::simulate() the initial page render used, via a
// debounced POST, instead of carrying a second copy of the month-by-month payoff
// algorithm in JS (the two had drifted apart before: this file used to hand-roll its
// own runSimulation()).
document.addEventListener('alpine:init', () => {
    window.Alpine.data('debtPayoff', (debtInputs, initialSnowball, initialAvalanche) => ({
        debtInputs,
        extraPayment: 0,
        strategy: 'snowball',
        snowballResult: initialSnowball,
        avalancheResult: initialAvalanche,
        chart: null,
        _debounce: null,
        _requestSeq: 0,

        get activeResult() {
            return this.strategy === 'snowball' ? this.snowballResult : this.avalancheResult;
        },

        formatMonths(n) {
            if (!n || n >= 600) return '50+ yrs';
            const y = Math.floor(n / 12), m = n % 12;
            if (y === 0) return m + ' mo';
            if (m === 0) return y + ' yr';
            return y + 'yr ' + m + 'mo';
        },

        payoffDate(months) {
            if (!months || months >= 600) return '50+ yrs';
            const d = new Date();
            d.setMonth(d.getMonth() + months);
            return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        },

        // Debounced by the extraPayment $watch below. Requests are sequenced so a
        // slow earlier response can't overwrite a newer one if they resolve out of order.
        async recalculate() {
            const seq = ++this._requestSeq;
            const extra = parseFloat(this.extraPayment) || 0;

            let data;
            try {
                ({ data } = await window.axios.post('/planning/debt-payoff/simulate', { extra_payment: extra }));
            } catch (e) {
                return; // leave the previous result in place on a failed request
            }
            if (seq !== this._requestSeq) return;

            this.snowballResult  = data.snowball;
            this.avalancheResult = data.avalanche;
            this.updateChart();
        },

        chartLabels(len) {
            return Array.from({ length: len }, (_, i) => {
                const d = new Date(); d.setMonth(d.getMonth() + i + 1);
                return d.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
            });
        },

        updateChart() {
            if (!this.chart) return;
            const s = this.snowballResult.timeline, a = this.avalancheResult.timeline;
            const maxLen = Math.max(s.length, a.length);
            this.chart.data.labels = this.chartLabels(maxLen);
            this.chart.data.datasets[0].data = s;
            this.chart.data.datasets[1].data = a;
            this.chart.update('none');
        },

        init() {
            this.$nextTick(() => {
                const ctx = document.getElementById('debtChart');
                if (!ctx) return;
                const { gridColor: gc, labelColor: tc } = themeColors();
                const s = this.snowballResult.timeline, a = this.avalancheResult.timeline;
                this.chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: this.chartLabels(Math.max(s.length, a.length)),
                        datasets: [
                            { label: 'Snowball',  data: s, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.08)', fill: true,  tension: 0.3, pointRadius: 0, borderWidth: 2 },
                            { label: 'Avalanche', data: a, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.04)',  fill: false, tension: 0.3, pointRadius: 0, borderWidth: 2, borderDash: [5, 4] },
                        ],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, animation: false,
                        plugins: {
                            legend: { position: 'top', labels: { color: tc, usePointStyle: true, pointStyleWidth: 10 } },
                            tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': $' + Math.round(ctx.raw).toLocaleString() } },
                        },
                        scales: {
                            x: { ticks: { maxTicksLimit: 12, color: tc }, grid: { color: gc } },
                            y: { ticks: { callback: v => '$' + Math.round(v).toLocaleString(), color: tc }, grid: { color: gc } },
                        },
                    },
                });
            });

            this.$watch('extraPayment', () => {
                clearTimeout(this._debounce);
                this._debounce = setTimeout(() => this.recalculate(), 250);
            });
        },
    }));
});

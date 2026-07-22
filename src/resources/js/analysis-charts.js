import { themeColors } from './chart-utils';

// Cashflow (bar) and Spending Trends (stacked bar) charts for the Analysis page tabs.
// Reads its data from window.__analysisCharts, a plain JSON blob set inline by the
// Blade view — this module owns all the Chart.js setup, replacing what used to be
// two near-identical inline <script> blocks with their own copy of the tooltip/axis
// dollar-formatting logic.
document.addEventListener('DOMContentLoaded', function () {
    const cfg = window.__analysisCharts;
    if (!cfg) return;

    const { gridColor: grid, labelColor: ticks } = themeColors();
    const fmtDollar  = v => '$' + v.toLocaleString('en-US');
    const fmtDollar2 = v => ' $' + v.toLocaleString('en-US', { minimumFractionDigits: 2 });

    if (cfg.tab === 'cashflow') {
        const el = document.getElementById('cashflowChart');
        if (!el) return;
        const history = cfg.history ?? [];

        new Chart(el, {
            type: 'bar',
            data: {
                labels: history.map(h => h.month),
                datasets: [
                    { label: 'Income', data: history.map(h => h.income), backgroundColor: 'rgba(34,197,94,0.65)', borderColor: 'rgba(34,197,94,1)', borderWidth: 1, borderRadius: 3 },
                    { label: 'Spent',  data: history.map(h => h.spent),  backgroundColor: 'rgba(239,68,68,0.65)', borderColor: 'rgba(239,68,68,1)',  borderWidth: 1, borderRadius: 3 },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { labels: { color: ticks } },
                    tooltip: { callbacks: { label: ctx => fmtDollar2(ctx.parsed.y) } },
                },
                scales: {
                    x: { ticks: { color: ticks }, grid: { color: grid } },
                    y: { beginAtZero: true, ticks: { color: ticks, callback: fmtDollar }, grid: { color: grid } },
                },
            },
        });
    } else if (cfg.tab === 'trends') {
        const el = document.getElementById('trendsChart');
        if (!el) return;
        const labels   = cfg.monthLabels ?? [];
        const datasets = cfg.datasets ?? [];
        if (!datasets.length) return;

        new Chart(el, {
            type: 'bar',
            data: {
                labels,
                datasets: datasets.map(ds => ({
                    label: ds.label, data: ds.data,
                    backgroundColor: ds.color + 'cc', borderColor: ds.color,
                    borderWidth: 1, borderRadius: 2, stack: 'spend',
                })),
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { color: ticks, boxWidth: 12, padding: 16 } },
                    tooltip: { callbacks: { label: ctx => ' ' + ctx.dataset.label + ':' + fmtDollar2(ctx.parsed.y) } },
                },
                scales: {
                    x: { stacked: true, ticks: { color: ticks }, grid: { color: grid } },
                    y: { stacked: true, beginAtZero: true, ticks: { color: ticks, callback: fmtDollar }, grid: { color: grid } },
                },
            },
        });
    }
});

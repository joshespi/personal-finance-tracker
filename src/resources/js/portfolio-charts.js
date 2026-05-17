import {
    Chart,
    LineController, LineElement, PointElement, Filler,
    LinearScale, TimeScale, Tooltip, Legend,
    PieController, ArcElement,
} from 'chart.js';
import 'chartjs-adapter-date-fns';
import { filterByRange, activateBtn, fmtK, fmtFull, makeTimeScales, makeLegendOpts, pointFromRow, buildNorm, benchTickerColors } from './chart-utils';

Chart.register(LineController, LineElement, PointElement, Filler, LinearScale, TimeScale, Tooltip, Legend, PieController, ArcElement);

document.addEventListener('DOMContentLoaded', function () {
    const { chartData: allData, benchmarkData: benchRaw, allocation: allocData } = window.__portCharts ?? {};
    if (!allData) return;

    const isDark     = document.documentElement.classList.contains('dark');
    const gridColor  = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
    const labelColor = isDark ? '#9ca3af' : '#6b7280';

    const portChartEl = document.getElementById('portChart');
    if (portChartEl) {
        let portRange = '1Y';
        const portChart = new Chart(portChartEl, {
            type: 'line',
            data: {
                datasets: [
                    { label: 'Market Value', data: [], borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.15)', fill: true,  tension: 0.3, borderWidth: 2, pointRadius: 0 },
                    { label: 'Cost Basis',   data: [], borderColor: '#94a3b8', borderDash: [5, 5],                        fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: makeTimeScales(gridColor, labelColor, fmtK),
                plugins: {
                    legend: makeLegendOpts(labelColor),
                    tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${fmtFull(ctx.parsed.y)}` } },
                },
            },
        });

        function updatePortChart(range) {
            const filtered = filterByRange(allData, range);
            portChart.data.datasets[0].data = filtered.map(r => pointFromRow(r, 'value'));
            portChart.data.datasets[1].data = filtered.map(r => pointFromRow(r, 'cost'));
            portChart.update();
            activateBtn('#port-range-btns', range);
        }

        document.querySelectorAll('#port-range-btns button[data-range]').forEach(b =>
            b.addEventListener('click', () => { portRange = b.dataset.range; updatePortChart(portRange); })
        );
        updatePortChart(portRange);
    }

    const benchEl = document.getElementById('portBenchChart');
    if (benchEl && Object.keys(benchRaw).length > 0) {
        let benchRange = '1Y';

        const benchChart = new Chart(benchEl, {
            type: 'line',
            data: { datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: makeTimeScales(gridColor, labelColor, v => v.toFixed(1) + '%'),
                plugins: {
                    legend: makeLegendOpts(labelColor),
                    tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(2)}%` } },
                },
            },
        });

        function updateBenchChart(range) {
            const datasets = [{ label: 'This Portfolio', data: buildNorm(allData, 'value', range), borderColor: '#6366f1', fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0 }];
            Object.keys(benchRaw).forEach(t => datasets.push({
                label: t, data: buildNorm(benchRaw[t] || [], 'price', range), borderColor: benchTickerColors[t] || '#9ca3af', fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0,
            }));
            benchChart.data.datasets = datasets;
            benchChart.update();
            activateBtn('#port-bench-range-btns', range);
        }

        document.querySelectorAll('#port-bench-range-btns button[data-range]').forEach(b =>
            b.addEventListener('click', () => { benchRange = b.dataset.range; updateBenchChart(benchRange); })
        );
        updateBenchChart(benchRange);
    }

    const donutEl = document.getElementById('portAllocationDonut');
    if (donutEl && allocData.total > 0) {
        const labels = allocData.holdings.map(h => h.symbol);
        const values = allocData.holdings.map(h => h.value);
        if (allocData.manual_value > 0) {
            labels.push('Manual');
            values.push(allocData.manual_value);
        }
        new Chart(donutEl, {
            type: 'pie',
            data: { labels, datasets: [{ data: values, borderWidth: 0 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => `${ctx.label}: $${ctx.parsed.toLocaleString('en-US', { minimumFractionDigits: 2 })}` } },
                },
            },
        });
    }
});

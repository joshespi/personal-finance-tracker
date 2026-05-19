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
    const { chartData: allDataFull, chartDataExManual: allDataMkt, benchmarkData: benchRaw, allocation: allocData } = window.__dashCharts ?? {};
    if (!allDataFull) return;

    const isDark     = document.documentElement.classList.contains('dark');
    const gridColor  = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
    const labelColor = isDark ? '#9ca3af' : '#6b7280';

    let showManual = localStorage.getItem('dashShowManual') !== 'false';

    const manualBtn = document.getElementById('manual-toggle');
    function syncManualToggle() {
        if (!manualBtn) return;
        if (showManual) {
            manualBtn.classList.add('bg-indigo-100', 'dark:bg-indigo-900/40', 'text-indigo-700', 'dark:text-indigo-300', 'border-indigo-300', 'dark:border-indigo-600');
            manualBtn.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'text-gray-500', 'dark:text-gray-400', 'border-gray-300', 'dark:border-gray-600');
        } else {
            manualBtn.classList.remove('bg-indigo-100', 'dark:bg-indigo-900/40', 'text-indigo-700', 'dark:text-indigo-300', 'border-indigo-300', 'dark:border-indigo-600');
            manualBtn.classList.add('bg-gray-100', 'dark:bg-gray-700', 'text-gray-500', 'dark:text-gray-400', 'border-gray-300', 'dark:border-gray-600');
        }
    }
    syncManualToggle();
    if (manualBtn) {
        manualBtn.addEventListener('click', () => {
            showManual = !showManual;
            localStorage.setItem('dashShowManual', showManual);
            syncManualToggle();
            updateDashChart(dashRange);
        });
    }

    const demoMode = window.__dashCharts?.demoMode ?? false;

    function updateTiles(filtered, range) {
        if (!filtered.length) return;
        const last  = filtered[filtered.length - 1];
        const first = filtered[0];

        const mvEl = document.getElementById('tile-market-value');
        if (mvEl) mvEl.textContent = demoMode ? '••••' : fmtFull(last.value);

        const totEl = document.getElementById('tile-total-value');
        if (totEl) totEl.textContent = demoMode ? '••••' : fmtFull(last.value);

        const plEl    = document.getElementById('tile-pl-value');
        const plLabel = document.getElementById('tile-pl-label');
        if (plEl) {
            if (demoMode) {
                plEl.textContent = '••••';
            } else {
                const pl = last.value - first.value;
                plEl.textContent = (pl >= 0 ? '+$' : '-$') + Math.abs(pl).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                plEl.className   = 'mt-1 text-2xl font-semibold font-mono ' + (pl >= 0 ? 'text-green-600' : 'text-red-600');
            }
            if (plLabel) plLabel.textContent = range + ' Gain/Loss';
        }
    }

    let dashRange = '1Y';
    const dashChartEl = document.getElementById('dashChart');
    if (dashChartEl) {
        const dashChart = new Chart(dashChartEl, {
            type: 'line',
            data: {
                datasets: [
                    { label: 'Portfolio Value', data: [], borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.15)', fill: true,  tension: 0.3, borderWidth: 2, pointRadius: 0 },
                    { label: 'Cost Basis',      data: [], borderColor: '#94a3b8', borderDash: [5, 5],                        fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0 },
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

        function updateDashChart(range) {
            const filtered = filterByRange(showManual ? allDataFull : allDataMkt, range);
            dashChart.data.datasets[0].data = filtered.map(r => pointFromRow(r, 'value'));
            dashChart.data.datasets[1].data = filtered.map(r => pointFromRow(r, 'cost'));
            dashChart.update();
            activateBtn('#dash-range-btns', range);
            updateTiles(filtered, range);
        }

        document.querySelectorAll('#dash-range-btns button[data-range]').forEach(b =>
            b.addEventListener('click', () => { dashRange = b.dataset.range; updateDashChart(dashRange); })
        );
        updateDashChart(dashRange);
    }

    const benchEl = document.getElementById('benchmarkChart');
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
            const datasets = [{ label: 'My Portfolio', data: buildNorm(showManual ? allDataFull : allDataMkt, 'value', range), borderColor: '#6366f1', fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0 }];
            Object.keys(benchRaw).forEach(t => datasets.push({
                label: t, data: buildNorm(benchRaw[t] || [], 'price', range), borderColor: benchTickerColors[t] || '#9ca3af', fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0,
            }));
            benchChart.data.datasets = datasets;
            benchChart.update();
            activateBtn('#bench-range-btns', range);
        }

        document.querySelectorAll('#bench-range-btns button[data-range]').forEach(b =>
            b.addEventListener('click', () => { benchRange = b.dataset.range; updateBenchChart(benchRange); })
        );
        updateBenchChart(benchRange);
    }

    const donutEl = document.getElementById('allocationDonut');
    if (donutEl && allocData.total > 0) {
        new Chart(donutEl, {
            type: 'pie',
            data: {
                labels: allocData.labels,
                datasets: [{ data: allocData.values, backgroundColor: allocData.colors, borderWidth: 0 }],
            },
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

import {
    Chart,
    LineController, LineElement, PointElement, Filler,
    LinearScale, TimeScale, Tooltip, Legend,
    PieController, ArcElement,
} from 'chart.js';
import 'chartjs-adapter-date-fns';
import { resampleByRange, fmtFull, makeValueCostChart, makeBenchmarkChart, DEMO_MASK } from './chart-utils';

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

    const demoMode = window.__demoMode ?? false;

    function updateTiles(filtered, range) {
        if (!filtered.length) return;
        const last  = filtered[filtered.length - 1];
        const first = filtered[0];

        const tileValue = demoMode ? DEMO_MASK : fmtFull(last.value);
        const mvEl = document.getElementById('tile-market-value');
        if (mvEl) mvEl.textContent = tileValue;

        const totEl = document.getElementById('tile-total-value');
        if (totEl) totEl.textContent = tileValue;

        const plEl    = document.getElementById('tile-pl-value');
        const plLabel = document.getElementById('tile-pl-label');
        if (plEl) {
            if (demoMode) {
                plEl.textContent = DEMO_MASK;
            } else {
                const pl = last.value - first.value;
                plEl.textContent = (pl >= 0 ? '+$' : '-$') + Math.abs(pl).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                plEl.className   = 'mt-1 text-2xl font-semibold font-mono ' + (pl >= 0 ? 'text-green-600' : 'text-red-600');
            }
            if (plLabel) plLabel.textContent = range + ' Gain/Loss';
        }
    }

    const dashData = () => (showManual ? allDataFull : allDataMkt);

    const dashChart = makeValueCostChart({
        el: document.getElementById('dashChart'),
        gridColor, labelColor,
        valueLabel: 'Portfolio Value',
        data: dashData,
        btnsSel: '#dash-range-btns',
        transform: resampleByRange,
        onUpdate: updateTiles,
        autoTimeUnit: true,
    });

    if (manualBtn && dashChart) {
        manualBtn.addEventListener('click', () => {
            showManual = !showManual;
            localStorage.setItem('dashShowManual', showManual);
            syncManualToggle();
            dashChart.refresh();
        });
    }

    makeBenchmarkChart({
        el: document.getElementById('benchmarkChart'),
        gridColor, labelColor,
        selfLabel: 'My Portfolio',
        selfData: dashData,
        benchRaw,
        btnsSel: '#bench-range-btns',
    });

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

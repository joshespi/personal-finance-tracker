import {
    Chart,
    LineController, LineElement, PointElement, Filler,
    LinearScale, TimeScale, Tooltip, Legend,
    PieController, ArcElement,
} from 'chart.js';
import 'chartjs-adapter-date-fns';
import { makeValueCostChart, makeBenchmarkChart, slicePalette, DEMO_MASK } from './chart-utils';

Chart.register(LineController, LineElement, PointElement, Filler, LinearScale, TimeScale, Tooltip, Legend, PieController, ArcElement);

document.addEventListener('DOMContentLoaded', function () {
    const { chartData: allData, benchmarkData: benchRaw, allocation: allocData } = window.__portCharts ?? {};
    const demoMode = window.__demoMode ?? false;
    if (!allData) return;

    const isDark     = document.documentElement.classList.contains('dark');
    const gridColor  = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
    const labelColor = isDark ? '#9ca3af' : '#6b7280';

    makeValueCostChart({
        el: document.getElementById('portChart'),
        gridColor, labelColor,
        valueLabel: 'Market Value',
        data: allData,
        btnsSel: '#port-range-btns',
    });

    makeBenchmarkChart({
        el: document.getElementById('portBenchChart'),
        gridColor, labelColor,
        selfLabel: 'This Portfolio',
        selfData: allData,
        benchRaw,
        btnsSel: '#port-bench-range-btns',
    });

    const donutEl = document.getElementById('portAllocationDonut');
    if (donutEl && allocData.total > 0) {
        const labels = allocData.holdings.map(h => h.symbol);
        const values = allocData.holdings.map(h => h.value);
        if (allocData.manual_value > 0) {
            labels.push('Manual');
            values.push(allocData.manual_value);
        }
        const colors = values.map((_, i) => slicePalette[i % slicePalette.length]);
        new Chart(donutEl, {
            type: 'pie',
            data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => `${ctx.label}: ${demoMode ? DEMO_MASK : '$' + ctx.parsed.toLocaleString('en-US', { minimumFractionDigits: 2 })}` } },
                },
            },
        });
    }
});

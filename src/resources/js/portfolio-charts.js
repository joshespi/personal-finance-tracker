import { makeValueCostChart, makeBenchmarkChart, makeAllocationPie, themeColors, slicePalette } from './chart-utils';

document.addEventListener('DOMContentLoaded', function () {
    const { chartData: allData, benchmarkData: benchRaw, allocation: allocData } = window.__portCharts ?? {};
    const demoMode = window.__demoMode ?? false;
    if (!allData) return;

    const { gridColor, labelColor } = themeColors();

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
        makeAllocationPie({ el: donutEl, labels, values, colors, demoMode });
    }
});

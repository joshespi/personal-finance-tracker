import { themeColors } from './chart-utils';

// Monthly dividends bar chart. Reads its data from window.__dividendsChart, a plain
// JSON blob set inline by the Blade view.
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('dividendChart');
    if (!el) return; // no chart to draw in the "no dividends yet" empty state

    const cfg = window.__dividendsChart ?? {};
    const monthData = cfg.byMonth ?? [];
    const labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const { gridColor, labelColor: textColor } = themeColors();

    new Chart(el, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Dividends',
                data: monthData,
                backgroundColor: 'rgba(99,102,241,0.7)',
                borderRadius: 4,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: { label: ctx => '$' + ctx.parsed.y.toFixed(2) },
                },
            },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: textColor } },
                y: {
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        callback: v => '$' + v.toFixed(0),
                    },
                    beginAtZero: true,
                },
            },
        },
    });
});

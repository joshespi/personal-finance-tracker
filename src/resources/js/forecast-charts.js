import { themeColors } from './chart-utils';
import { fmtFull, fmtK } from './format-utils';

// FIRE forecast nominal-vs-real projection line chart (the "trajectory" mode of the
// Retirement tab on /planning). Reads its data from window.__forecastChart, a plain
// JSON blob set inline by the Blade view. Uses the shared fmtFull/fmtK formatters
// (previously reimplemented inline here) so the axis and tooltip read the same as
// every other money chart in the app.
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('forecastChart');
    if (!el) return;

    const cfg        = window.__forecastChart ?? {};
    const projection = cfg.projection ?? [];
    const { isDark, gridColor, labelColor } = themeColors();

    new Chart(el, {
        type: 'line',
        data: {
            labels: projection.map(p => p.label),
            datasets: [
                {
                    label: 'Nominal',
                    data: projection.map(p => p.nominal),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                },
                {
                    label: 'Real (inflation-adjusted)',
                    data: projection.map(p => p.real),
                    borderColor: isDark ? '#6b7280' : '#9ca3af',
                    borderDash: [6, 4],
                    fill: false,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': ' + fmtFull(ctx.raw),
                    },
                },
            },
            scales: {
                x: {
                    grid: { color: gridColor },
                    ticks: { color: labelColor, maxTicksLimit: 10 },
                },
                y: {
                    grid: { color: gridColor },
                    ticks: { color: labelColor, callback: fmtK },
                },
            },
        },
    });
});

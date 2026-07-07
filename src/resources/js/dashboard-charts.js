import {
    Chart,
    LineController, LineElement, PointElement, Filler,
    LinearScale, TimeScale, Tooltip, Legend,
    PieController, ArcElement,
} from 'chart.js';
import 'chartjs-adapter-date-fns';
import {
    resampleByRange, fmtFull, fmtSigned, makeValueCostChart, makeBenchmarkChart, DEMO_MASK,
    dailyChangeMap, buildCalendarWeeks, calendarColor,
} from './chart-utils';

// Only the trailing window is ever rendered in the calendar heatmap; bounding
// the rows passed to it keeps that cost from growing with total account
// history (years of daily snapshots).
const CALENDAR_WINDOW_DAYS = 365;

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
                plEl.textContent = fmtSigned(pl);
                plEl.className   = 'mt-1 text-2xl font-semibold font-mono ' + (pl >= 0 ? 'text-green-600' : 'text-red-600');
            }
            if (plLabel) plLabel.textContent = range + ' Gain/Loss';
        }
    }

    const dashData = () => (showManual ? allDataFull : allDataMkt);

    function renderCalendarLegend(el) {
        if (!el) return;
        el.innerHTML = '';
        const parts = ['Loss', -2, -0.75, 0, 0.75, 2, 'Gain'];
        parts.forEach(p => {
            if (typeof p === 'string') {
                const label = document.createElement('span');
                label.textContent = p;
                el.appendChild(label);
                return;
            }
            const sw = document.createElement('span');
            sw.className = 'w-[10px] h-[10px] rounded-sm inline-block';
            sw.style.background = calendarColor(p, isDark);
            el.appendChild(sw);
        });
    }

    function labelCell(className, text) {
        const el = document.createElement('div');
        el.className = className;
        if (text) el.textContent = text;
        return el;
    }

    // DOMContentLoaded has already fired by this point, so the tooltip node
    // can be created once up front instead of lazily on first render.
    const calendarTooltip = document.createElement('div');
    calendarTooltip.className = 'fixed z-50 hidden pointer-events-none px-2.5 py-1.5 rounded-md text-xs font-medium shadow-lg whitespace-nowrap bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900';
    document.body.appendChild(calendarTooltip);

    // dashData() only ever returns allDataFull or allDataMkt, which are fixed
    // for the life of the page — cache the day-diff/week-grid per toggle
    // state so switching back and forth doesn't re-bucket ~365 rows each time.
    const calendarDataCache = new Map();
    function getCalendarData() {
        if (calendarDataCache.has(showManual)) return calendarDataCache.get(showManual);

        const rows = dashData();
        let data = null;
        if (rows && rows.length >= 2) {
            const windowed = rows.slice(-(CALENDAR_WINDOW_DAYS + 1));
            data = { changes: dailyChangeMap(windowed), ...buildCalendarWeeks(windowed, CALENDAR_WINDOW_DAYS) };
        }

        calendarDataCache.set(showManual, data);
        return data;
    }

    function renderCalendarHeatmap() {
        const container = document.getElementById('calendarHeatmap');
        if (!container) return;

        const data = getCalendarData();
        container.innerHTML = '';
        if (!data) {
            container.innerHTML = '<p class="text-sm text-gray-400 dark:text-gray-500">Not enough data yet.</p>';
            return;
        }

        const { changes, weeks, monthLabels } = data;

        const wrap = document.createElement('div');
        wrap.className = 'inline-flex flex-col gap-1 min-w-max';

        const monthRow = document.createElement('div');
        monthRow.className = 'flex gap-[3px] pl-[18px]';
        weeks.forEach((_, idx) => {
            const label = monthLabels.find(m => m.weekIndex === idx);
            monthRow.appendChild(labelCell('w-[11px] text-[10px] text-gray-400 dark:text-gray-500', label?.label));
        });
        wrap.appendChild(monthRow);

        const gridRow = document.createElement('div');
        gridRow.className = 'flex gap-[3px]';

        const dayLabels = document.createElement('div');
        dayLabels.className = 'flex flex-col gap-[3px] pr-1';
        ['', 'Mon', '', 'Wed', '', 'Fri', ''].forEach(t => {
            dayLabels.appendChild(labelCell('w-[18px] h-[11px] text-[10px] leading-[11px] text-gray-400 dark:text-gray-500', t));
        });
        gridRow.appendChild(dayLabels);

        weeks.forEach(week => {
            const col = document.createElement('div');
            col.className = 'flex flex-col gap-[3px]';
            week.forEach(dateStr => {
                const cell = document.createElement('div');
                cell.className = 'w-[11px] h-[11px] rounded-sm';
                if (!dateStr) {
                    cell.style.background = 'transparent';
                    col.appendChild(cell);
                    return;
                }

                cell.style.background = calendarColor(changes.get(dateStr)?.pct, isDark);
                cell.dataset.date = dateStr;

                col.appendChild(cell);
            });
            gridRow.appendChild(col);
        });

        // One delegated listener for the whole grid instead of 3 per cell (~1095
        // closures) — cheaper to build and GC on every render/toggle.
        gridRow.addEventListener('mousemove', e => {
            const cell = e.target.closest('[data-date]');
            if (!cell) {
                calendarTooltip.classList.add('hidden');
                return;
            }

            const dateStr = cell.dataset.date;
            const change  = changes.get(dateStr);
            const dateLabel = new Date(dateStr + 'T00:00:00Z')
                .toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });

            if (!change) {
                calendarTooltip.textContent = `${dateLabel}: no data`;
            } else {
                const dollarStr = demoMode ? DEMO_MASK : fmtSigned(change.dollar);
                const pctStr = (change.pct >= 0 ? '+' : '') + change.pct.toFixed(2) + '%';
                calendarTooltip.textContent = `${dateLabel}: ${dollarStr} (${pctStr})`;
            }

            calendarTooltip.classList.remove('hidden');
            calendarTooltip.style.left = (e.clientX + 12) + 'px';
            calendarTooltip.style.top  = (e.clientY + 12) + 'px';
        });
        gridRow.addEventListener('mouseleave', () => calendarTooltip.classList.add('hidden'));

        wrap.appendChild(gridRow);
        container.appendChild(wrap);
    }

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
            renderCalendarHeatmap();
        });
    }

    // The legend only depends on isDark, which is fixed for the life of the
    // page, so it's rendered once rather than on every calendar re-render.
    renderCalendarLegend(document.getElementById('calendarHeatmapLegend'));
    renderCalendarHeatmap();

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

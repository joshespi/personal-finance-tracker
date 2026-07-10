import {
    Chart,
    LineController, LineElement, PointElement, Filler,
    LinearScale, TimeScale, Tooltip, Legend,
    PieController, ArcElement,
} from 'chart.js';
import 'chartjs-adapter-date-fns';
import {
    resampleByRange, fmtFull, fmtSigned, makeValueCostChart, makeBenchmarkChart, DEMO_MASK,
    dailyChangeMap, buildCalendarMonths, calendarColor,
} from './chart-utils';

// Months shown per page of the calendar heatmap; prev/next pages through
// history one month at a time from there.
const CALENDAR_MONTHS_PER_PAGE = 12;

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
    // for the life of the page — cache the day-diff map per toggle state so
    // paging through months doesn't recompute it on every click. It's keyed
    // off the full series (not the visible window) since the pager can land
    // on any month, and diffing a few thousand rows is trivial either way.
    const calendarChangesCache = new Map();
    function getCalendarChanges() {
        if (calendarChangesCache.has(showManual)) return calendarChangesCache.get(showManual);

        const rows = dashData();
        const changes = rows && rows.length >= 2 ? dailyChangeMap(rows) : null;

        calendarChangesCache.set(showManual, changes);
        return changes;
    }

    function getCalendarBounds() {
        const rows = dashData();
        if (!rows || rows.length < 2) return null;
        const first = new Date(rows[0].date + 'T00:00:00Z');
        const last  = new Date(rows[rows.length - 1].date + 'T00:00:00Z');
        return {
            min: { year: first.getUTCFullYear(), month: first.getUTCMonth() },
            max: { year: last.getUTCFullYear(), month: last.getUTCMonth() },
        };
    }

    const monthIndex = m => m.year * 12 + m.month;
    function shiftMonth(m, delta) {
        const idx = monthIndex(m) + delta;
        return { year: Math.floor(idx / 12), month: ((idx % 12) + 12) % 12 };
    }

    // Last month shown in the calendar pager; null until the first render
    // seeds it from the latest data date. Persists across the manual-asset
    // toggle so switching it doesn't reset whatever month the user paged to.
    let calendarWindowEnd = null;
    function clampCalendarWindow(bounds) {
        if (!calendarWindowEnd || monthIndex(calendarWindowEnd) > monthIndex(bounds.max)) {
            calendarWindowEnd = bounds.max;
        } else if (monthIndex(calendarWindowEnd) < monthIndex(bounds.min)) {
            calendarWindowEnd = bounds.min;
        }
    }

    const calendarPrevBtn    = document.getElementById('calendarPrevBtn');
    const calendarNextBtn    = document.getElementById('calendarNextBtn');
    const calendarRangeLabel = document.getElementById('calendarRangeLabel');

    function updateCalendarNav(bounds, months) {
        if (!calendarPrevBtn || !calendarNextBtn || !calendarRangeLabel) return;

        if (!bounds) {
            calendarRangeLabel.textContent = '';
            calendarPrevBtn.disabled = true;
            calendarNextBtn.disabled = true;
            return;
        }

        const first = months[0];
        const last  = months[months.length - 1];
        calendarRangeLabel.textContent = first.year === last.year
            ? `${first.label} – ${last.label} ${last.year}`
            : `${first.label} ${first.year} – ${last.label} ${last.year}`;

        calendarPrevBtn.disabled = monthIndex(first) <= monthIndex(bounds.min);
        calendarNextBtn.disabled = monthIndex(calendarWindowEnd) >= monthIndex(bounds.max);
    }

    if (calendarPrevBtn) {
        calendarPrevBtn.addEventListener('click', () => {
            if (calendarPrevBtn.disabled) return;
            calendarWindowEnd = shiftMonth(calendarWindowEnd, -1);
            renderCalendarHeatmap();
        });
    }
    if (calendarNextBtn) {
        calendarNextBtn.addEventListener('click', () => {
            if (calendarNextBtn.disabled) return;
            calendarWindowEnd = shiftMonth(calendarWindowEnd, 1);
            renderCalendarHeatmap();
        });
    }

    function renderCalendarHeatmap() {
        const container = document.getElementById('calendarHeatmap');
        if (!container) return;

        const changes = getCalendarChanges();
        const bounds  = getCalendarBounds();

        if (!changes || !bounds) {
            container.innerHTML = '<p class="text-sm text-gray-400 dark:text-gray-500">Not enough data yet.</p>';
            updateCalendarNav(null);
            return;
        }

        clampCalendarWindow(bounds);
        const months = buildCalendarMonths(calendarWindowEnd.year, calendarWindowEnd.month, CALENDAR_MONTHS_PER_PAGE);

        container.innerHTML = '';

        const wrap = document.createElement('div');
        wrap.className = 'inline-flex flex-col gap-1 min-w-max';

        const monthRow = document.createElement('div');
        monthRow.className = 'flex gap-3';
        // Mirrors gridRow's own leading spacer (dayLabels, w-[18px]) + gap-3 so month
        // labels line up with their grid columns instead of drifting by the gap width.
        monthRow.appendChild(labelCell('w-[18px]'));
        months.forEach(m => {
            const label = labelCell('text-[10px] text-gray-400 dark:text-gray-500', m.label);
            label.style.width = (m.weeks.length * 14 - 3) + 'px';
            monthRow.appendChild(label);
        });
        wrap.appendChild(monthRow);

        const gridRow = document.createElement('div');
        gridRow.className = 'flex gap-3';

        const dayLabels = document.createElement('div');
        dayLabels.className = 'flex flex-col gap-[3px] pr-1';
        ['', 'Mon', '', 'Wed', '', 'Fri', ''].forEach(t => {
            dayLabels.appendChild(labelCell('w-[18px] h-[11px] text-[10px] leading-[11px] text-gray-400 dark:text-gray-500', t));
        });
        gridRow.appendChild(dayLabels);

        months.forEach(m => {
            const monthBlock = document.createElement('div');
            monthBlock.className = 'flex gap-[3px]';
            m.weeks.forEach(week => {
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
                monthBlock.appendChild(col);
            });
            gridRow.appendChild(monthBlock);
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

        updateCalendarNav(bounds, months);
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

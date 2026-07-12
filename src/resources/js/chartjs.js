import {
    Chart,
    LineController,
    BarController,
    LineElement,
    BarElement,
    PointElement,
    Filler,
    LinearScale,
    CategoryScale,
    Tooltip,
    Legend,
    PieController,
    ArcElement,
} from 'chart.js';
import { themeColors } from './chart-utils';

// window.Chart/window.themeColors for the legacy report pages (analysis,
// cashflow, debt-payoff, dividends, forecast, planning, spending-trends) that
// build charts from inline <script> blocks instead of a Vite-bundled entry.
// Loaded via the 'head-vite' stack so it executes (as a deferred module)
// before Alpine's init() lifecycle, which fires on 'alpine:init'/
// DOMContentLoaded — after every deferred module script, including this one,
// has already run.
Chart.register(
    LineController,
    BarController,
    LineElement,
    BarElement,
    PointElement,
    Filler,
    LinearScale,
    CategoryScale,
    Tooltip,
    Legend,
    PieController,
    ArcElement,
);

window.Chart = Chart;
window.themeColors = themeColors;

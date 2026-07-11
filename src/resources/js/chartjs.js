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

// window.Chart for the legacy report pages (analysis, cashflow, debt-payoff,
// dividends, forecast, planning, spending-trends) that build charts from
// inline <script> blocks instead of a Vite-bundled entry. Loaded via the
// 'head-vite' stack so it executes (as a deferred module) before app.js and
// its own Alpine.start() call.
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

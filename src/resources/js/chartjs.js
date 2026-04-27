import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    Filler,
    LinearScale,
    TimeScale,
    Tooltip,
    Legend,
    PieController,
    ArcElement,
} from 'chart.js';
import 'chartjs-adapter-date-fns';

Chart.register(
    LineController,
    LineElement,
    PointElement,
    Filler,
    LinearScale,
    TimeScale,
    Tooltip,
    Legend,
    PieController,
    ArcElement,
);

window.Chart = Chart;

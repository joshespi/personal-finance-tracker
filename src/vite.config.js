import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/chartjs.js',
                'resources/js/dashboard-charts.js',
                'resources/js/portfolio-charts.js',
                'resources/js/analysis-charts.js',
                'resources/js/planning-charts.js',
                'resources/js/forecast-charts.js',
                'resources/js/dividends-charts.js',
                'resources/js/editor.js',
                'resources/js/donate.js',
            ],
            refresh: true,
        }),
    ],

});

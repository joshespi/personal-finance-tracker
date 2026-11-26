import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                // Shared accent and surface scales used throughout the Blade views.
                indigo: {
                    50: '#eaf5f4', 100: '#d2e9e7', 200: '#aad6d3', 300: '#83c4c2',
                    400: '#59abaa', 500: '#368b8e', 600: '#246e73', 700: '#205c61',
                    800: '#1c494e', 900: '#17393e', 950: '#10282c',
                },
                gray: {
                    50: '#f7f8f5', 100: '#edf0ec', 200: '#dbe1dc', 300: '#bdc9c3',
                    400: '#82958d', 500: '#61746c', 600: '#4d6059', 700: '#354943',
                    800: '#20332e', 900: '#14231f', 950: '#101b18',
                },
            },
            borderRadius: { md: '0.5rem', lg: '0.875rem' },
            boxShadow: {
                sm: '0 0 0 1px rgb(64 91 77 / 0.14), 0 3px 12px -6px rgb(26 50 40 / 0.14)',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};

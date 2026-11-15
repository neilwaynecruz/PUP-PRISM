import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        host: 'localhost',
        hmr: {
            host: 'localhost',
        },
        warmup: {
            clientFiles: [
                'resources/js/app.ts',
                'resources/css/app.css',
                'resources/js/layouts/AppLayout.vue',
                'resources/js/pages/Dashboard.vue',
            ],
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
    build: {
        rolldownOptions: {
            output: {
                codeSplitting: {
                    groups: [
                        {
                            name: 'fullcalendar',
                            test: /node_modules[\\/]@fullcalendar/,
                        },
                        {
                            name: 'chartjs',
                            test: /node_modules[\\/]chart\.js/,
                        },
                        {
                            name: 'reka-ui',
                            test: /node_modules[\\/]reka-ui/,
                        },
                    ],
                },
            },
        },
    },
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import copy from 'rollup-plugin-copy';

export default defineConfig(({mode}) => {
    return {
        server: {
            cors: mode === "development",
            watch: {
                ignored: [
                    '**/node_modules/**',
                    '**/vendor/**',
                    '**/public/**',
                    '!**/vendor/seatplus/web/**'
                ],
            }
        },
        plugins: [
            {
                ...copy({
                         targets: [{
                             src: 'vendor/seatplus/web/resources/js',
                             dest: 'resources'
                         }],
                         verbose: true,
                         overwrite: true
                     }),
                apply: 'serve',
            },
            laravel({
                input: 'resources/js/app.js',
                refresh: ['resources/js/**', 'vendor/seatplus/web/resources/js/**'],
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },
    }
});
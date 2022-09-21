import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import run from 'vite-plugin-run';

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
            run([
                {
                    startup: false,
                    name: 'copy vendor',
                    run: ['php', 'artisan', 'vendor:publish', '--tag=web', '--force'],
                    condition: (file) => file.includes('vendor/seatplus/web/resources/js/'),
                }
            ])
        ],
        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },
    }
});
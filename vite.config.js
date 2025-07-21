import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.jsx',
                'resources/js/admin/users.js',
                'resources/js/admin/datasets.js',
                'resources/js/admin/dashboard.js',
                'resources/js/admin/buildings.js',
                'resources/js/admin/audit-logs.js',
                'resources/js/admin/analysis-jobs.js',
                'resources/js/admin/entitlements.js'
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
});

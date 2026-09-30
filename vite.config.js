import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: [
                // 'resources/sass/app.scss',
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/inertia.jsx',
                'resources/js/meeting-room.jsx',
            ],
            refresh: true,
        }),
    ],
});

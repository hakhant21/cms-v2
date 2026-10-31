import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/motion.css',
                'resources/js/app.js',
                // Its own entry, loaded only where the chat bubble is rendered:
                // laravel-echo and pusher-js are most of the broadcast layer and
                // a page without a chat has no business shipping them.
                'resources/js/echo.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin.js'],
            refresh: true,
            fonts: [
                // Preload only the weights the first screen needs (body 400, labels 600, headings 800);
                // LocaleAwareVite keeps the preloads of the current script direction.
                bunny('Plus Jakarta Sans', { weights: [400, 500, 600, 700, 800], preload: [{ weight: 400 }, { weight: 600 }, { weight: 800 }] }),
                bunny('Cairo', { weights: [400, 600, 700, 800], preload: [{ weight: 400 }, { weight: 600 }, { weight: 800 }] }),
                bunny('JetBrains Mono', { weights: [400], preload: false }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

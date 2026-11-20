import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { cmsAssets } from './vendor/dbravoan/dbcms-core/resources/vite.mjs';

const cms = cmsAssets();
export default defineConfig({
    plugins: [cms.plugin, laravel({ input: ['resources/css/app.css', 'resources/js/app.js', ...cms.inputs], refresh: false }), tailwindcss()],
    resolve: { alias: cms.aliases, preserveSymlinks: true },
    build: {
        reportCompressedSize: false,
        target: ['es2020', 'edge88', 'firefox78', 'chrome87', 'safari14'],
    },
    server: { host: '0.0.0.0', port: 5173, hmr: { host: 'localhost' } },
});

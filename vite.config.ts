import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import { google } from 'laravel-vite-plugin/fonts'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import { resolve } from 'node:path'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            // Downloaded at build time and served from this origin — no
            // third-party font request at runtime, and the plugin's fallback
            // metrics keep the first paint from shifting.
            fonts: [
                google('Instrument Serif', { weights: [400], styles: ['normal', 'italic'] }),
                google('Inter', { weights: [400, 500, 600, 700] }),
            ],
        }),
        react(),
        tailwindcss(),
        VitePWA({
            srcDir: 'resources/js',
            filename: 'sw.ts',
            strategies: 'injectManifest',
            injectRegister: false,
            manifest: false,
            // The manifest is served by Laravel at /manifest.webmanifest.
            injectManifest: {
                // Precache only the shell: the entry chunk, the shared vendor
                // chunks, styles and fonts. Every other chunk — including the
                // whole admin editor — is content-hashed and picked up by the
                // service worker's cache-first rule the first time it is used,
                // so a visitor who only reads recipes never downloads or
                // stores the admin bundle. HTML is never precached.
                globDirectory: 'public/build',
                globPatterns: [
                    'assets/app-*.js',
                    'assets/inertia-*.js',
                    'assets/react-*.js',
                    'assets/rolldown-runtime-*.js',
                    'assets/*.css',
                    'assets/*.woff2',
                ],
                globIgnores: ['**/node_modules/**'],
                manifestTransforms: [
                    (entries) => ({
                        manifest: entries.map((entry) => ({
                            ...entry,
                            url: `build/${entry.url}`,
                        })),
                        warnings: [],
                    }),
                ],
            },
            devOptions: { enabled: false },
            outDir: 'public',
        }),
    ],
    resolve: {
        alias: {
            '@': resolve(import.meta.dirname, 'resources/js'),
        },
    },
    build: {
        // Source maps would double the size of what the home server ships.
        sourcemap: false,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    // Keep the framework in its own long-lived chunk so a
                    // content change does not invalidate 60 KB of React.
                    if (id.includes('node_modules/react') || id.includes('node_modules/scheduler')) {
                        return 'react'
                    }
                    if (id.includes('node_modules/@inertiajs')) {
                        return 'inertia'
                    }
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/storage/app/**'],
        },
    },
})

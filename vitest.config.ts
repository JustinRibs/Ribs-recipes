import { defineConfig } from 'vitest/config'
import { resolve } from 'node:path'

export default defineConfig({
    resolve: {
        alias: { '@': resolve(import.meta.dirname, 'resources/js') },
    },
    test: {
        // Pure logic only. Anything that needs a browser is covered by the
        // Playwright suite in tests/e2e, which drives the real application.
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
})

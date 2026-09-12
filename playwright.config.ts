import { defineConfig, devices } from '@playwright/test'

/**
 * End-to-end tests run against a real application on a real SQLite file, so
 * they exercise the same code an iPhone would. The default project is an
 * iPhone-sized viewport, because that is the experience this site is built
 * for; a desktop project runs the same specs at 1440px.
 */
const PORT = Number(process.env.E2E_PORT ?? 8321)
const BASE_URL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${PORT}`

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['github'], ['list']] : [['list']],
    timeout: 30_000,
    expect: { timeout: 7_000 },

    use: {
        baseURL: BASE_URL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'mobile',
            use: { ...devices['iPhone 14 Pro'] },
        },
        {
            name: 'desktop',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
        },
    ],

    webServer: {
        // The environment is handed over as APP_ENV, not as `artisan serve
        // --env=e2e`. That flag configures the artisan process; the PHP
        // built-in server it spawns bootstraps the application again on every
        // request, and Laravel forwards only an allow-list of variables to
        // those workers (ServeCommand::$passthroughVariables) — APP_ENV among
        // them. With the flag alone, served requests quietly fell back to .env
        // and the development database, which is precisely the isolation this
        // suite exists to have.
        //
        // APP_ENV=e2e makes each worker load .env.e2e, which points at
        // database/e2e.sqlite.
        //
        // tests/e2e/prepare.mjs must have run first — `npm run test:e2e` does
        // that. Playwright starts this server before any globalSetup, so the
        // environment file and the database have to exist by now.
        command: `php artisan serve --port=${PORT}`,
        env: { APP_ENV: 'e2e' },
        url: BASE_URL,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        // Piped, not ignored: when the server refuses to boot, its output is
        // the only thing that explains why.
        stdout: 'pipe',
        stderr: 'pipe',
    },
})

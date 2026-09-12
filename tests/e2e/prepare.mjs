/**
 * Prepares everything the end-to-end suite needs, before Playwright starts.
 *
 * This runs as its own step rather than as Playwright's `globalSetup`, because
 * Playwright launches `webServer` *first* and only then calls globalSetup — so
 * a fresh checkout would start `artisan serve` against an environment file and
 * a database that did not exist yet. That is invisible on a machine where both
 * already exist, and fatal on a CI runner.
 *
 *   node tests/e2e/prepare.mjs
 */

import { execFileSync } from 'node:child_process'
import { copyFileSync, existsSync, readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve(import.meta.dirname, '../..')

function artisan(...args) {
    execFileSync('php', ['artisan', ...args, '--env=e2e'], { cwd: root, stdio: 'inherit' })
}

const envPath = resolve(root, '.env.e2e')

// .env.e2e is git-ignored and gains a generated key below; only the blank
// template is tracked, so a key can never reach a commit.
if (!existsSync(envPath)) {
    copyFileSync(resolve(root, '.env.e2e.example'), envPath)
    console.log('Created .env.e2e from the template.')
}

if (/^APP_KEY=\s*$/m.test(readFileSync(envPath, 'utf8'))) {
    artisan('key:generate', '--force')
}

if (!existsSync(resolve(root, 'public/build/manifest.json'))) {
    console.error('Front-end assets are missing. Run `npm run build` first.')
    process.exit(1)
}

const database = resolve(root, 'database/e2e.sqlite')

if (!existsSync(database)) {
    writeFileSync(database, '')
}

// Its own SQLite file, so a test run can never touch the development
// database — and every spec starts from exactly the same collection, which is
// what lets them assert on specific recipes.
artisan('migrate:fresh', '--seed', '--force')

// Seeded photos are served from public/storage, which is a symlink that does
// not survive a fresh clone.
artisan('storage:link', '--force')

console.log('End-to-end environment ready.')

import { execFileSync } from 'node:child_process'
import { copyFileSync, existsSync, readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve(import.meta.dirname, '../..')

function artisan(...args: string[]): void {
    execFileSync('php', ['artisan', ...args, '--env=e2e'], { cwd: root, stdio: 'inherit' })
}

/**
 * Prepares a clean, seeded database for the suite.
 *
 * Runs against .env.e2e and its own SQLite file, so a test run can never touch
 * the development database — and every spec starts from exactly the same
 * collection, which is what lets them assert on specific recipes.
 *
 * .env.e2e is created here from the committed template and is git-ignored,
 * because this function generates an APP_KEY into it. Writing a generated key
 * into a tracked file is how one reached a public repository once already.
 */
export default function globalSetup(): void {
    const envPath = resolve(root, '.env.e2e')

    if (!existsSync(envPath)) {
        copyFileSync(resolve(root, '.env.e2e.example'), envPath)
    }

    if (/^APP_KEY=\s*$/m.test(readFileSync(envPath, 'utf8'))) {
        artisan('key:generate', '--force')
    }

    if (!existsSync(resolve(root, 'public/build/manifest.json'))) {
        throw new Error('Front-end assets are missing. Run `npm run build` before the end-to-end suite.')
    }

    const database = resolve(root, 'database/e2e.sqlite')

    if (!existsSync(database)) {
        writeFileSync(database, '')
    }

    artisan('migrate:fresh', '--seed', '--force')
}

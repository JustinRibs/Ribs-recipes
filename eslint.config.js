import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import tseslint from 'typescript-eslint'

/**
 * Deliberately small. `tsc --noEmit` runs with strict, noUnusedLocals,
 * noUnusedParameters and noUncheckedIndexedAccess, so it already catches most
 * of what a large rule set would. What it cannot see is the rules of hooks,
 * which is the main reason ESLint is here at all.
 */
export default tseslint.config(
    {
        ignores: [
            'public/**',
            'vendor/**',
            'node_modules/**',
            'bootstrap/ssr/**',
            'storage/**',
            'test-results/**',
            'playwright-report/**',
        ],
    },

    js.configs.recommended,
    ...tseslint.configs.recommended,
    reactHooks.configs.flat['recommended-latest'],

    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            ecmaVersion: 2023,
            globals: { ...globals.browser, ...globals.serviceworker },
            parserOptions: {
                ecmaFeatures: { jsx: true },
            },
        },
        rules: {
            // An underscore prefix is the established way to say "deliberately
            // unused" — a discarded callback argument, a caught-and-ignored
            // error.
            '@typescript-eslint/no-unused-vars': [
                'error',
                { argsIgnorePattern: '^_', varsIgnorePattern: '^_', caughtErrors: 'none' },
            ],
        },
    },

    {
        files: ['*.config.{ts,js}', 'tests/e2e/**/*.ts', 'vitest.config.ts'],
        languageOptions: {
            globals: { ...globals.node },
        },
    },
)

import '../css/app.css'

import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react'
import { createRoot, hydrateRoot } from 'react-dom/client'
import { StrictMode } from 'react'
import { TimerProvider } from '@/hooks/useTimers'
import { registerServiceWorker } from '@/lib/pwa'

const appName = import.meta.env.VITE_APP_NAME ?? 'Ribs Recipes'

const pages = import.meta.glob<ResolvedComponent>('./pages/**/*.tsx')

void createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),

    // Every page is a separate lazily-loaded chunk, so a visitor who only
    // ever reads recipes never downloads the admin editor.
    resolve: (name) => {
        const page = pages[`./pages/${name}.tsx`]

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`)
        }

        return page()
    },

    setup({ el, App, props }) {
        if (!el) {
            throw new Error('Inertia root element is missing')
        }

        const tree = (
            <StrictMode>
                <TimerProvider>
                    <App {...props} />
                </TimerProvider>
            </StrictMode>
        )

        if (el.hasChildNodes()) {
            hydrateRoot(el, tree)
        } else {
            createRoot(el).render(tree)
        }
    },

    progress: {
        color: '#2F6F9F',
        // Short delay so instant navigations never flash a progress bar.
        delay: 180,
        showSpinner: false,
    },
})

registerServiceWorker()

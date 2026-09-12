import { router, useForm } from '@inertiajs/react'
import { AlertTriangle, RefreshCw, Save, ShieldCheck, ShieldX } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { TextAreaField, TextField } from '@/components/ui/Field'
import { AdminLayout } from '@/layouts/AdminLayout'
import { adminRoutes } from '@/lib/routes'

interface SettingsProps {
    settings: {
        tagline: string
        hero_heading: string
        hero_subheading: string
        footer_note: string
    }
    system: {
        appVersion: string
        environment: string
        searchDriver: string
        accessConfigured: boolean
        teamDomain: string | null
        devBypass: boolean
        storedImages: number
        remoteImages: number
        unattachedImages: number
    }
    users: { id: number; name: string; email: string; role: string; lastSeenAt: string | null }[]
}

interface SettingsFormShape {
    tagline: string
    hero_heading: string
    hero_subheading: string
    footer_note: string
    [key: string]: string
}

export default function Settings({ settings, system, users }: SettingsProps) {
    const { data, setData, put, processing, errors } = useForm<SettingsFormShape>({ ...settings })

    const submit = (event: React.FormEvent) => {
        event.preventDefault()
        put(adminRoutes.settings, { preserveScroll: true })
    }

    return (
        <AdminLayout title="Settings">
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-2xl bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-line sm:p-6"
                >
                    <h2 className="font-display text-[1.35rem] tracking-tight text-ink">Site copy</h2>

                    <TextField
                        label="Tagline"
                        value={data.tagline}
                        onChange={(event) => setData('tagline', event.target.value)}
                        error={errors.tagline}
                        hint="Appears under the logo and in the footer."
                    />

                    <TextField
                        label="Homepage heading"
                        value={data.hero_heading}
                        onChange={(event) => setData('hero_heading', event.target.value)}
                        error={errors.hero_heading}
                    />

                    <TextAreaField
                        label="Homepage subheading"
                        value={data.hero_subheading}
                        onChange={(event) => setData('hero_subheading', event.target.value)}
                        error={errors.hero_subheading}
                        rows={2}
                    />

                    <TextAreaField
                        label="Footer note"
                        value={data.footer_note}
                        onChange={(event) => setData('footer_note', event.target.value)}
                        error={errors.footer_note}
                        rows={3}
                    />

                    <Button type="submit" disabled={processing}>
                        <Save className="size-4" aria-hidden="true" />
                        {processing ? 'Saving…' : 'Save settings'}
                    </Button>
                </form>

                <div className="space-y-6">
                    {/* --- Access -------------------------------------------- */}
                    <section className="rounded-2xl bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-line sm:p-6">
                        <h2 className="font-display text-[1.35rem] tracking-tight text-ink">Admin access</h2>

                        <div className="mt-4 space-y-3">
                            {system.devBypass ? (
                                <Status
                                    tone="warn"
                                    title="Development bypass is active"
                                    body={`APP_ENV is "${system.environment}", so Cloudflare Access is not being checked. This is impossible in production.`}
                                />
                            ) : system.accessConfigured ? (
                                <Status
                                    tone="ok"
                                    title="Cloudflare Access is verifying every admin request"
                                    body={`Tokens are validated against ${system.teamDomain}.`}
                                />
                            ) : (
                                <Status
                                    tone="error"
                                    title="Cloudflare Access is not configured"
                                    body="Set CLOUDFLARE_ACCESS_TEAM_DOMAIN and CLOUDFLARE_ACCESS_AUD. Until then, admin requests are refused outside development."
                                />
                            )}
                        </div>

                        <h3 className="mt-6 text-sm font-semibold uppercase tracking-[0.1em] text-ink-faint">
                            Authors
                        </h3>
                        <ul className="mt-3 divide-y divide-line">
                            {users.map((user) => (
                                <li key={user.id} className="flex items-center justify-between gap-3 py-2.5">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium text-ink">{user.name}</p>
                                        <p className="truncate text-[0.82rem] text-ink-muted">{user.email}</p>
                                    </div>
                                    <span className="shrink-0 rounded-full bg-surface-2 px-2.5 py-1 text-[0.75rem] font-medium text-ink-muted">
                                        {user.role}
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <p className="mt-3 text-[0.85rem] text-ink-muted">
                            Authors are added by creating a user row and allowing the email in the Cloudflare
                            Access policy. There is no registration page, by design.
                        </p>
                    </section>

                    {/* --- System -------------------------------------------- */}
                    <section className="rounded-2xl bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-line sm:p-6">
                        <h2 className="font-display text-[1.35rem] tracking-tight text-ink">System</h2>

                        <dl className="mt-4 space-y-2.5 text-[0.92rem]">
                            <Row label="Environment" value={system.environment} />
                            <Row label="Search" value={system.searchDriver} />
                            <Row label="Stored photos" value={String(system.storedImages)} />
                            <Row label="Hot-linked photos" value={String(system.remoteImages)} />
                            <Row
                                label="Unattached uploads"
                                value={
                                    system.unattachedImages === 0
                                        ? 'none'
                                        : `${system.unattachedImages} (pruned nightly)`
                                }
                            />
                        </dl>

                        <Button
                            variant="secondary"
                            className="mt-5"
                            onClick={() =>
                                router.post(adminRoutes.settingsReindex, {}, { preserveScroll: true })
                            }
                        >
                            <RefreshCw className="size-4" aria-hidden="true" />
                            Rebuild the search index
                        </Button>

                        <p className="mt-2 text-[0.85rem] text-ink-muted">
                            Only needed if search results ever look out of date — the index updates itself on
                            every save.
                        </p>
                    </section>
                </div>
            </div>
        </AdminLayout>
    )
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between gap-4">
            <dt className="text-ink-muted">{label}</dt>
            <dd className="text-right font-medium text-ink">{value}</dd>
        </div>
    )
}

function Status({ tone, title, body }: { tone: 'ok' | 'warn' | 'error'; title: string; body: string }) {
    const Icon = tone === 'ok' ? ShieldCheck : tone === 'warn' ? AlertTriangle : ShieldX

    const classes =
        tone === 'ok'
            ? 'bg-adriatic-soft text-ink'
            : tone === 'warn'
              ? 'bg-croatia-soft text-ink'
              : 'bg-croatia text-white'

    return (
        <div className={`flex items-start gap-3 rounded-xl p-3.5 ${classes}`}>
            <Icon className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div className="min-w-0">
                <p className="font-medium">{title}</p>
                <p className="mt-0.5 text-[0.88rem] opacity-85">{body}</p>
            </div>
        </div>
    )
}

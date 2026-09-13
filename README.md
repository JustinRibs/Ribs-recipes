# Ribs Recipes

A self-hosted recipe collection. The public site is readable by anyone with no
account of any kind; administration lives entirely under `/admin` behind
Cloudflare Zero Trust Access.

**Production:** <https://recipes.ribarichh.com>

---

## Contents

- [What it does](#what-it-does)
- [Stack](#stack)
- [Local development](#local-development)
- [Production deployment](#production-deployment)
- [Continuous integration and Docker Hub](#continuous-integration-and-docker-hub)
- [Cloudflare Tunnel](#cloudflare-tunnel)
- [Cloudflare Zero Trust Access](#cloudflare-zero-trust-access)
- [Environment variables](#environment-variables)
- [Backups](#backups)
- [Importing from a URL](#importing-from-a-url)
- [Importing from a CSV](#importing-from-a-csv)
- [Images](#images)
- [Search](#search)
- [Testing](#testing)
- [Maintenance](#maintenance)
- [How it is put together](#how-it-is-put-together)

---

## What it does

**Public, no account required**

- A homepage of editorial rails: a spotlight, recently added, favorites, and a
  row per category.
- Browse with search, category and tag filters, and sorting. Search covers
  titles, descriptions, ingredient names, categories and tags.
- Recipe pages with structured ingredients, numbered steps, notes, a gallery
  and source attribution.
- **Serving scaling** — change the serving count and every scalable quantity
  re-renders as a proper fraction. Amounts that cannot be scaled ("to taste")
  are left exactly as written.
- **Cooking mode** — one step at a time at arm's-length size, swipe or tap to
  move, ingredients one tap away, the screen kept awake, and timers that keep
  running as you move between steps and pages.
- Installable as a PWA, with enough offline caching that a recipe you already
  opened stays usable if the network drops mid-cook.
- Light, dark and system themes, remembered locally.
- Schema.org Recipe structured data, Open Graph tags and canonical URLs,
  rendered server-side.

**Admin, behind Cloudflare Access**

- A recipe editor with dynamic ingredient and step rows, reordering, per-step
  timers, photo management and local draft autosave.
- Import from a URL — fetches the page, reads Schema.org JSON-LD (or
  microdata, or page metadata), and shows an **editable preview**. Nothing is
  saved until you press Save.
- Import from a CSV — upload, review every row with its own warnings and
  errors, tick the ones you want, then confirm.
- Categories and tags, favorites, drafts, duplication, and a trash you can
  restore from.

There is **no public registration, no login page, and no public write route of
any kind**. Every route that can change data is under `/admin`.

---

## Stack

|            |                                                                |
| ---------- | -------------------------------------------------------------- |
| Backend    | Laravel 13, PHP 8.4                                            |
| Frontend   | Inertia 3, React 19, TypeScript, Tailwind CSS 4                |
| Build      | Vite 8                                                         |
| Database   | SQLite, with FTS5 for search                                   |
| Runtime    | FrankenPHP (web server and PHP in one process)                 |
| Proxy      | Your existing Traefik, reached through a Cloudflare Tunnel     |
| Admin auth | Cloudflare Zero Trust Access (Google IdP), JWT verified in-app |

No Redis. No queue worker. No second Traefik.

---

## Local development

Requirements: PHP 8.4 with `sqlite3`, `gd` and `intl`; Composer; Node 22.

```bash
git clone <this repository> ribs-recipes
cd ribs-recipes

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Then set these three lines in `.env` for development:

```dotenv
APP_ENV=local
APP_DEBUG=true
ADMIN_DEV_BYPASS=true
```

`ADMIN_DEV_BYPASS` is the development shortcut past Cloudflare Access. It is
honoured **only** when `APP_ENV` is `local`, `development` or `testing` — see
[the security note](#the-development-bypass) below.

Create and seed the database:

```bash
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
```

The seeder creates the owner account, the starting categories and tags, and —
outside production — thirteen real demo recipes with locally generated
photography, so the homepage looks like a real collection immediately.

Run it:

```bash
composer run dev     # Laravel + Vite together
```

or in two terminals:

```bash
php artisan serve
npm run dev
```

The site is at <http://localhost:8000> and the admin at
<http://localhost:8000/admin>.

### Everyday commands

```bash
composer run dev          # serve the app and the Vite dev server
composer run test         # the PHP suite
composer run lint         # Pint (code style), with --test to check only
npm run build             # production assets
npm run types             # TypeScript check
npm run test              # front-end unit tests (Vitest)
npm run test:e2e          # end-to-end tests (Playwright)
npm run check             # types + unit tests + PHP suite
```

---

## Production deployment

The server already runs Docker Compose and Traefik. This stack **joins** the
existing Traefik network; it never starts, configures or replaces Traefik, and
it publishes no host ports.

### 1. Put the code on the server

```bash
sudo mkdir -p /srv/ribs-recipes
sudo chown "$USER" /srv/ribs-recipes
git clone <this repository> /srv/ribs-recipes
cd /srv/ribs-recipes
```

### 2. Configure

```bash
cp .env.example .env
```

Generate an application key. You do not need PHP on the host for this:

```bash
docker run --rm dunglas/frankenphp:1-php8.4-alpine \
    php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'
```

Put that in `APP_KEY`, then fill in the rest of `.env`. The values that matter
most:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://recipes.ribarichh.com
APP_KEY=base64:…

APP_HOST=recipes.ribarichh.com
TRAEFIK_NETWORK=traefik          # see below
TRAEFIK_ENTRYPOINT=web

ADMIN_EMAIL=you@gmail.com        # the Google account you sign in with
CLOUDFLARE_ACCESS_TEAM_DOMAIN=yourteam.cloudflareaccess.com
CLOUDFLARE_ACCESS_AUD=…          # from the Access application
```

Find your Traefik network's real name:

```bash
docker network ls
```

It is commonly `traefik`, `proxy` or `web`. Set `TRAEFIK_NETWORK` to match, or
the stack will fail to start with "network not found".

If Traefik terminates TLS itself rather than Cloudflare, set
`TRAEFIK_ENTRYPOINT=websecure`, `TRAEFIK_TLS=true` and
`TRAEFIK_CERT_RESOLVER=<your resolver>`.

### 3. Start

```bash
docker compose up -d --build
docker compose logs -f app
```

The first boot creates the SQLite database, runs the migrations, seeds the
owner account and the starting taxonomy, and caches the config, routes and
views. It is idempotent — every later deploy runs the same steps safely.

### 4. Check it

```bash
docker compose ps                 # both services healthy
docker compose exec app curl -s -o /dev/null -w '%{http_code}\n' localhost/up
```

### Updating

Building on the server:

```bash
cd /srv/ribs-recipes
./scripts/backup.sh               # always, first
git pull
docker compose up -d --build
```

Or, with the published image (see below):

```bash
cd /srv/ribs-recipes
./scripts/backup.sh
docker compose pull
docker compose up -d
```

Migrations run automatically on boot.

### What is persisted

One named volume, `ribs-recipes-storage`, mounted at `/app/storage`:

```
/app/storage/database/database.sqlite   the database
/app/storage/app/public/media/          uploaded and processed photos
/app/storage/app/private/imports/       CSV uploads awaiting confirmation
/app/storage/logs/                      application, access, import, media logs
```

Everything else — the code, the built assets, the framework caches — is in the
image and is rebuilt on deploy.

---

## Continuous integration and Docker Hub

Two workflows live in `.github/workflows`.

**`ci.yml`** runs on every push and pull request: Pint, the PHP suite, the
TypeScript check, ESLint, Prettier, the Vitest unit tests, a production asset
build, and the Playwright end-to-end suite on both WebKit and Chromium. A
failing run uploads its Playwright report as an artifact.

**`publish.yml`** builds the production image and pushes it to Docker Hub. It
runs on a push to `main` — but only _after_ `ci.yml` passes, so a broken commit
never becomes a deployable tag. It also runs on a `v*.*.*` tag, and by hand
from the Actions tab.

Every push produces two tags: `latest` and the full commit SHA. A version tag
adds `1.2.3` and `1.2`. After pushing, the workflow starts the image it just
published and checks that `/`, `/recipes` and `/sitemap.xml` answer 200 and
that `/admin` answers **403** — the image is only considered good if the admin
area is shut without a Cloudflare Access token.

### Setting it up

In **Settings → Secrets and variables → Actions**, add two repository secrets:

| Secret               | Value                                                  |
| -------------------- | ------------------------------------------------------ |
| `DOCKERHUB_USERNAME` | Your Docker Hub account name.                          |
| `DOCKERHUB_TOKEN`    | A Docker Hub **access token** with Read & Write scope. |

Create the token at **hub.docker.com → Account settings → Personal access
tokens**. Use a token, never your account password: a token is scoped, it is
listed so you can see it exists, and it can be revoked on its own without
changing how you log in.

Optionally add a repository _variable_ `DOCKERHUB_REPOSITORY` if the image
should not be named `<username>/ribs-recipes`.

### Deploying what was published

Point the server's `.env` at the tag and pull instead of building:

```dotenv
RIBS_IMAGE=yourdockerhubuser/ribs-recipes:latest
```

```bash
docker compose pull && docker compose up -d
```

Leave `RIBS_IMAGE` unset and the stack builds from the checkout as before —
both routes work, and the scheduler always runs the same image as the app.

The workflow builds `linux/amd64` only. Building `linux/arm64` too means
compiling PHP extensions under QEMU, which takes roughly twenty minutes per
run; if the server is a Raspberry Pi or Apple silicon, add the platform in
`publish.yml` and accept the wait.

---

## Cloudflare Tunnel

The tunnel terminates the public HTTPS connection and forwards to Traefik on
the same host. In **Zero Trust → Networks → Tunnels**, add a public hostname to
your existing tunnel:

| Field     | Value                                                             |
| --------- | ----------------------------------------------------------------- |
| Subdomain | `recipes`                                                         |
| Domain    | `ribarichh.com`                                                   |
| Type      | `HTTP`                                                            |
| URL       | your Traefik HTTP entrypoint, e.g. `traefik:80` or `localhost:80` |

If `cloudflared` runs as a container, it must be on the same Docker network as
Traefik and should use Traefik's service name (`traefik:80`). If it runs on the
host, use `localhost:80`.

Under **Additional application settings → HTTP Settings**, leave _HTTP Host
Header_ empty so the original `recipes.ribarichh.com` header reaches Traefik —
that header is what Traefik's router rule matches on.

Traffic then flows:

```
Browser → Cloudflare edge → Cloudflare Tunnel → Traefik → ribs-recipes
```

No port is open on your router, and no host port is published by this stack.

---

## Cloudflare Zero Trust Access

This is the real security boundary for `/admin`. The application does not
merely check for a header — it verifies Cloudflare's signed token on every
single admin request.

### 1. Google as the identity provider

**Zero Trust → Settings → Authentication → Login methods → Add new → Google**

Follow Cloudflare's instructions to create a Google OAuth client and paste in
the client ID and secret. Test the connection before continuing.

### 2. The Access application

**Zero Trust → Access → Applications → Add an application → Self-hosted**

| Field            | Value                       |
| ---------------- | --------------------------- |
| Application name | `Ribs Recipes Admin`        |
| Session duration | 24 hours (or as you prefer) |

Add the application paths. **Cloudflare treats the bare path and the wildcard
as two separate entries, and you need both** — with only `admin/*`, the
`/admin` dashboard itself is left unprotected:

| Subdomain | Domain          | Path      |
| --------- | --------------- | --------- |
| `recipes` | `ribarichh.com` | `admin`   |
| `recipes` | `ribarichh.com` | `admin/*` |

In the _Identity providers_ step, enable **Google** and turn **Accept all
available identity providers** off, so nothing else can be used to sign in.

### 3. The policy

Add one policy:

| Field       | Value                        |
| ----------- | ---------------------------- |
| Policy name | `Owner only`                 |
| Action      | `Allow`                      |
| Include     | **Emails** → `you@gmail.com` |

Use _Emails_, not _Email domain_ — a domain rule would admit anyone with an
address at that domain. To add a family member later, add their address to this
same Include rule **and** create a matching user row (see
[Adding another author](#adding-another-author)).

### 4. Point the application at it

From the Access application's **Overview** tab, copy the **Application
Audience (AUD) Tag** — a long hex string. From **Zero Trust → Settings →
Custom Pages** (or the top of any Zero Trust page) copy your **team domain**,
which looks like `yourteam.cloudflareaccess.com`.

```dotenv
CLOUDFLARE_ACCESS_TEAM_DOMAIN=yourteam.cloudflareaccess.com
CLOUDFLARE_ACCESS_AUD=8fa1b2c3…
ADMIN_EMAIL=you@gmail.com
```

Then `docker compose up -d` to pick up the change.

### How the verification works

Cloudflare sends a signed JWT with every request it lets through, in the
`Cf-Access-Jwt-Assertion` header (and as the `CF_Authorization` cookie).
`App\Http\Middleware\EnsureCloudflareAccess` checks, in order:

1. **Signature** — against your team's public keys, fetched from
   `https://<team-domain>/cdn-cgi/access/certs` and cached for an hour.
2. **Issuer** — must be exactly `https://<team-domain>`, so a token from
   another Cloudflare team is refused.
3. **Audience** — must contain your `CLOUDFLARE_ACCESS_AUD`, so a token for a
   different application of yours is refused.
4. **Expiry** — `exp` and `nbf`, with a small configurable clock skew.

Only then is the email claim read and mapped to a user row.

The `Cf-Access-Authenticated-User-Email` header is **deliberately ignored**. It
is unsigned, and anything that could reach the container directly could set it.

`tests/Feature/Admin/CloudflareAccessTest.php` covers all of this with real
signed tokens, including forged signatures, expired tokens, wrong issuer, wrong
audience, and the header-only attempt.

### Verifying it works

```bash
# No token: 403, from anywhere, including the host itself.
curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: recipes.ribarichh.com' \
    http://localhost/admin

# A forged email header changes nothing.
curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: recipes.ribarichh.com' \
    -H 'Cf-Access-Authenticated-User-Email: you@gmail.com' \
    http://localhost/admin
```

Both must print `403`. In a browser, `https://recipes.ribarichh.com/admin`
should bounce you to Google and back.

### The development bypass

`ADMIN_DEV_BYPASS=true` skips Access entirely. It is gated on the **runtime
environment**, not on the flag:

```php
// config/ribs.php
'dev_environments' => ['local', 'development', 'testing'],
```

With `APP_ENV=production`, setting `ADMIN_DEV_BYPASS=true` does nothing at all —
admin requests are still refused. There is no code path that lets a production
deployment fall back to an unauthenticated admin area. The admin Settings page
shows, in plain language, which of the two is currently protecting you.

### Adding another author

1. Add their email to the Cloudflare Access policy's Include rule.
2. Create their user row:

```bash
docker compose exec app php artisan tinker
>>> App\Models\User::create([
...     'name' => 'Family Member',
...     'email' => 'them@gmail.com',
...     'role' => App\Enums\UserRole::Contributor,
... ]);
```

Roles are `owner`, `editor` and `contributor`. A contributor may create recipes
and edit their own; an editor may edit anything and manage categories and tags;
only an owner can delete permanently. A verified identity with no matching user
row is refused, unless `ADMIN_AUTO_PROVISION=true`.

---

## Environment variables

Every variable is documented inline in [`.env.example`](.env.example). The ones
worth knowing:

| Variable                        | Purpose                                                                  |
| ------------------------------- | ------------------------------------------------------------------------ |
| `APP_KEY`                       | Laravel encryption key. Generate once; changing it invalidates sessions. |
| `APP_HOST`                      | Hostname Traefik routes to this container.                               |
| `TRAEFIK_NETWORK`               | Name of your **existing** Traefik network.                               |
| `TRAEFIK_ENTRYPOINT`            | Traefik entrypoint to attach to (`web` behind a tunnel).                 |
| `CLOUDFLARE_ACCESS_TEAM_DOMAIN` | e.g. `yourteam.cloudflareaccess.com`.                                    |
| `CLOUDFLARE_ACCESS_AUD`         | Application Audience tag from the Access app.                            |
| `ADMIN_EMAIL`                   | Provisioned as the `owner` user on first sign-in.                        |
| `ADMIN_DEV_BYPASS`              | Development only; ignored outside `APP_ENV=local`.                       |
| `IMAGE_MAX_UPLOAD_KB`           | Largest photo accepted (default 25 MB).                                  |
| `IMAGE_MAX_EDGE`                | Longest edge stored (default 2400px).                                    |
| `IMPORT_FETCH_MAX_BYTES`        | Cap on a fetched page (default 3 MB).                                    |
| `IMPORT_ALLOW_PRIVATE_NETWORKS` | **Leave false.** Disables SSRF protection.                               |
| `CSV_IMPORT_MAX_ROWS`           | Rows read per CSV upload (default 250).                                  |

---

## Backups

Two things cannot be rebuilt: the SQLite database and the uploaded photos.
`scripts/backup.sh` snapshots both.

```bash
./scripts/backup.sh                        # ./backups, keep 14 snapshots
./scripts/backup.sh --dir /mnt/nas/ribs    # somewhere else
./scripts/backup.sh --keep 30              # keep a month
./scripts/backup.sh --local                # no Docker; operate on local paths
```

The destination and retention can also come from the environment, which is what
a cron entry usually does:

```cron
0 3 * * * cd /srv/ribs-recipes && BACKUP_DIR=/mnt/nas/ribs BACKUP_KEEP=30 \
    ./scripts/backup.sh >> /var/log/ribs-backup.log 2>&1
```

Each run produces:

```
backups/2026-09-11-030000/
    database.sqlite     a consistent snapshot
    images/…            every stored photo, in its original layout
    manifest.txt        what was taken, and how to put it back
```

### Why it does not just copy the file

The database runs in WAL mode. A plain `cp` of `database.sqlite` can capture a
torn page, and will silently miss every committed change still sitting in the
`-wal` file. The script uses `sqlite3 .backup`, which is SQLite's online backup
API: a consistent snapshot taken while the site keeps serving. Every snapshot is
then verified with `pragma integrity_check` before the run is considered
successful, and a failed check aborts rather than leaving a bad backup in place.

### Restoring

Every snapshot's `manifest.txt` contains these steps with its own paths filled
in.

```bash
cd /srv/ribs-recipes
SNAPSHOT=backups/2026-09-11-030000

# 1. Stop the application so nothing writes during the swap.
docker compose stop app scheduler

# 2. Restore the database, and remove the old WAL sidecars — leaving them
#    would let stale transactions reapply over the restored file.
docker compose run --rm --entrypoint sh app -c \
    'rm -f /app/storage/database/database.sqlite*'
docker compose cp "$SNAPSHOT/database.sqlite" \
    app:/app/storage/database/database.sqlite

# 3. Restore the photos.
docker compose run --rm --entrypoint sh app -c \
    'rm -rf /app/storage/app/public/media && mkdir -p /app/storage/app/public/media'
tar -cf - -C "$SNAPSHOT/images" . | \
    docker compose run --rm -T --entrypoint sh app -c \
    'tar -xf - -C /app/storage/app/public/media'

# 4. Fix ownership and start again.
docker compose run --rm --entrypoint sh app -c \
    'chown -R www-data:www-data /app/storage'
docker compose up -d

# 5. Rebuild the search index if results look stale.
docker compose exec app php artisan recipes:reindex
```

To restore onto a machine with no Docker at all, the snapshot is just a SQLite
file and a directory of images — copy them into place and point a Laravel
install at them.

---

## Importing from a URL

`/admin/import/url`. Paste a recipe address; the server fetches the page and
extracts what it can, best source first:

1. **Schema.org Recipe in JSON-LD** — the highest-fidelity source, and what
   almost every modern recipe site publishes. `@graph` containers, `HowToStep`
   and `HowToSection` groups, image arrays and ISO 8601 durations are all
   handled.
2. **Microdata** — the same vocabulary marked up in HTML, still common on older
   food blogs.
3. **Page metadata** — Open Graph title, description and image, plus ingredient
   and instruction lists _only_ where a container names itself as one. It will
   not scrape an article's paragraphs hoping they are a recipe; an import
   preview full of somebody's childhood story is worse than an empty one.

The result is shown in an **editable preview** — the ordinary recipe editor,
pre-filled. Nothing is written to the database until you press **Save recipe**.
The preview says which source was used and warns about anything missing.

### SSRF protection

The importer fetches URLs a person types in, which makes it the one part of the
application that could otherwise be pointed at your home network.
`App\Services\Http\UrlGuard` and `SafeHttpClient` enforce:

- **http and https only** — no `file:`, `gopher:`, `ftp:` or `javascript:`.
- **No credentials** in the authority, and only normal web ports.
- **Every resolved address must be publicly routable** — loopback, private,
  link-local (including `169.254.169.254`), CGNAT, multicast and reserved
  ranges are refused, for IPv4 and IPv6, including IPv4-mapped IPv6 addresses.
- **Blocked hostnames** — `localhost`, `*.internal`, `*.lan`, `*.local`,
  `metadata.google.internal` and friends, short-circuited before DNS.
- **DNS rebinding** — the connection is pinned with `CURLOPT_RESOLVE` to the
  exact addresses that were validated, so a hostname cannot resolve to
  something public during the check and to `127.0.0.1` a moment later.
- **Redirects followed manually**, so _every hop_ is re-validated rather than
  trusting cURL's own follower.
- **Connect and read timeouts**, a **redirect limit**, and a **byte cap
  enforced mid-download** so a hostile server cannot stream gigabytes at the
  home server.

`tests/Unit/UrlGuardTest.php` pins the full list of refusals.

---

## Importing from a CSV

`/admin/import/csv`. Upload, review, then confirm. A template is downloadable
from that page and committed at
[`resources/templates/ribs-recipes-template.csv`](resources/templates/ribs-recipes-template.csv).

### Columns

Only `title` is required. Unknown columns are ignored, and headers are matched
loosely — `Prep Minutes`, `prep-minutes` and `prep_minutes` are the same thing.

| Column           | Notes                                                                        |
| ---------------- | ---------------------------------------------------------------------------- |
| `title`          | **Required.**                                                                |
| `description`    | One or two sentences; shows on cards and in search.                          |
| `category`       | Created if it does not exist.                                                |
| `tags`           | Pipe-separated: `Healthy\|High Protein\|Meal Prep`. Created if new.          |
| `prep_minutes`   | A number, or `1 hr 30 min`, or `PT1H30M`.                                    |
| `cook_minutes`   | Same.                                                                        |
| `servings`       | A number.                                                                    |
| `calories`       | Per serving.                                                                 |
| `source_url`     | Full `https://…` address.                                                    |
| `source_name`    | Falls back to the host of `source_url`.                                      |
| `hero_image_url` | Downloaded into the collection, or hot-linked — your choice at confirm time. |
| `ingredients`    | One per line. See below.                                                     |
| `instructions`   | One step per line. See below.                                                |
| `notes`          | Free text.                                                                   |
| `favorite`       | `yes` / `true` / `1` to feature it on the homepage.                          |

### Ingredients

One ingredient per line **inside the cell**, as
`quantity | unit | ingredient | note`. Every part except the ingredient name is
optional.

```
2 | cups | rolled oats
1 | tsp | ground cinnamon
0.5 | cup | Greek yogurt | plain, 2%
1 1/2 | lb | chicken breast | cubed
 | | salt | to taste
```

Fractions (`1/2`, `1 1/2`), decimals (`0.5`) and ranges (`2-3`) are all
understood. A line with no pipes is parsed as free text, so
`2 cups rolled oats` also works.

### Instructions

One step per line. Add `| 25` to give a step a 25-minute timer in cooking mode.

```
Preheat the oven to 350°F.
Mix everything together.
Bake until the centre is set. | 30
```

### The preview

Every row is validated on its own and shown with its own errors and warnings.
Rows that cannot be imported are marked and excluded; **one malformed recipe
never takes the batch down with it**. You tick the rows you want, choose a
default category and whether to import as drafts or published, and only then is
anything written.

A row with no ingredients or no instructions is always imported as a draft,
whatever you chose — a published recipe nobody can cook is worse than a draft.

The uploaded file is held in private storage between the two steps, and the
confirm step re-reads it from disk rather than trusting what the browser sends
back, so the rows that get imported are provably the rows you reviewed.

---

## Images

Each recipe has one hero image and any number of gallery images, each with its
own caption, alt text and position.

### Uploaded photos

Photos are processed the moment they are chosen:

- **Re-encoded**, which normalises the format and strips every metadata block —
  including GPS coordinates, which is the reason a personal site should never
  serve camera originals untouched.
- **Rotated** according to the EXIF orientation, so portrait phone photos are
  not stored sideways.
- **Downscaled** to `IMAGE_MAX_EDGE` (2400px by default). The 24 MP original is
  not kept; nothing on the site ever renders larger than a 2K hero.
- **Emitted as a WebP ladder** at 320/640/960/1440/2048 plus the source width,
  never upscaled, with one full-size JPEG as a universal fallback and share
  image.
- Given a **~1 KB blurred placeholder** inlined as a data URI, so a card never
  flashes an empty grey box on a slow connection.

Stored under `storage/app/public/media/YYYY/MM/<ulid>/`, on the persistent
volume, and served at `/storage/media/…`.

### Remote photos

You can also point at a photo that lives somewhere else, with two options:

- **Download and store** — copies it into the collection. The photo survives
  the source site changing or disappearing. Recommended, and the default for
  imports.
- **Use external image** — keeps the original URL and hot-links it. Saves disk
  space, but the photo depends on that site staying up.

Either way the URL goes through the same SSRF guard as the recipe importer, and
the response must actually be an image.

### Housekeeping

Photos upload immediately so you see a real thumbnail while writing, and the
recipe claims them when you save. Anything uploaded into an editor that was
then abandoned is swept up nightly by `php artisan media:prune`. Duplicating a
recipe shares its photo files rather than copying them; deleting one copy never
takes the other's photos with it.

---

## Search

On SQLite, search runs through an **FTS5** index that covers titles,
descriptions, ingredient names, categories, tags and notes. Diacritics are
folded (so `cevapi` finds `Ćevapi`) and the last word gets a prefix wildcard, so
typing feels instant.

A recipe's searchable text spans four tables, which SQLite triggers cannot
join, so the denormalised row is rewritten from the model layer whenever a
recipe or an ingredient is saved — including from seeders, console commands and
tinker. Anything a visitor types is quoted before it reaches FTS5, so query
operators are treated as literal text rather than syntax.

If FTS5 is unavailable, or you later move to PostgreSQL or MariaDB, search
transparently falls back to portable indexed `LIKE` matching. Nothing else has
to change. The admin Settings page shows which is in use.

```bash
php artisan recipes:reindex   # rebuild, if results ever look stale
```

---

## Testing

```bash
composer run test    # 200 PHP tests
npm run test         # 59 front-end unit tests
npm run test:e2e     # 72 end-to-end tests (WebKit at iPhone size + Chromium)
npm run check        # types, unit tests, and the PHP suite
```

**PHP** — `tests/`. Covers recipe CRUD and validation, slug generation, the
public site being public and drafts staying private, Schema.org output,
Cloudflare Access (real signed tokens: forged signatures, expired tokens, wrong
issuer, wrong audience, header-only attempts, and every admin route refusing an
unauthenticated request), SSRF refusals, URL and CSV import parsing, image
processing, and ingredient scaling.

**Front-end** — Vitest, for the pure logic a visitor sees: quantity parsing,
fraction formatting, serving scaling, unit agreement and timer detection. These
mirror the PHP suite case for case, because both implementations have to agree.

**End-to-end** — Playwright, against a real application on its own database.
The default project is **WebKit at iPhone 14 Pro size**, because that is what
this site is built for; the same specs also run on desktop Chromium. They cover
the homepage, search, serving scaling, cooking mode (including timers that
survive step changes), the four-tap admin entrance, recipe creation, both
importers, and the error pages.

The e2e suite needs built assets first:

```bash
npm run build && npm run test:e2e
```

On the first run it copies `.env.e2e.example` to `.env.e2e` and generates an
`APP_KEY` into that copy. `.env.e2e` is git-ignored; only the blank template is
tracked, so a generated key can never reach a commit.

The suite runs against `database/e2e.sqlite`, never the development database.
That hangs on one detail worth knowing: `php artisan serve --env=e2e`
configures the _artisan_ process, not the requests it serves. The PHP built-in
server bootstraps the application again per request and Laravel forwards only
an allow-list of variables to those workers. `APP_ENV` is on that list, so
`playwright.config.ts` passes it through `webServer.env` — which is what makes
the workers load `.env.e2e` at all.

### A note on environment files

`.gitignore` ignores `.env*` outright and re-admits only `.env.example` and
`.env.*.example`. Anything holding a real value is ignored by default rather
than by being remembered — which is the failure mode an allow-list has.

---

## Maintenance

```bash
docker compose logs -f app                     # application output
docker compose exec app tail -f storage/logs/access.log   # Access refusals
docker compose exec app tail -f storage/logs/import.log   # import failures
docker compose exec app tail -f storage/logs/media.log    # image failures

docker compose exec app php artisan media:prune          # sweep stale uploads
docker compose exec app php artisan recipes:reindex      # rebuild search
docker compose exec app php artisan about                # environment summary
```

Access, import and media events go to their own daily log files so a recurring
failure is easy to find. **Tokens, secrets and request headers are never
logged** — only the reason something was refused.

---

## How it is put together

```
app/
  Enums/                RecipeStatus, UserRole, ImageSource
  Http/
    Controllers/
      PublicSite/       home, browse, recipe, category, tag, search, manifest
      Admin/            dashboard, recipes, taxonomy, media, imports, settings
    Middleware/         EnsureCloudflareAccess, HandleInertiaRequests, headers
    Presenters/         exact prop shapes for each page
    Requests/Admin/     form request validation
  Models/               Recipe, Ingredient, Step, Image, Category, Tag, User
  Policies/             per-role authorisation
  Services/
    Access/             JWT verification, identity → user mapping
    Http/               UrlGuard (SSRF), SafeHttpClient
    Images/             processing pipeline and storage
    Import/             JSON-LD, microdata and fallback parsers; CSV
    Recipes/            the single write path, search, slugs, browsing
    Seo/                Schema.org Recipe output
  Support/              Quantity, Duration, SecurityHeaders

resources/js/
  components/           cards, rails, search, gallery, cooking mode, admin
  hooks/                theme, wake lock, timers, swipe, local state
  layouts/              public and admin chrome
  lib/                  quantity, time, routes  (mirrored by the PHP Support/)
  pages/                one file per Inertia page
  sw.ts                 the service worker, written by hand

docker/                 Dockerfile support: php.ini, Caddyfile, entrypoint
scripts/                backup.sh, build-icons.sh
```

A few decisions worth knowing about:

- **One write path.** Manual creation, the URL importer and the CSV importer
  all funnel through `RecipeWriter`, so slugs, derived totals, ingredient
  normalisation, image ownership and the search index stay consistent no matter
  where a recipe came from.
- **Quantities are stored twice.** A normalised number for scaling, and the
  author's own wording for display. An unscaled recipe always reads exactly the
  way it was written, and scaling never invents a number for "a pinch".
- **The admin area is never cached.** Not by Cloudflare, not by Traefik, not by
  the service worker, which refuses to intercept `/admin` at all.
- **Authorisation runs before route-model binding**, so an unauthenticated
  request to `/admin/recipes/42` is a 403 whether or not recipe 42 exists.
- **The four-tap logo is navigation, not security.** `/admin` is exactly as
  protected whether you arrive by tapping, by typing the URL, or by guessing
  it. It exists only so the public site carries no visible admin link.

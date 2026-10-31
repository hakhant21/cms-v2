# Ruma Organic — content-managed website

A Laravel + Filament CMS where every word, image, link, colour and animation on
the public site is edited by a non-technical editor in `/admin`. There are no
hand-written page templates: a URL is matched to a row in `pages`, and the
blocks stored with that page decide what renders.

## What is in the box

| Area | Where |
| --- | --- |
| Admin panel | `app/Filament` (pages, navigation, media, messages, languages, users, settings) |
| Block catalogue | `app/Blocks` — `BlockType` → `AbstractBlockType` → 10 types, listed in `BlockRegistry` |
| Page rendering | `app/Http/Controllers/PageController.php` → `resources/views/blocks/*` |
| Section layouts | `resources/views/blocks/sections/*` (plain, hero, grid, media, company_profile, farm_journey, cta, banner, soil_intro, soil_dimension, cta_lime) |
| Design system | `resources/css/app.css` (tokens, components, motion), `resources/css/components.css` |
| Motion | `resources/css/motion.css`, `resources/js/motion.js` (no framework, no dependencies) |
| Settings catalogue | `app/Support/SiteSettings.php` |
| Live chat | `app/Support/Chat.php` (rules), `app/Livewire/ChatWidget.php` (visitor), `app/Livewire/ChatThread.php` + `app/Filament/Resources/ChatConversations` (team) |
| Error pages | `resources/views/errors/{404,500}.blade.php` + `x-site.error-page` |
| Legacy import | `php artisan ruma:import` |
| Content export | `php artisan ruma:export` — the deployable content file |
| Link audit | `php crawl.php [base-url]` against a running server |

## Local setup

Requirements: PHP 8.4+, Composer, Node 20+ and a MySQL 8 server. The Docker
stack brings its own database, so only the host-run workflow below needs one —
create the schema the app logs in with first:

```sql
CREATE DATABASE ruma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ruma'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL ON ruma.* TO 'ruma'@'localhost';
```

Then put those values in `.env` (`DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`) and run:

```sh
composer install
cp .env.example .env      # then set the DB_* values above
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

The test suite needs no database server — it runs on in-memory SQLite, which is
why `config/database.php` keeps SQLite as the fallback connection even though
everything deployed runs on MySQL.

`php artisan dev` runs everything at once instead — the web server, the queue,
the log tail, Vite and the Reverb websocket — and `php artisan dev:list` shows
that list.

Create the first administrator:

```sh
php artisan tinker --execute="App\Models\User::create(['name' => 'Admin', 'email' => 'you@example.com', 'password' => bcrypt('password'), 'is_admin' => true]);"
```

Only accounts with `is_admin` may open `/admin`.

## Using the admin

* **Pages** — one screen per page. The top holds the address, status, featured
  image and the **product images** gallery (drag the order you want to show; the
  first photo opens a product page when there is no featured image); a tab per
  language holds the title, summary, sections and the search-engine listing.
  **Translate** fills the other language tabs with machine translation, which
  stays in the form until you read it and press Save.
* **Sections** are built from fields: pick a field type (title, paragraph, list,
  image, gallery, video, button, milestones, stats…) and fill it in. The layout
  selector decides how those fields are arranged — from a plain stack to the
  animated Russia–Myanmar hero. Body copy is printed as cards with a reveal
  animation rather than as a wall of paragraphs.
* **Video block** — paste any YouTube or Vimeo address (watch page, share link,
  Shorts or an embed URL); the player address is derived from it. Choose
  landscape or portrait for Shorts.
* **Navigation** — header or footer, either a page (follows slug changes) or a
  custom URL, with a label per language.
* **Site settings** — company details, contact form behaviour, brand colours,
  animation speed, the home page, social links. Each setting is one line in
  `SiteSettings`; the form, the typed reads and the frontend follow. Filling in a
  social link adds its icon to the footer; an empty field leaves the icon out.
* **Media** — the image library. Uploads from any field land here.
* **Messages** — enquiries from the contact form, with a new/read state.
* **Redirects** — old addresses that should still work. One row answers the
  default language and every language prefix, so `/blog` and `/my/blog` both
  find their page.

Drafts are invisible to visitors; use **Preview** on a page to see it in every
language before publishing.

Table filters are plain dropdowns sitting immediately after the search box — no
button to open, and the choice is always visible.

### Collection blocks and archives

A `post_collection`, `product_collection`, `feedback_collection` or
`faq_accordion` block holds no content of its own: it lists the published pages
of its kind, newest first, reading the library at render time. Adding an entry
therefore updates every listing that shows it — no rebuild and no copy-paste.

A `feedback_collection` can slide or sit still: **carousel** shows two cards at
a time and advances every two seconds (hovering or tabbing into it holds it
still), while **grid** shows them all. Either way the farmer's portrait and name
sit at the bottom of the card.

That is also how an *archive* works: a page with a collection block on it. The
importer creates one per kind that has entries (`/news` today); the editor can
retitle, restyle or delete it like any other page.

## Search engines

Each page renders its own JSON-LD (`WebPage`, or `Article` / `Product` for those
kinds) with a `BreadcrumbList` that is built from the site tree — the section
crumb is only emitted when a page really lives at that address. Missing pages are
`noindex,follow`, and the sitemap lists every page in every language with
`hreflang` alternates.

## Languages

The default language lives at the root (`/about`), every other language is
prefixed (`/my/about`). The prefix always follows the `languages` table — the
code never hard-codes a locale. Adding a language in the admin adds a tab in
every editor and a prefix on the site.

Whole-UI strings (buttons, headings the site itself prints) live in
`lang/{en,my,ru}/ui.php`.

## Live chat

A bubble in the corner of every public page opens a conversation that lands in
**Inbox → Live chat** in the admin. Visitors are not logged in, so the
conversation's credential is a 40-character token minted in their session; the
same token names the broadcast channel they listen on, which is why a guest
needs no authorisation round trip. The team joins one shared private channel,
`chat.agents`, guarded by `is_admin` in `routes/channels.php`.

Messages are written to the database first and broadcast afterwards, so the
chat survives a stopped socket:

| Situation | What still works |
| --- | --- |
| `REVERB_APP_KEY` set, `php artisan reverb:start` running | instant delivery both ways |
| Reverb stopped | the visitor's panel polls every 6s, the admin thread every 15s |
| `VITE_REVERB_APP_KEY` empty | no broadcast client is even downloaded; polling only |
| `chat.enabled` off | no bubble, no client, no transcript |

Turn it on and off, and write the opening line, in **Settings → Live chat**.

To enable the socket locally, put the four `REVERB_*` values in `.env` (the
`VITE_REVERB_*` keys copy them for the browser), run `php artisan reverb:start`
— or simply `php artisan dev`, which includes it — and `npm run build` so the
broadcast client is in the manifest.

Two ports are involved: `REVERB_PORT` is the address browsers are told to use
and `REVERB_SERVER_PORT` is where the server actually listens. They are normally
the same number, and both must be free — on a Laravel Herd machine 8080 is not.

## Adding a block type

1. Create `app/Blocks/Types/YourBlock.php` extending `AbstractBlockType`
   (`key()`, `label()`, `fields()`, `defaults()`).
2. Register it in `app/Blocks/BlockRegistry.php`.
3. Add `resources/views/blocks/your_block.blade.php`.

The editor picker, the renderer and `tests/Unit/SectionDataTest.php` pick it up
automatically — the test fails until the view exists.

## Importing the legacy content

```sh
php artisan ruma:import /path/to/legacy.sqlite --fresh --force
```

Reads the old `pages` / `blocks` / `block_page` / `menu_items` tables and writes
the new shape: one row per page, one translation per language, block payloads
per locale. Safe to re-run; it never touches users.

The importer also creates a listing page for every content kind that has
entries, and switches off any menu entry whose address no longer resolves —
those appear greyed out in the admin with a warning in the command output, so
the editor can either create the missing page or point the entry somewhere real.

## Carrying the content to another host

```sh
php artisan ruma:export      # writes database/seeders/content/site.json
```

The deployed stack runs MySQL, whose volume starts empty, so content cannot
travel as a database file. It travels as that one readable file: pages, every
locale's copy of them, the media references, both menus and the redirects,
written with business keys (slug, locale, file path) instead of ids, which is
why the same file seeds a database whose auto-increments are nothing like the
ones it was written from. It is a text file in the repository, so a wording
change is visible in a diff.

`SiteContentSeeder` replays it at container start, and only into a database with
no pages — once an editor is at work the database is the newer copy of the
truth, so a boot never overwrites their words. Re-export after meaningful
content changes if a fresh deploy should start from the current site.

## Deploying with Docker

The stack is four containers — `web` (nginx), `app` (php-fpm + queue worker +
scheduler under supervisord), `mysql` (the database) and `redis` (sessions,
cache, queue) — plus an optional fifth, `reverb`, for live chat.

```sh
git clone <repo> && cd ruma-v2
cp .env.example .env        # then set APP_URL, and the mail/Gemini keys if used
make up                     # build the images and start everything
make smoke                  # every language, the admin, the sitemap, a 404
```

`./run.sh up` does exactly the same on a host without `make`; `run.sh` is the
bash twin of every Makefile target.

The site listens on `http://<host>:8081` by default (`WEB_PORT` in `.env`). Put
a TLS-terminating proxy in front of it for production; nginx here only serves
plain HTTP and passes PHP to the app container.

**Content survives rebuilds.** The database lives on its own volume, uploads on
a second one, and `make up` never touches either — deleting a volume is the only
way to lose content, which is what `make destroy` warns about before it does it.
The same `.env` configures both sides: compose passes `DB_DATABASE`,
`DB_USERNAME` and `DB_PASSWORD` to the MySQL container and to the app, so one
file is the single source of credentials, and `DB_HOST` is pinned to `mysql` for
the containers whatever the host file says. The app container waits for the
database to answer before it migrates, then runs `php artisan db:seed --force`
— the seeders only insert what is missing, so booting on an existing database
adds nothing it already has. Migrations run at container start.

| Task | Command |
| --- | --- |
| Status, logs, shell | `make ps` · `make logs` / `logs-web` / `logs-redis` / `logs-mysql` · `make shell` |
| Artisan in the container | `make artisan c="tinker --execute=..."` |
| A mysql prompt | `make db` |
| Backup the live database | `make backup` (writes `ruma-backup-<date>.sql.gz`, a `mysqldump`) |
| Restore it | `make restore from=ruma-backup-….sql.gz` |
| Rebuild caches after a deploy | `make cache` (or `make restart`; opcache does not revalidate files) |
| Workers and scheduler | `make supervisor` |
| Live chat server | `make realtime` — set the `REVERB_*` keys, then rebuild: the browser side is baked into the built assets |
| Tear down **including data** | `make destroy` (types a confirmation first; `FORCE=1` to skip) |

Creating the first administrator on a fresh host:

```sh
make artisan c="tinker --execute=\"App\\Models\\User::create(['name' => 'Admin', 'email' => 'you@example.com', 'password' => bcrypt('secret'), 'is_admin' => true]);\""
```

Sizing notes: MySQL is capped at 700 MB (its InnoDB pool is set to 128 MB), the
app container at 384 MB with four 192 MB php-fpm workers, nginx at 64 MB, Redis
at 160 MB — the whole stack fits a 2 GB host, and a 1 GB host runs it by lowering
`pm.max_children` in `docker/php-fpm/www.conf` and the matching `mem_limit`. MySQL
is the largest item by far: on a host that small, drop `innodb-buffer-pool-size`
in `docker-compose.yml` as well.

## Tests and quality gates

```sh
php artisan test          # feature + unit
composer lint             # code style (pint)
composer check            # both
npm run build             # Vite assets
php crawl.php             # every page in every language + dead-link audit
```

`tests/Feature/AdminSmokeTest.php` opens every admin screen as an editor, so a
broken form schema surfaces as a failed test rather than a blank panel.
`tests/Feature/SiteTest.php` covers routing, locales, drafts, preview, the
sitemap and the contact form. `tests/Feature/ChatTest.php` covers the chat
token, the counters, the rate limit, closing and reopening, and what is allowed
to cross the wire.

## Notes

* Emails are queued through the default mailer; the enquiry is written to the
  database first, so a mail failure never loses a message.
* Motion respects `prefers-reduced-motion` and the “None” animation setting.
* Images in the legacy data point at the old CDN; new uploads are served from
  `storage/app/public` (`php artisan storage:link`).
* Legacy strings are repaired at the border (`App\Support\Text`): Windows-1252
  bytes become UTF-8 and file names keep their multi-byte characters, because a
  single invalid byte made `json_encode()` refuse a Livewire snapshot and turned
  an admin screen into a 500.

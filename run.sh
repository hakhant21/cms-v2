#!/usr/bin/env bash
# Bash twin of the Makefile, for hosts without `make` (a minimal server image,
# Windows Herd, CI). Every subcommand mirrors a Makefile target:
#
#   ./run.sh up
#   ./run.sh migrate
#   ./run.sh artisan migrate --force
#
# The destructive operations (destroy, fresh) ask first here, so they are just
# as guarded when called through `make destroy` as they are on the CLI.
#
# Override the compose binary/arguments with COMPOSE if the plugin form is not
# available:   COMPOSE="docker-compose" ./run.sh up
set -Eeuo pipefail

cd -- "$(dirname -- "${BASH_SOURCE[0]}")"

# COMPOSE may contain arguments ("docker compose -f other.yml"), so split it.
IFS=' ' read -r -a COMPOSE_CMD <<< "${COMPOSE:-docker compose}"

WEB_PORT="${WEB_PORT:-8081}"

compose() { "${COMPOSE_CMD[@]}" "$@"; }
# No --no-deps: a one-shot container runs migrations and seeds, so MySQL and
# Redis have to be up before it does anything.
run() { compose run --rm -T app "$@"; }
# The database container, addressed as root — which is what a dump of the whole
# schema needs. The container knows its own password, so this script never does.
mysql_root() { compose exec -T mysql sh -c "exec $*"; }

# Report the exact command and its exit code on failure. `$BASH_COMMAND` is
# the command string; `$?` is its exit status at trap time.
trap 'rc=$?; echo "ERROR: ${BASH_COMMAND} (exit $rc)" >&2' ERR

usage() {
    cat <<'EOF'
Deploy (Docker)
  env          Create .env from .env.example and generate APP_KEY (idempotent)
  validate     Validate docker-compose.yml without starting anything
  up           Build and start the stack (web, app, mysql, redis)
  build        Rebuild images without starting
  down         Stop and remove containers (keeps volumes)
  destroy      Stop and remove containers AND volumes (asks first)
  ps           Show container status
  logs         Tail the app container (php-fpm, queue, scheduler)
  logs-web     Tail the web (nginx) container
  logs-redis   Tail the redis container
  logs-mysql   Tail the MySQL container
  db           A mysql prompt as the application user
  restart      Restart the app container
  shell        Open a shell in the app container
  artisan ...  Run artisan in the app container (args passed through)
  supervisor   Show supervisord program status
  migrate      Run pending migrations (one-shot app container, verbose)
  seed         Seed without wiping anything
  fresh        Drop all tables and re-seed (asks first — deletes content)
  cache        Rebuild the production caches in the running container
  smoke        Curl the deployed site: health, home, every language, admin
  backup       Write the live database out as a gzipped SQL dump
  restore F    Load a dump back into the live database
  realtime     Start the stack plus the Reverb chat server

Host (Herd, PHP 8.4)
  test         Run the test suite
  pint         Format dirty PHP files
  check        pint --test plus the test suite
  dev          Vite dev server
  assets       Production asset build
  serve        php artisan dev (server, queue, logs, Vite, Reverb)
  crawl        Crawl every page in every language and report dead links
EOF
}

ensure_env() {
    if [[ ! -f .env ]]; then
        if [[ -f .env.example ]]; then
            echo "==> .env not found — creating from .env.example"
            cp .env.example .env
        else
            echo "ERROR: no .env and no .env.example to copy from." >&2
            exit 1
        fi
    fi

    if ! grep -qE '^APP_KEY=base64:.+' .env; then
        echo "==> Generating APP_KEY"
        local key
        if command -v openssl >/dev/null 2>&1; then
            key="base64:$(openssl rand -base64 32)"
        else
            # Fallback: /dev/urandom + base64 (GNU coreutils or BSD).
            key="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
        fi
        sed -i.bak "s|^APP_KEY=.*|APP_KEY=$key|" .env && rm -f .env.bak
    fi
}

# Refuses to wipe data without a typed yes, unless FORCE=1 is set (CI, scripts).
confirm() {
    local prompt="$1"
    if [[ "${FORCE:-0}" == "1" ]]; then
        echo "==> FORCE=1 — skipping the confirmation prompt"
        return 0
    fi
    printf '%s [type yes to continue] ' "$prompt"
    local answer
    read -r answer
    [[ "$answer" == "yes" ]] || { echo "aborted"; exit 1; }
}

# One HTTP check against the running stack: path, expected status.
check() {
    local path="$1" expect="${2:-200}"
    local code
    code=$(curl -sS -o /dev/null -m 20 -w '%{http_code}' "http://127.0.0.1:${WEB_PORT}${path}" || echo 000)
    if [[ "$code" == "$expect" ]]; then
        printf '  \033[32m✓\033[0m %-22s %s\n' "$path" "$code"
    else
        printf '  \033[31m✗\033[0m %-22s %s (expected %s)\n' "$path" "$code" "$expect"
        failed=1
    fi
}

smoke() {
    echo "==> smoke test on http://127.0.0.1:${WEB_PORT}"
    local failed=0
    check /up 200
    check / 200
    check /about 200
    check /products 200
    check /our-next-projects 200
    check /benefits 200
    check /test-results 200
    check /contact 200
    # The two translated trees: a missing locale row shows up here as a 404.
    check /my 200
    check /ru 200
    check /sitemap.xml 200
    check /admin/login 200
    check /no-such-page-anywhere 404
    if [[ "$failed" == 0 ]]; then
        echo "==> all checks passed"
    else
        echo "==> FAILURES — see 'make logs' and 'make logs-web'" >&2
        exit 1
    fi
}

case "${1:-help}" in
    env)       ensure_env ;;
    validate)  compose config -q && echo "docker-compose.yml is valid" ;;
    up)        ensure_env; compose up -d --build ;;
    build)     compose build ;;
    down)      compose down ;;
    destroy)
        confirm "destroy removes the volumes: the MySQL data directory and every uploaded image on this host. Back up first with './run.sh backup'."
        compose down -v --remove-orphans
        ;;
    ps)        compose ps ;;
    logs)      compose logs -f app ;;
    logs-web)  compose logs -f web ;;
    logs-redis) compose logs -f redis ;;
    logs-mysql) compose logs -f mysql ;;
    db)         mysql_root 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' ;;
    restart)   compose restart app ;;
    shell)     compose exec app sh ;;
    artisan)   shift; compose exec app php artisan "$@" ;;
    supervisor) compose exec app supervisorctl status ;;
    migrate)   run php artisan migrate --force -v ;;
    seed)      run php artisan db:seed --force ;;
    fresh)
        confirm "migrate:fresh drops every table and re-seeds — the content in MySQL goes with it."
        run php artisan migrate:fresh --seed --force -v
        ;;
    cache)     compose exec app sh -c 'php artisan config:cache && php artisan route:cache && php artisan event:cache && php artisan view:cache && php artisan filament:optimize' ;;
    smoke)     smoke ;;
    backup)
        out="${2:-$(date +ruma-backup-%Y%m%d-%H%M).sql.gz}"
        mysql_root 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --databases "$MYSQL_DATABASE"' | gzip -9 > "$out"
        ls -lh "$out"
        ;;
    restore)
        [[ -n "${2:-}" ]] || { echo "usage: ./run.sh restore <backup.sql[.gz]>" >&2; exit 1; }
        src="$2"
        compose stop app
        case "$src" in
            *.gz) gunzip -c "$src" ;;
            *)    cat "$src" ;;
        esac | mysql_root 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD"'
        compose up -d app
        echo "restored $src — run './run.sh migrate' if the code has newer migrations"
        ;;
    realtime)  ensure_env; compose --profile realtime up -d ;;

    test)      php artisan test --compact ;;
    pint)      ./vendor/bin/pint --dirty --format agent ;;
    check)     ./vendor/bin/pint --test && php artisan test --compact ;;
    dev)       npm run dev ;;
    assets)    npm run build ;;
    serve)     php artisan dev ;;
    crawl)     php crawl.php "${2:-http://127.0.0.1:8123}" ;;

    help|-h|--help) usage ;;
    *)
        echo "unknown command: $1" >&2
        usage >&2
        exit 1
        ;;
esac

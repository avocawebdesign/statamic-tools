#!/usr/bin/env bash
#
# Does this site still work? Installs from the lock files, builds, boots and renders it.
#
# Written for the updater to run after it changes a dependency, and for a developer to run by
# hand before pushing one. It reads the site; it changes no tracked file.
#
#   bash vendor/avocadesign/statamic-tools/scripts/check-site.sh [--audit] [--keep-env]
#
#   --audit     also report composer and npm advisories, first and from the lock files, so they
#               are reported whatever happens after. Advisories never fail the check: a site is
#               not broken because a dependency has an advisory, and a failing check that nobody
#               can fix that morning is a check people learn to ignore.
#   --keep-env  leave .env.check behind, to poke at what the check saw.
#
# The site's own .env is never touched. The check writes .env.check and runs everything with
# APP_ENV=check, which Laravel loads instead, with Statamic Pro off: a runner has no licence
# key, and nothing the check does needs Pro.
#
# A page whose template redirects, such as an entry that forwards to a file or to another site,
# passes when it has somewhere to go: another site, or a page on this one that answers.
#
# Exit codes: 0 the site renders, 1 it does not, 2 the check could not run.

set -euo pipefail

audit=false
keep_env=false
for arg in "$@"; do
    case "$arg" in
        --audit) audit=true ;;
        --keep-env) keep_env=true ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

fail() { echo; echo "FAILED: $1" >&2; exit "${2:-1}"; }
step() { echo; echo "==> $1"; }

# Quiet while it works, loud when it doesn't: a runner's log is only read when something broke.
output=$(mktemp)
run() { if ! "$@" >"$output" 2>&1; then cat "$output" >&2; return 1; fi; }

[ -f artisan ] && [ -f composer.lock ] || fail "Run this from the site's root folder." 2
command -v php >/dev/null || fail "No php on the PATH." 2
command -v composer >/dev/null || fail "No composer on the PATH." 2
command -v npm >/dev/null || fail "No npm on the PATH." 2

server_pid=""
cleanup() {
    [ -n "$server_pid" ] && kill "$server_pid" 2>/dev/null || true
    rm -f "$output"
    [ "$keep_env" = true ] || rm -f .env.check
}
trap cleanup EXIT

if [ "$audit" = true ]; then
    step "Advisories, for the record"
    composer audit --locked --format=summary || true
    npm audit --package-lock-only --omit=dev || true
fi

step "An environment of its own"
[ -f .env.example ] || fail "No .env.example to build the check's environment from." 2
cp .env.example .env.check
# Written after the copy so these win, whatever the example says.
cat >> .env.check <<'ENV'

APP_ENV=check
APP_DEBUG=false
STATAMIC_PRO_ENABLED=false
STATAMIC_LICENSE_KEY=
STATAMIC_STATIC_CACHING_STRATEGY=null
ENV
export APP_ENV=check
echo "  .env.check, Statamic Pro off"

step "Dependencies from the lock files"
run composer install --no-interaction --prefer-dist --no-progress || fail "composer install"
run npm ci --no-audit --no-fund || fail "npm ci. A Node the site does not ask for is the usual reason: package.json says $(php -r 'echo json_decode(file_get_contents("package.json"), true)["engines"]["node"] ?? "no version";'), this is $(node -v)."
echo "  installed"
# After the install: artisan needs vendor/, which a fresh checkout doesn't have.
run php artisan key:generate --env=check --force || fail "Could not generate a key for the check." 2

step "Assets"
run npm run build || fail "npm run build"
echo "  built"

# Flat file content needs no database. A site that has moved a driver to the database does.
if grep -qE '^(QUEUE_CONNECTION|CACHE_STORE|SESSION_DRIVER)=database' .env.check; then
    step "Database"
    db=$(grep -E '^DB_DATABASE=' .env.check | cut -d= -f2- | tr -d '"')
    [ -n "$db" ] || db="database/database.sqlite"
    [ -f "$db" ] || { mkdir -p "$(dirname "$db")"; : > "$db"; }
    run php artisan migrate --force || fail "migrate"
    echo "  migrated $db"
fi

step "The site's own checks"
run php please stache:refresh || fail "stache:refresh. The content did not load."
php please avoca:site:check --strict || fail "avoca:site:check --strict"

step "Rendering"
port=8391
while lsof -i ":$port" >/dev/null 2>&1; do port=$((port + 1)); done
php artisan serve --port="$port" --no-reload >/dev/null 2>&1 &
server_pid=$!
for _ in $(seq 1 40); do
    curl -fsS -o /dev/null "http://127.0.0.1:${port}/" 2>/dev/null && break
    sleep 0.5
done

# The site says which pages matter: the ones it lists, every page a collection is mounted on, one
# entry from each collection, and then whatever covers the most blocks. Rendering every page of a
# large site would take all morning and tell us no more.
urls=$(php please avoca:site:urls --no-ansi) || fail "avoca:site:urls"
base="http://127.0.0.1:${port}"
# A redirect to the site's own address is followed too, so a site whose APP_URL is set is no different.
app_url=$(grep -E '^APP_URL=' .env.check | tail -n 1 | cut -d= -f2- | tr -d '"' | sed 's:/*$::')

# The path on this site a redirect goes to, or nothing when it goes to another site.
on_site() {
    local location=$1 root
    for root in "$base" "$app_url"; do
        [ -n "$root" ] || continue
        case "$location" in
            "$root") echo "/"; return ;;
            "$root"/*) echo "${location#"$root"}"; return ;;
        esac
    done
}

bad=0
redirected=0
while IFS= read -r url; do
    [ -n "$url" ] || continue
    answer=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "${base}${url}") || true
    code=${answer%% *}
    location=${answer#* }
    case "$code" in
        200)
            printf '  %s %s\n' "$code" "$url"
            ;;
        3??)
            target=$(on_site "$location")
            if [ -z "$location" ]; then
                printf '  %s %s, with nowhere to go\n' "$code" "$url"
                bad=$((bad + 1))
            elif [ -n "$target" ]; then
                then_code=$(curl -s -o /dev/null -w '%{http_code}' "${base}${target}") || true
                printf '  %s %s -> %s %s\n' "$code" "$url" "$then_code" "$target"
                case "$then_code" in
                    200 | 3??) redirected=$((redirected + 1)) ;;
                    *) bad=$((bad + 1)) ;;
                esac
            else
                printf '  %s %s -> %s\n' "$code" "$url" "$location"
                redirected=$((redirected + 1))
            fi
            ;;
        *)
            printf '  %s %s\n' "$code" "$url"
            bad=$((bad + 1))
            ;;
    esac
done <<< "$urls"
if [ "$redirected" -gt 0 ]; then
    echo
    echo "  $redirected page(s) redirect, as their templates ask, and render only as far as the redirect."
    echo "  Where other entries in that collection have pages of their own, list one under urls in"
    echo "  resources/site/updates.yaml so the rest of the template is rendered too."
fi
[ "$bad" -eq 0 ] || fail "$bad page(s) did not render: an error, a missing page, or a redirect to one."

echo
echo "The site installs, builds and renders."

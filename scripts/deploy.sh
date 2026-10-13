#!/usr/bin/env bash
# Pull the latest main into this checkout and run only the steps the incoming
# changes actually need. Called by .github/workflows/deploy.yml on the
# self-hosted runner, but safe to run by hand on the server too:
#
#   scripts/deploy.sh           # deploy whatever changed since the last deploy
#   scripts/deploy.sh --full    # rebuild images, reinstall deps, rebuild assets
#
# Because ./src is bind-mounted into the containers, PHP/Blade changes are live
# the moment the merge lands — no image rebuild needed. Rebuilds only happen
# when the image itself (Dockerfile, docker/, compose file) changes.
#
# Everything is inside main() so bash parses the whole file up front: the
# `git merge` below can rewrite this very script mid-run, and bash otherwise
# reads scripts lazily line by line.
set -euo pipefail

main() {
  local script_dir root_dir branch full=0
  script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  root_dir="$(dirname "$script_dir")"
  branch="${DEPLOY_BRANCH:-main}"
  [[ "${1:-}" == "--full" ]] && full=1

  cd "$root_dir"

  # --- 1. Pull ---------------------------------------------------------------
  # Refuse to deploy over hand-edits to tracked files; a merge would either
  # fail halfway or silently keep a stale hotfix. Untracked files (.env,
  # db_data, backups) are fine.
  if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "ERROR: tracked files were edited on this server — commit or discard them first:" >&2
    git status --short --untracked-files=no >&2
    exit 1
  fi

  local old new changed
  old="$(git rev-parse HEAD)"
  git fetch --quiet origin "$branch"
  git merge --ff-only --quiet "origin/$branch"
  new="$(git rev-parse HEAD)"
  changed="$(git diff --name-only "$old" "$new")"

  echo "[deploy] ${old:0:7} -> ${new:0:7} ($(grep -c . <<<"$changed" || true) files changed)"
  (( full )) && echo "[deploy] --full: running every step"

  # True if any changed path matches the regex (or always, with --full).
  changed_matches() {
    (( full )) && return 0
    grep -qE "$1" <<<"$changed"
  }

  # --- 2. Containers ---------------------------------------------------------
  # Image or stack definition changed: rebuild and recreate. The app
  # entrypoint then runs composer install + migrate + optimize on its own, so
  # steps 3-4 are skipped and we just wait for it to finish booting.
  local rebuilt=0
  if changed_matches '^(docker/|docker-compose\.yml$)'; then
    local since
    since="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "[deploy] container config changed — rebuilding"
    docker compose up -d --build --remove-orphans
    wait_for_app "$since"
    rebuilt=1
  fi

  if (( ! rebuilt )); then
    # --- 3. PHP dependencies -------------------------------------------------
    # Mirrors the entrypoint: production skips dev dependencies.
    if changed_matches '^src/composer\.(json|lock)$'; then
      echo "[deploy] composer.lock changed — composer install"
      docker compose exec -T app sh -c '
        if [ "$APP_ENV" = production ]; then
          composer install --no-dev --optimize-autoloader --no-interaction
        else
          composer install --optimize-autoloader --no-interaction
        fi'
    fi

    # --- 4. Migrations -------------------------------------------------------
    if changed_matches '^src/database/migrations/'; then
      echo "[deploy] new migrations — migrate"
      docker compose exec -T app php artisan migrate --force
    fi
  fi

  # --- 5. Front-end assets ---------------------------------------------------
  # public/build is gitignored, so the server compiles its own. Tailwind scans
  # resources/views and resources/js, so any change under resources/ can add
  # or remove CSS classes. Node runs in the app container (glibc); the `rolldown`
  # probe also catches a node_modules installed for another platform (e.g. the
  # old alpine `node` service), whose native binaries the app can't load.
  if changed_matches '^src/(package(-lock)?\.json|vite\.config\.js|tailwind\.config\.js|postcss\.config\.js)$' \
    || ! docker compose exec -T app node -e "require('rolldown')" >/dev/null 2>&1; then
    echo "[deploy] JS dependencies changed — npm ci"
    docker compose exec -T app npm ci --no-audit --no-fund
  fi
  if changed_matches '^src/(resources/|package(-lock)?\.json$|vite\.config\.js$|tailwind\.config\.js$|postcss\.config\.js$)' \
    || [[ ! -f src/public/build/manifest.json ]]; then
    echo "[deploy] front-end changed — npm run build"
    docker compose exec -T app npm run build
  fi

  # --- 6. Laravel caches -----------------------------------------------------
  # Cheap, and stale cached config/routes/views are the usual "I deployed but
  # nothing changed" culprit, so always refresh them.
  echo "[deploy] refreshing Laravel caches"
  docker compose exec -T app php artisan optimize:clear
  docker compose exec -T app php artisan optimize

  # --- 7. Smoke test ---------------------------------------------------------
  # Hit Laravel's built-in /up health route through nginx.
  local addr
  addr="$(docker compose port nginx 80)"
  if curl -fsS -o /dev/null --max-time 15 "http://localhost:${addr##*:}/up"; then
    echo "[deploy] /up OK — deployed ${new:0:7}"
  else
    echo "ERROR: health check http://localhost:${addr##*:}/up failed" >&2
    docker compose logs --tail 50 app >&2
    exit 1
  fi
}

# After a rebuild the entrypoint runs composer/migrate before php-fpm starts.
# Wait for its "starting php-fpm" line (or give up after 5 minutes) so a
# failed migration fails the deploy instead of passing silently.
wait_for_app() {
  local since="$1" deadline=$(( SECONDS + 300 ))
  echo "[deploy] waiting for app entrypoint (composer, migrate, optimize)..."
  until docker compose logs --since "$since" app 2>/dev/null | grep -q '\[deploy\] starting php-fpm'; do
    if (( SECONDS > deadline )); then
      echo "ERROR: app did not finish booting within 5 minutes" >&2
      docker compose logs --since "$since" --tail 80 app >&2
      exit 1
    fi
    sleep 3
  done
}

main "$@"
exit

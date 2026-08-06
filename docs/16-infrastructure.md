# Infrastructure (Phase 1 — Cursor)

Environment only. **No migrations.** Schema (including `tenant_id` on every
table) is Claude's — see the schema lock in
[14-cross-agent-notes.md](14-cross-agent-notes.md).

## Local stack

```bash
cp .env.staging.example .env   # or merge DB_/REDIS_ keys into your Herd .env
docker compose up -d postgres redis
# Herd can keep serving PHP; point DB_HOST/REDIS_HOST at 127.0.0.1
```

Full app container (nginx + php-fpm + worker + scheduler):

```bash
docker compose up --build
# http://localhost:8080  (APP_HTTP_PORT)
curl -fsS http://localhost:8080/up
```

### Chrome pin

Browsershot hard-codes `headless: 'shell'`. The image and CI install a
**pinned** `chrome-headless-shell` build:

```
CHROME_HEADLESS_SHELL_VERSION=150.0.7871.24
```

Bump it only together with the locked `puppeteer` in `package-lock.json`, and
keep `.github/workflows/ci.yml` on the same tag.

## CI services

`Test suite` brings up Postgres 16 and Redis 7 as service containers (health-
checked). PHPUnit still uses in-memory SQLite today so the suite stays fast and
does not race Claude's tenancy migrations. The services prove the runners can
reach both before the schema cutover.

## Staging

`.env.staging.example` mirrors `.env.production.example`: `APP_DEBUG=false`,
encrypted sessions, pgsql, redis queue/cache/session, SMTP, JSON stderr logs,
Sentry DSN. Deploy the compose stack (or the same shape on your host) with that
file as the template.

Optional GitHub Action [uptime.yml](../.github/workflows/uptime.yml) curls
`${{ secrets.STAGING_URL }}/up` every 15 minutes when the secret is set.

## Observability

| Piece | Where |
|---|---|
| Correlation id | `App\Support\AssignRequestId` → `X-Request-Id` + `Log::shareContext` |
| JSON logs | `LOG_STACK=stderr_json,daily` (`config/logging.php`) |
| Sentry | `sentry/sentry-laravel`, `SENTRY_LARAVEL_DSN` (no-op when empty) |
| Health | Laravel `/up` (compose nginx healthcheck + optional uptime workflow) |

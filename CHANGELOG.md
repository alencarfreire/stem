# Changelog

## Unreleased

## 0.5.0 — 2026-09-09

### Added
- `$r->files()` — `$_FILES` copy per request (worker-safe)
- `$r->download($path, $filename)` — attachment response
- `Stem\Integrations\Cors::allow()` — call on a branch; OPTIONS → 204
- `fromGlobals()` method override: `X-HTTP-Method-Override` and POST `_method`

## 0.4.0 — 2026-09-09

### Added
- `$r->ctx($key)` / `$r->ctx($key, $value)` — request-scoped bag (no leak across `handle()`)
- `$r->onUuid()` / `$r->isUuid()` — UUID segment, no regex
- `$r->when($pred, $cb)` — 0-arg predicate; seals on true

## 0.3.0 — 2026-09-09

### Added
- `$r->isInt()` / `$r->isParam()` — exact remaining segment (does not swallow `/users/1/posts`)
- `$r->run(callable(Request): void)` — compose routing files without sealing on miss
- `examples/app/` — indicated PDO + repository + route-file layout (documented on Pages)
- `$r->branches(['users' => $cb, ...])` — O(1) next-segment dispatch (Roda `hash_branches`)

## 0.2.0 — 2026-09-09


### Added
- `405 Method Not Allowed` with `Allow` when a leaf path was visited with the wrong verb
- Automatic `OPTIONS` → `204` + `Allow` when verbs were collected
- `HEAD` matches `get()`; `Response::send()` omits the body
- `$r->redirect()`, `$r->noContent()`, `$r->cookie()` / `Response::withCookie()`
- `$r->jsonBody()`, `queryParam()`, `form()` / `formParam()`, `bearerToken()`, `wantsJson()`
- `$r->options()`
- `App::notFound()` and `App::error()`

### Changed
- Branch taken with no leaf is **404**, not empty 200 (`GET /users/abc` under `on('users')` + `onInt`)
- Wrong method on a visited path is **405**, not empty 200

## 0.1.0 — 2026-09-09

First tagged release: executable routing tree, FPM/FrankenPHP/RoadRunner/Swoole, docs EN/pt-BR.

# Changelog

## Unreleased

### Added
- `$r->isInt()` / `$r->isParam()` — exact remaining segment (does not swallow `/users/1/posts`)
- `$r->run(callable(Request): void)` — compose routing files without sealing on miss

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

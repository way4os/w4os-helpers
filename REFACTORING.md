# Refactoring of opensim-helpers into a Slim 4 project

Decided 2026-10-20. The old scripts (`query.php`, `currency.php`, `offline.php`...) are NOT kept as they are: priority is a clear
structure, Slim 4, PSR-4, PSR-7/15, standards, the current engine (`way4os/w4os-engine`) and its OpenAPI.
Work step by step; each step ends green (`vendor/bin/pest`), is committed alone, and is ticked here. Resume at the first unticked box.
Economy of effort: port the logic, not the history; move code, do not rewrite what works; one test run per step.

## Rules

- The engine owns the grid knowledge (config, databases, models, services, formats). The helpers are HTTP front-ends: no ini parsing,
  no SQL of their own for what a model gives (`Grid`, `Avatar`, `Region`, `Service`...), see `opensim-engine/DEVELOPERS.md`.
- Fixed formats stay as OpenSim and the viewers want them (query, register, offline, currency, landtool, get_grid_info, parser):
  same URLs, same request and response bodies. Everything else (pages, health, guide, search page, profile) is free to change.
- Config is the official one (Robust.ini, OpenSim.ini) read through the engine; `Helpers.ini` only for what has no official place.
- Errors are explicit (RFC 9457 where JSON, the codes the viewers expect where fixed), never an intentional 500.
- PHP >= 8.2, PSR-12/PER, no globals, no `require` of scripts, no GLOB_BRACE, gettext `_()`.
- Every endpoint is documented in an OpenAPI document (fixed-format ones and pages too).

## Target layout

```
public/index.php            the only entry (front controller); web server rewrites everything to it
src/
  App.php                   builds the Slim app: container, middleware, routes
  Http/Middleware/          GridMiddleware (OPENSIM_GRID -> engine Grid), ErrorMiddleware, CorsMiddleware
  Http/Action/              one invokable class per endpoint (Query, Register, Offline, Currency, Landtool, Motd, Parser,
                            EventsParser, TextGen, GridInfo, Health, Search, Profile, Guide, Home, Welcome)
  Service/                  Cache, Guide source, Search registry, Events parser (the logic the actions call)
  View/                     Twig environment, layouts/pages/components
  Addon/                    Gloebit, Podex (currency addons)
config/                     container definitions, routes.php
templates/                  layouts/ pages/ components/ (kept, adapted)
tests/                      Pest: Unit/ (services), Feature/ (actions through Slim, PSR-7 requests), Packaging/
docs/api/                   openapi.yaml of the helpers (generated or hand kept, see step 9)
```

## Mapping of the existing code

| Old | New |
|---|---|
| `index.php`, `classes/class-router.php` | `public/index.php`, `src/App.php`, `config/routes.php` |
| `includes/bootstrap.php`, `functions.php`, `databases.php`, `config.example.php` | container definitions + engine `Environment`/`Grid`; delete |
| `query.php`, `includes/search*.php`, `search-parser.php` | `Action\Query`, `Action\Search`, `Service\SearchRegistry`, `Service\SearchParser` |
| `register.php` | `Action\Register` (GET /search/register) |
| `offline.php` | `Action\Offline` (engine `Services\OfflineMessages`) |
| `currency.php`, `landtool.php`, `includes/economy.php`, `addons/` | `Action\Currency`, `Action\Landtool`, engine `Economy\Currency`, `Addon\*` |
| `motd.php`, `parser.php`, `eventsparser.php`, `textgen.php` | `Action\Motd`, `Parser`, `EventsParser`, `TextGen` |
| `classes/class-grid-info.php`, `class-grid-config.php` | engine `Grid`, `GridInfoCache` |
| `classes/class-cache.php`, `class-guide.php` | `Service\Cache`, `Service\Guide` |
| `classes/class-view.php`, `class-blocks.php`, `blocks/*` | `View\*` + Twig components |
| `includes/profile-publish.php` | `Action\Profile` + engine `Avatar` |
| `includes/2do-polyfill.php`, `htaccess` | delete / web server snippet of the engine presets |

## Steps

- [x] 1. Skeleton: `slim/slim`, `slim/psr7`, `php-di/php-di` in composer.json; `public/index.php`, `src/App.php`, container,
      `GridMiddleware`, `ErrorMiddleware`; route `GET /health` answering from the engine; Pest Feature test helper that sends PSR-7
      requests to the app without a web server. Old files stay untouched and unused.
- [x] 2. Grid info and pages shell: `/get_grid_info` (engine), Twig `View` with the existing layouts/pages/components, `/` and `/welcome`.
- [x] 3. Fixed-format services: the engine already serves them (`Api\Handler`): offline, search query/register, currency, landtool, motd,
      text images. `Action\Engine` routes the addresses the grid is configured with (`Grid::searchApi/registerUrl/offlineUrl/motdUrl`, `economy()` + `currency.php`/`landtool.php`, never hardcoded names) to the engine's; the whole
      API is mounted at `/api` (so `/api/openapi.json`). `parser`/`eventsparser` are engine tasks (`POST /api/tasks/{id}/runs`), no helper script.
      Left for step 6: check each former argument still works (`textgen` args, `motd` username, `query` gk) against real requests.
- [x] 4. Pages, as the engine's OpenAPI (`docs/api/openapi.json`) describes them: `/search`, `/welcome`, `/guide`, `/profile/{name}`, `/health`
      (management: Bearer API key), `/grid_status` (HTML or text, keyword ONLINE/OFFLINE/DEGRADED/MAINTENANCE), `/grid_status.rss`. Addresses are the
      grid's (`Grid::address(<service key>)`), never fixed; `/get_grid_info` is Robust's, not ours. Blocks read the engine models.
      - [x] `Site` dispatcher (engine services, then pages, `/blocks/{name}`), blocks grid-info, grid-status, motd, destination_guide, health
      - [x] grid_status as text + RSS (DEGRADED from the monitor; MAINTENANCE not produced yet); `/get_grid_info` kept on purpose (a viewer keeps what it says, not the first address)
      - [x] blocks search, profile, popular_places, directory_info (search database through `Connections::pdo`; SQL still here, to move to the engine models later)
      - [x] menu, assets (theme of the grid first, `/assets/...`), logo, sidebars, theme templates (`<grid>/theme/templates`)
- [x] 5. Config: `Helpers.ini` through the engine; remove `includes/` and `classes/`; delete the old scripts.
- [x] 6. OpenAPI: the engine's document is the contract (pages and services included, served at `/api/openapi.json`); the pages the helpers add
      (`/directory_info`, `/blocks/{name}`, `/assets/{file}`) were documented in the engine; a test checks every served page is in it.
      Still to check on real requests: the former arguments (`textgen`, `motd` username, `query` gk).
- [x] 7. Packaging: `dev/build.sh` builds the deb and the zip with `public/`, `src/` and the Slim dependencies in `vendor/` (checked: stage content).
      Still to do: `tests/Packaging/check` in a container (not run), and the document root of the web server presets of the engine
      (`webroot-helpers`: `docroot = /var/www/html`) must become `/usr/share/opensim-helpers/public` (engine tests expect the former).
- [x] 8. Docs: README (practical questions), DEVELOPERS.md (layout above), CHANGELOG one line.
- [x] 9. Cleanup: phpcs, `composer update way4os/w4os-engine`, full suite, remove this file's "Steps" or tick all.

## Notes for whoever resumes

- The working tree had uncommitted edits in `classes/blocks/` (user's): do not commit them with the refactoring; port them when
  the block is moved (step 4).
- Tests run on the containers host: from the kit root `PROJECTS="opensim-rest-php opensim-engine opensim-helpers" RUN=opensim-helpers tests/Packaging/distro-pest`.
- After adding a class, `composer dump-autoload` (authoritative classmaps).

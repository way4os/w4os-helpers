# OpenSim Helpers development rules

**Never use code or concepts related to projects consuming this library**, it must be agnostic and work with any project. The config of the grid (`/etc/opensim/grids/<grid>/`) is not one of them: it is the structure the projects share, the helpers read it as they read an `includes/config.php`.

## Architecture

A [Slim 4](https://www.slimframework.com) application (PSR-7/15, PHP-DI, PSR-4 `OpenSim\Helpers\` in `src/`). The plan of the refactoring and its state are in `REFACTORING.md`.

The engine ([opensim-engine](https://github.com/magicoli/opensim-engine)) owns what is known of the grid: the config (Robust.ini, OpenSim.ini, `Helpers.ini`, read as OpenSimulator reads them), the databases, the models (`Grid`, `Avatar`, `Region`...), the services (offline messages, search, currency, land tools, message of the day) and its API (served by its own entry point, `public/index.php` of the engine, never by the helpers). The helpers are the web front-end: they find the grid, give the engine the request, and draw pages. No ini parsing, no constants, no globals.

- `public/index.php` is the only entry; the web server sends everything that is not a file to it, with `OPENSIM_GRID` naming the grid when the machine has several.
- `GridMiddleware` finds the grid (the `grid` attribute of the request) and removes the path of the helpers from the request (`/helpers`), so the routes do not know it. No grid: 503.
- The engine's API is not the helpers': `/api/...` is a 404 here; the engine serves it from its own entry point.
- Everything else goes to `Http\Action\Site`: first the services of the simulators and the viewers (`Engine`: the engine serves them at the addresses the grid is configured with, `Grid::searchApi()`, `registerUrl()`, `offlineUrl()`, `motdUrl()`, and `economy()` + `currency.php` / `landtool.php`: never fixed names in the code), then the pages at the addresses the grid gives them (`search`, `welcome`, `guide`, `web-profile`, `grid-status`, `grid-status-rss` of its services), `/get_grid_info`, `/directory_info`, `/health` (management: Bearer API key), `/blocks/{name}`, `/assets/{file}` (the files of the helpers) and `/pictures/{uuid}.{jpg|png|gif|webp}` (a picture of the grid, a texture or a profile picture, served by the engine as its public `/assets/{id}.{format}`).
- A block is a piece of a page: a class of `src/Block/` gives its data (`Block::data(Grid, args)`), the template `templates/components/<name>-block.twig` shows it. In a template a block is a variable of its name, dashes as underscores (`{{ grid_info }}`). A site that embeds the grid reads a block as a fragment at `/blocks/<name>`, or its data with `?format=json`.
- `templates/` has `layouts/`, `pages/` and `components/`, `assets/` the css, js, images and fonts. A viewer (User-Agent) gets the content alone, with the sidebars on the splash page only. A grid replaces any of them in its `theme/` folder (`theme/templates`, `theme/assets`).
- Every address is in the OpenAPI document of the engine, the ones the helpers add included; a test checks it.

Tests are Pest: `tests/Feature` sends PSR-7 requests to the application without a web server, `tests/Unit` and `tests/Environment` check the rest, `tests/Packaging` the packages in a container.

## Build

`dev/build.sh` makes what the project distributes into `dist/`, from the last commit (it refuses when changes are not committed or when `composer.lock` is not up to date; `DIRTY=1` builds the last commit anyway):

- a Debian package, `opensim-helpers_<version>_all.deb`, the application in `/usr/share/opensim-helpers`, to be served by a web server whose document root is its `public` folder: its `vendor` folder has the third-party libraries (Slim, PHP-DI, Twig), and `opensim-engine` and `opensim-rest-php` are links to the packages of those names, which it depends on. Its config is the one of the grid in `/etc/opensim/grids/<grid>/`, with `Helpers.ini` for what has no official place (made by hand from `config/Helpers.example.ini`, or by the OpenSim kit);
- a zip, `opensim-helpers-<version>.zip`: the files with a complete `vendor` folder (the engine and rest-php included, as files), to unzip and use without composer.

`dev/build.sh deb` or `dev/build.sh zip` makes one. What is distributed is what git tracks (so what `.gitignore` ignores is not there) without what `.distignore` lists, plus the `vendor` folder composer makes without the development tools, from the repositories of `composer.json` (a path repository in development, else Packagist). The work is done on copies, the `vendor` folder of the project is not touched. The scripts are those of [build-tools](https://github.com/magicoli/build-tools) (`vendor/bin/build-tools`, a development dependency); the project keeps its own `packaging/`: the nfpm definition `opensim-helpers.yaml`, `build` (the files of the package), `siblings` (the projects of the family the Debian package gets from their own packages), `opensim-helpers.preinst`. It needs nfpm and composer. `.libignore` is another list, for `tools/install-library.sh` (the helpers installed as a library in another project).

`tests/Packaging/check` tries both in a clean container (podman) with the Debian packages of opensim-engine and opensim-rest-php (`DEPS_ENGINE=` and `DEPS_REST=` say where they are, by default the `dist` folders of the sibling projects): the home page must answer as an unprivileged user with a config made from the example. `PACKAGING=1 vendor/bin/pest` runs it after the build where podman is available. What the container needs is sent to it as a tar stream: `CONTAINER_CONNECTION=name` (a `podman system connection`) runs it on the podman of another machine, `MEMORY=` sets its memory (default 400m); both can be set in `tests/.env` (see `tests/.env.example`), read after the `.env` of the project (bash-tools `read_env`: what the files set wins over the environment of the command).

## Release

One command in each project, in the order of the family (rest-php, engine, helpers, kit), each one completely before the next:

```bash
dev/release.sh          # the whole release, after one question
dev/release.sh status   # what is done and what remains
dev/release.sh beta     # patch|minor|major|stable|dev|alpha|beta|rc|1.2.3-beta.4: see the versions in the README of build-tools
```

It needs the `.env` of the project to say where the apt repository is (`APT_REPO_DIR`, see `.env.example` of build-tools), `gh` (logged in) and `nfpm`, and says what is missing before it does anything. `dev/switch.sh dev|release` does the composer part alone: the projects of the family linked next to this one (path repositories, `@dev`), or required by version (`^` the latest version tag of each, refused when a project changed since that release).

## Shell scripts

The scripts of `dev/` are wrappers of build-tools. `tests/Packaging/check` uses the functions of [bash-tools](https://github.com/magicoli/bash-tools) to ask, tell and fail (`log`, `success`, `warning`, `die`, `end`, `yesno`, `require`, `usage`, `read_env`), loaded from `vendor/bin/bash-helpers`: write the new ones the same way, not with their own prompts and `echo`.

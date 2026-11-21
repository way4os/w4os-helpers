# w4os helpers

![Stable](https://img.shields.io/github/release/way4os/w4os-helpers?label=stable&color=green&include_prerelease)
![GitHub Tag](https://img.shields.io/github/tag/way4os/w4os-helpers?label=latest&include_prereleases)
![GitHub commits since latest release](https://img.shields.io/github/commits-since/way4os/w4os-helpers/latest?label=dev)
![PHP](https://img.shields.io/badge/PHP-8.2+-7884bf)
[![License](https://img.shields.io/badge/license-AGPL--3.0-552b55)](LICENSE)
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/way4os/w4os-helpers/total)
[![Donate](https://img.shields.io/badge/-Donate-yellow)](https://magiiic.org/donate/)

This branch is in development, it is intended for integration in another project.

Use master or 3.x branch instead for up-to-date code.

<https://github.com/way4os/w4os-helpers>

## Description

A [Slim 4](https://www.slimframework.com) application, the web front-end of [w4os-engine](https://github.com/way4os/w4os-engine), to enable OpenSimulator features that are not implemented in the core, like search, currency, events in OpenSimulator grids (see Features below).

This library can be used as-is, or integrated into another project.

Some projects based on this library:

- [w4os (WordPress plugin)](https://w4os.org)
- [2do.directory](https://2do.directory)
- [OpenSim Kit (Linux OpenSimulator framework)](https://github.com/way4os/opensim-kit)

## Requirements

PHP 8.2 or newer, with the `curl`, `dom`, `filter`, `intl`, `json`, `mbstring`, `pdo`, `session` and `simplexml` extensions, plus those of [w4os-engine](https://github.com/way4os/w4os-engine). `gettext` is recommended, for the translations: without it the messages stay in English. `imagick` is needed by the text images (`textgen.php`) only.

On Debian and Ubuntu: `sudo apt install php-cli php-curl php-intl php-mbstring php-mysql php-xml`, and `php-imagick` for the text images.


## Installation

The helpers read their settings from the grid config files, by default `/etc/opensim/grids/<grid>/Robust(.HG).ini` and `/etc/opensim/grids/<grid>/OpenSim.ini` (and the `sims/*.ini` of its simulators), in the format of OpenSimulator. They can be grouped with the same section structure in `/etc/opensim/grids/<grid>/Helpers.ini` instead, for a web host that has no OpenSimulator on it.

**Web server.** The document root is the `public` folder (`/usr/share/w4os-helpers/public` with the package); what is not a file goes to its `index.php`, with `OPENSIM_GRID` set to the nick of the grid when the machine has several (the files `opensim web <grid> snippet caddy|nginx|apache` writes do it).

**What the helpers write.** They run as the user of the web server (`www-data`), who cannot write the folders of the install. They keep the state of their tasks (the search index fills itself at the end of a visit) and their cache (the pictures) in `/var/cache/w4os-helpers/tasks` and `/var/cache/w4os-helpers/cache`, which the package makes for `www-data`. On a manual install, or with a web server that runs as another user, make these two folders for it, or give the helpers other ones with `OPENSIM_TASKS_ROOT` and `OPENSIM_CACHE_ROOT` (set like `OPENSIM_GRID`). The health page (`cache`: `assets`, `tasks`) says whether they can be written.

**One source of truth.** A setting that has its place in OpenSim.ini or Robust.ini is read from there and nowhere else: do not copy it. `Helpers.ini` may hold any setting, in the same sections, but it only fills what the other files leave out (it is the file of a web host without OpenSimulator, and of the few settings that have no official place, in `[Helpers]`). All those files may hold passwords: mode 640, group of the web server.

```ini
;; In OpenSim.ini (or the ini of a simulator): what the simulators call
[Search]
  Module = OpenSimSearch
  SearchURL = "https://www.yourgrid.org/helpers/query.php"

[DataSnapshot]
  DATA_SRV_YourGrid = "https://www.yourgrid.org/helpers/register.php"
  ;; Another directory too: DATA_SRV_2do = "https://2do.directory/helpers/register.php"

[Messaging]
  OfflineMessageURL = "https://www.yourgrid.org/helpers/offline.php"

[Economy]
  ;; BetaGridLikeMoneyModule (the default): everything is free, the helpers do nothing. With any other module
  ;; (Gloebit...), currency.php and landtool.php answer the viewers and the regions (cost, purchase, land, balance,
  ;; transfers) through the module of the region; when it cannot add the currency itself, the user is sent to its page.
  ;; Gloebit: its sandbox is [Gloebit] GLBEnvironment, its packs and prices are in addons/gloebit.php.
  economymodule = Gloebit
```

```ini
;; In Robust.ini (Robust.HG.ini): what the viewers get
[Const]
  WebURL = "https://www.yourgrid.org"

[GridInfoService]
  gridname = "Your Grid"
  login = "${Const|BaseURL}:${Const|PublicPort}/"
  welcome = "${Const|WebURL}/welcome"
  ;; The viewers replace [AGENT_NAME] by Firstname.Lastname; the helpers also take Firstname-Lastname
  web_profile_url = "${Const|WebURL}/profile/[AGENT_NAME]"
  ;; The viewers add the name of the script: <economy>/currency.php, <economy>/landtool.php
  economy = "${Const|WebURL}/helpers"

[LoginService]
  WelcomeMessage = "Welcome to Your Grid, <USERNAME>!"
  MessageUrl = "${Const|WebURL}/helpers/motd.php"
  DestinationGuide = "${Const|WebURL}/helpers/guide.php"

[DatabaseService]
  ConnectionString = "Data Source=localhost;Database=robust;User ID=opensim;Password=*****;Old Guids=true;"

;; A service that has a database of its own has it here too, the helpers choose as OpenSimulator does
; [UserAccountService]
;   ConnectionString = "Data Source=localhost;Database=accounts;User ID=opensim;Password=*****;"
```

```ini
;; In Helpers.ini: what has no official place
[Helpers]
  ;; The destinations of the guide: a file (in the folder of the grid) or a URL, "Name|Region URL" lines,
  ;; a name alone starts a category
  GuideSource = "destinations.txt"
  ;; The registry of the search is Robust's database unless it has its own
  SearchConnectionString = "Data Source=localhost;Database=ossearch;User ID=opensim;Password=*****;"
  HypEventsURL = "https://2do.directory/events"
  ;; Whatever this folder holds (templates/, assets/) replaces the files of the helpers
  ThemePath = "theme"
  ;; Public pages of the grid, served by default at <helpers>/grid_info and <helpers>/grid_status
  ;GridInfoURL = "${Const|WebURL}/helpers/grid_info"
  ;GridStatusURL = "${Const|WebURL}/helpers/grid_status"
  ;; LogoURL, MailSender, UseUTCTime (true by default)
```

**The files of the grid.** The helpers read the ini files of the OpenSimulator environment the way its executables do: each one with the files it includes (`Include-` in any section, in the other files too) and the `${Const|...}` substitutions. Every setting is where OpenSimulator has it, under its own name: `MoneyScriptAccessKey` of the MoneyServer is read in `[MoneyServer]` of `MoneyServer.ini`, kept beside `Robust.ini` and `OpenSim.ini` with the conventions of the MoneyServer, and the settings of Gloebit as `Gloebit.ini.example` has them, in `OpenSim.ini` or in a file it includes. `Helpers.ini` has only what OpenSimulator has no place for. The key that ties a quote of currency to its purchase is the one of the MoneyServer, `MoneyScriptAccessKey` in `MoneyServer.ini`: the setup makes the whole file from the original of the MoneyServer, with that key at random, since the server needs it too. There is no default key: without it `currency.php` and `landtool.php` do not serve and answer a bad configuration.

**Addresses.** The helpers answer the addresses the grid gives to its services, those of this site only (the host, and the port when the address has one, of the page that is served): a service the grid has set up on another site (`SearchURL = "https://2do.directory/helpers/query.php"`) has no ghost on this one. A service the grid says nothing about answers at its default address, `/helpers/<script>.php`. Only `currency.php` and `landtool.php` have a fixed name, under the address of the economy. `/search` is the web search (`search` of `[GridInfoService]`, which the viewers open for their web search tab): `?q=` for the words, `/search/places/`, `land`, `events`, `classifieds`, `people` for a kind of results, each place a link a viewer follows. `/profile/<First.Last>` is the page of an avatar (`web_profile_url`): its name, its member since, and what its profile published (a profile that is not published shows no more), its picks and its classifieds. The search registry is filled when a simulator registers, again 90 seconds later (its regions are not all loaded when it registers), then by the search whenever a simulator is due, no cron job is needed (the tasks of the engine can be run by one). `/get_grid_info` (what the grid says of itself, so a viewer that reaches the web site can get the parameters of the grid) and `/grid_status` (its state: ONLINE, DEGRADED or OFFLINE, as text with `?format=text`, and `/grid_status.rss`) are public pages; `GridStatus = "${Const|WebURL}/helpers/grid_status"` in `[GridInfoService]` makes Firestorm show that state. `/health` lists what the grid says (`get_grid_info`), then every address of its config with its status. The menu of the top bar lists the pages the site serves (home, destinations, directory, health). The pictures of the grid a page shows (a profile picture, a map tile) are at `/pictures/<uuid>.<jpg|png|gif|webp>`. The API of the engine is not served by the helpers: it has its own entry point (`/usr/share/w4os-engine/public`). `/health` is a page for who manages the grid: it needs an API key of the engine (`Authorization: Bearer <key>`, made with `opensim api-key create`). What is not served says why: 404 for an address that is nothing, 405 for a wrong method, 400 for a call without what it needs, 503 for a configuration that is missing (never a 500 on purpose). The address of the helpers itself (`/helpers`, the economy of the grid) and `/profile` without a name are not pages: they answer 418, a teapot, unless the grid gives that address to a page (`welcome`).

**The search of the web** has two settings in the Robust config: `search` of `[GridInfoService]` (what `get_grid_info` says: Firestorm receives it from there, for its web search tab and the parameters of the grid, and it is the one the helpers use) and `SearchURL` of `[LoginService]` (the documented one, which alone does not reach Firestorm in its default mode). They should say the same: the setup gives the address of one to the other when it has none, and leaves two different addresses as they are. Neither is `SearchURL` of `[Search]` in `OpenSim.ini`, the service the simulators call (`query.php`), which the search of the viewer uses. `/health` tests `search`.

**Databases.** Each query uses the database of its service: the connection string (.NET format) of the section of the service when it has one, else `[DatabaseService]`.


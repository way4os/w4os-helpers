# OpenSimulator Helpers

![Stable](https://img.shields.io/github/release/GuduleLapointe/opensim-helpers?label=stable&color=green&include_prerelease)
![GitHub Tag](https://img.shields.io/github/tag/GuduleLapointe/opensim-helpers?label=latest&include_prereleases)
![GitHub commits since latest release](https://img.shields.io/github/commits-since/GuduleLapointe/opensim-helpers/latest?label=dev)
![PHP](https://img.shields.io/badge/PHP-8.2+-7884bf)
[![License](https://img.shields.io/badge/license-AGPL--3.0-552b55)](LICENSE)
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/GuduleLapointe/opensim-helpers/total)
[![Donate](https://img.shields.io/badge/-Donate-yellow)](https://magiiic.org/donate/)

This branch is in development, it is intended for integration in another project.

Use master or 3.x branch instead for up-to-date code.

<https://github.com/magicoli/opensim-helpers>

## Description

Collection of PHP scripts to enable OpenSimulator features that are not implemented in the core, like search, currency, events in OpenSimulator grids (see Features below).

This library can be used as-is, or integrated into another project.

Some projects based on this library:

- [w4os (WordPress plugin)](https://w4os.org)
- [2do.directory](https://2do.directory)
- [OpenSim Kit (Linux OpenSimulator framework)](https://github.com/GuduleLapointe/opensim-kit)

## Requirements

PHP 8.2 or newer, with the `curl`, `dom`, `filter`, `gettext`, `intl`, `json`, `mbstring`, `pdo`, `session` and `simplexml` extensions, plus those of [opensim-engine](https://github.com/magicoli/opensim-engine). `imagick` is needed by `textgen.php` only.

On Debian and Ubuntu: `sudo apt install php-cli php-curl php-intl php-mbstring php-mysql php-xml`, and `php-imagick` for `textgen.php`.

The `xmlrpc_*` functions, needed by OpenSimulator for the search, the currencies and the land exchanges, are not an extension to install: `includes/xmlrpc-polyfill.php` provides them on top of `phpxmlrpc/phpxmlrpc`.

## Installation

The helpers read their settings from the grid config files, by default `/etc/opensim/grids/<grid>/Robust(.HG).ini` and `/etc/opensim/grids/<grid>/OpenSim.ini` (and the `sims/*.ini` of its simulators), in the format of OpenSimulator. They can be grouped with the same section structure in `/etc/opensim/grids/<grid>/Helpers.ini` instead, for a web host that has no OpenSimulator on it.

The historic config is still supported, but might be deprecated in the future: constants in `includes/config.php` (copy `includes/config.example.php`). When that file exists, it is the only config read.

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
  ;; LogoURL, MailSender, UseUTCTime (true by default)
  ;; The secret that ties a quote of currency to its purchase. A grid with a MoneyServer has it already, MoneyScriptAccessKey
  ;; of its [MoneyServer] (MoneyServer.ini): the helpers read it there. Without one, each grid has a key of its own, made of the
  ;; password of its database.
  ; ScriptKey = "a secret"
```

**Addresses.** The helpers answer the addresses the grid gives to its services, those of this site only (the host, and the port when the address has one, of the page that is served): a service the grid has set up on another site (`SearchURL = "https://2do.directory/helpers/query.php"`) has no ghost on this one. A service the grid says nothing about answers at its default address, `/helpers/<script>.php`. Only `currency.php` and `landtool.php` have a fixed place, under the address of the economy. `/health` lists every address of the grid with its status. The menu of the top bar lists the pages the site serves (home, destinations, directory, health).

**Databases.** Each query uses the database of its service: the connection string (.NET format) of the section of the service when it has one, else `[DatabaseService]`.


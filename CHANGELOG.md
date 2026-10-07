## Changelog

### Unreleased

- update: the functions the engine has (destinations, teleport links, region lookups, XML answers and errors, currency, XML-RPC polyfill) are the engine's, the helpers keep only what is theirs
- new: the top bar has a menu of the pages the site serves (home, destinations, directory, health), the current one marked
- update: the ini files are read with the files they include (`Include-`) and their constants, as the OpenSim executables do
- fix: no default currency key: without `MoneyScriptAccessKey` (`[MoneyServer]` of `MoneyServer.ini`) the currency is not served, bad configuration
- update: the health page uses the engine's `OpenSim_Health`: sections and settings as in the ini files, each API asked both ways
- update: health is one table by config file, cert, time and result aligned on the right, nothing said of what is not defined
- update: health starts with what the grid says (get_grid_info), compares it with the config, calls each service without data and with valid data
- update: the ini files are read by the engine's `OpenSim_Config`, the helpers keep the grid, the constants and the routes
- new: the web search page (`search` of the grid info): places, land for sale, events, classifieds, people, a link a viewer follows for each place
- new: the page of an avatar at `web_profile_url`: what its profile publishes, its picks, its classifieds; a 404 for one that is not there
- new: the popular places block, the search form on the home page
- fix: the search registry is read by the helpers when a simulator registers (not by a request to their own address, which their PHP may not trust) again 90 s later (its regions are not all loaded when it registers), then when a host is due, by the search (once a minute at most): no cron job needed
- fix: a registration (`DATA_SRV_`) that points to `query.php` no longer takes the place of the search at that address: the script an address names answers it
- update: the web search is `search` of `[GridInfoService]`, `SearchURL` of `[LoginService]` stands for it; health compares both
- new: public pages `grid_info` and `grid_status` (ONLINE or OFFLINE) under the helpers, `GridInfoURL` and `GridStatusURL` in `[Helpers]`
- update: `/profile` without a name and the address of the helpers itself answer 418, not a page
- fix: the search never makes its `regions` table in the database of Robust (it took the place of Robust's); `regionsregister` unless a search table of that name is already there
- fix: a simulator that registers is let go at once (length and closed connection when the server has no fastcgi_finish_request), it was held while its snapshot was read, which came out empty
- new: `avatar_publish_update` (query.php): the "show in search" box of a profile, which Robust ignores, told by the OpenSimSearch module and written in `userprofile`, for a caller that holds the secure session of the avatar
- fix: a search request without flags or without the day and category is answered, not a 500
- update: a wrong call gets its code (400, 404, 405, 503 for a missing configuration), never a 500 on purpose
- update: currency.php and landtool.php serve any economy module but BetaGridLikeMoneyModule, no `Currency*` setting
- update: the Gloebit sandbox is `[Gloebit] GLBEnvironment`; the rate is 4 for 1000 unless the module's addon says
- update: an address of the grid is served when its host (and its port, if it gives one) are the ones of the page served
- update: the profile address is `web_profile_url` of the grid info (`[AGENT_NAME]`), also `Firstname-Lastname`
- fix: the home page shows the destination guide, the block is `destination_guide`
- update: one source of truth, the settings are read from Robust.ini, OpenSim.ini and the simulators' ini, in their format (README)
- update: `Helpers.ini`, same format, holds the settings that have no official place, in `[Helpers]`, and a web host's reduced copy
- update: each query uses the database of its service (accounts, grid users, presence, regions, offline messages, profiles)
- update: the routes are the addresses the grid gives to its services, on this site only: no ghost service for one set elsewhere
- removed: `helpers.ini` with its own copy of the grid name, the addresses and the database, `[Urls]`, `[robust_db]`
- new: one layout for every page, with the main content of the page between a left sidebar and a right one
- new: blocks (grid info, grid status, message of the day) are variables of the templates (`{{ grid_info }}`), and alone at `<prefix>/blocks/<name>`
- new: the pages show the content alone to a viewer (the splash page has its sidebars), a browser has the top bar and the footer too
- new: `/health` lists the addresses of the grid and the services of the helpers, with their status (`?format=json` for a monitor)
- new: `/profile/<first>.<last>`, `/profile/<first>-<last>` and `/@<first>.<last>` are routed to the profile page
- update: the files of the pages are in `assets/` (`<prefix>/assets/`: `css`, `js`, `images`), the scripts of pages in `pages/`
- update: `directory_info.php` is a page of the grid layout, one query per grid
- removed: the page system of the former wizard (`init.php`, `class-grid.php`, `class-page.php`, `class-form.php`...), nothing used it
- fix: `directory_info.php` failed on its second visit (an empty cache was read back as text)
- fix: `eventsparser.php` failed on the first event (`opensim_latin1_only()` did not exist)
- new: `guide_source` in `helpers.ini` gives the destinations of the guide (a file of the grid folder, a path or a URL)
- fix: `guide.php` shows nothing behind the router, and its pages show what the file holds as text
- fix: `textgen.php` reads `size` (the font size was never read), `inpage`, and says when it has no Imagick
- fix: `directory_info.php` and the locale warned on a request without `Accept-Language` or without grids
- fix: with a money server, every transfer failed (an invalid lock, two functions that did not exist)
- fix: buying currency for a land no longer takes the whole balance
- fix: `currency.php` failed without `currency_rate` (10 for 1000 now) and on a refused purchase
- fix: Gloebit offers the pack that covers the amount; the balance needs the secure session
- fix: `landtool.php` answers a negative amount or a declined transaction, instead of nothing
- fix: `offline.php/SaveMessage/` and `/RetrieveMessages/` were a 404 behind the router: no offline message
- fix: a simulator that cannot be reached is asked again later, more and more late, not at every run
- fix: a parcel without picture or group got the ones of the parcel before it in the search tables
- fix: the search by name gives the second page (`query_start` is a string), and sorts places by traffic
- fix: an answer of the search without result still has its (empty) list of data, which the module reads
- fix: without ext-xmlrpc, `query.php`, `currency.php` and `landtool.php` answered with two XML documents
- fix: through the router, the scripts did not see their global variables: `query.php` and `register.php` failed
- removed: `SEARCH_REGISTRARS`, dead code (the simulators register with their `DATA_SRV_*`)

### 3.0.0-beta.5

- update: `dev/` runs [build-tools](https://github.com/magicoli/build-tools) instead of its own copy of the scripts
- update: [bash-tools](https://github.com/magicoli/bash-tools) 1.0.7 in require-dev, the scripts use its functions

### 3.0.0-beta.4

- new: `dev/release.sh` makes the whole release, `dev/switch.sh` the composer part
- new: `dev/build.sh` makes the Debian package and the zip
- update: the scripts load `includes/bootstrap.php` (libraries, config, database, functions), `config.php` only defines constants, a missing one is a 503
- fix: the xmlrpc polyfill is loaded only without the extension, a server that has it got a 500
- new: the config of the grid, `/etc/opensim/grids/<grid>/helpers.ini`, is read when there is no `includes/config.php`
- update: `directory_info.php` and `classes/init.php` load the bootstrap
- update: a constant the config leaves out takes a default, a service database the main one
- fix: the helpers refuse to run without a database, and say so in the log
- new: `index.php` router, `OPENSIM_ROUTES` sets the URL of each service
- new: home and splash pages, Twig templates

### 3.0.0-beta.3

- new: `OPENSIM_MOTD` (`config.php`) is the message of the day `motd.php` gives
- fix(xmlrpc): xmlrpc_encode returns the XML like the extension
- fix(xmlrpc): serve methods with the signature of the extension
- chore(deps): lock the engine with the OAR class
- chore(composer): lock from packagist again
- chore(composer): lock on dev-opensim-kit of engine and rest-php
- chore: update formatting rules, reindent composer.json

### 3.0.0-beta.1

First beta of the 3.0 helpers, on the engine and the REST library.

- new: engine and REST client are composer packages, legacy composer tasks moved to `legacy`
- new: XML-RPC polyfill on `phpxmlrpc/phpxmlrpc`, Laravel-backed config polyfill for legacy helpers
- new: `get_grid_info` accepts an external grid (cached), persistent cache `osdb_cache_get/set()`
- new: `directory_info.php`, `textgen.php`; `register.php` stops when the search database is down
- new `opensim_sanitize_uri()`, and `includes/config.php` is included when it exists
- update: PHP 8.2 minimum, runs clean on 8.2 to 8.5, extensions declared
- update: parcels use only the Laravel logic, an exporter saves to the OpenSim database
- update: code formatted from `.editorconfig` and `.prettierrc.json`, `${var}` syntax replaced
- update tests: a pest suite checks the PHP minimum and compatibility
- fix: `opensim_format_tp()` hop links, `opensim_link_region()` crash on incomplete arguments
- fix: gatekeeper address has its scheme, warnings and crash in `parser.php`, `eventsparser.php`
- fix: error shown 3 times on an empty place search, expat error 3, duplicate `TPLINK*`, cron URLs

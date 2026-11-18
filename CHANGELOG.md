## Changelog

### Unreleased

- fix (profile): a viewer gets the stream of a profile alone wherever the block is shown (a site's page too, the `web_profile_url` of a grid), the engine telling a viewer (`Destination::client()`: the class a site gives, else the user agent); the rule is the block's, no longer the page route's
- feature (profile): the stream of the avatar (w4os-flux), its posts newest first in the Feed tab of the page, after the in-world one; a viewer gets it alone (its Feed tab opens the page of the profile, `web_profile_url`), and whoever asks `feed_only=true`; a JSON Feed (`?format=feed`) and an RSS feed (`?format=rss`) of it; needs opensim-engine with `OpenSim\Flux\Posts`
- update (guide, popular places): a card shows what you go to, then where, as the engine says it (`title` above, the largest: the name of the list here, the region on another grid; `label` under it: its region and point here, its grid elsewhere); a label breaks after a slash or a colon only
- update (guide, popular places): each card shows the label of its destination under its name, the engine's (its region and point for this grid, its grid for another), no longer the bare name of its region; the popular places are the engine's (`Grid::popularPlaces()`), no longer a query of the helpers; the login screen takes the same links as the other pages
- update (blocks): a site may give the class of its visitor (`client`: viewer or browser, the engine's `Destination::client()`) instead of its user agent, for the teleport links; the class wins, and a site keeps one copy of a block per class
- update (blocks): the engine tells a viewer for the teleport links (`Grid::link()`, Destination::AUTO reading the user agent): a block gives it the user agent of the page that shows it (`agent`, a site gives the one of its visitor at `/blocks/{name}`), no longer a viewer flag; `View::isViewer()` gone, the engine's `Destination::isViewer()` instead
- update (blocks): every teleport link (popular places, guide, search, grid info, picks and classifieds of a profile) is the one for where it is shown, as the engine writes it (`Destination::AUTO`, `Links::to()`): hop:// on a page; in a viewer and on its login screen, a local secondlife:// link for a region of the grid and the viewer's teleport for another grid; a page knows a viewer by its user agent, a block asked at `/blocks/{name}` by `viewer` (a site says it) or the user agent
- update (guide): the pictures of the cards are the engine's (`Grid::guide()`: the parcel of each destination, of this grid or of another, the first of a section); the helpers only show them, the lookup of `SearchData::picture()` is gone; the sections are the titles of the list as the engine reads them (titles that follow each other are one, a title without a destination is left out)
- update (blocks): the link of a place found or popular is written by the engine from its grid, its region and its landing point (`Destination::uri()`, `Destination::position()`: a height of zero lands at 21 m, a point as a script writes it read too), no longer assembled by hand
- fix (guide): one destination is singular ("1 destination"), in every language (its plural forms, `ngettext`)
- update (guide): the guide as the viewers' Destinations panel shows it: a card for each category (the picture of one of its places, its name, how many destinations), then for each destination (the picture of its parcel, its name), dark as the panel; one row scrolling sideways in a viewer, as many rows as needed on a web page
- update (blocks): the popular places are a grid of cards, the picture of each parcel with its name and its region, as many rows as needed (`assets/css/places.css`)
- new (blocks): the picture of the parcel a destination leads to, as the search has it (`SearchData::picture()`: the parcel of that name in the region, else the one of that landing point, else the only parcel of the region; never another parcel), the grid's placeholder without one; `View::blockFiles()` gives a site the styles and scripts of a block it shows in its pages
- fix (guide): the destinations of the guide are linked as the viewers open them and as the 2.x guide linked them (`secondlife://host:port Region/x/y/z`, `Destination::HG`), no longer the line of the list as it is; a region named alone is one of the grid; tested against what the 2.x guide showed on the same lists (`tests/fixtures/guide`)
- update (blocks): the dates the blocks show are in the language of the page (`I18n::date()`, PHP's Intl formatter), no longer ISO: a recent user's arrival as a short date with its time, today and yesterday by their name; the birth of a profile and the end of a classified as a medium date; an event and the time of the health check as a medium date with its time (UTC)
- fix (security): the engine answers the services of the helpers locked (`Locked`): only what it says is public (`Router::PUBLIC`: what the viewers and the simulators call, the pictures), whatever key a request brings; the address of the offline messages takes their two methods only (SaveMessage, RetrieveMessages)
- change (api): the helpers no longer serve the API of the engine: `/api/...` is a 404 here, the engine serves it from its own entry point (`public/index.php` of opensim-engine, `opensim web GRID api`); the pictures of the grid a page shows are at `/pictures/<uuid>.<format>` (were `/api/grids/<grid>/assets/...`); the due tasks of the grid still run after the pages, as before (`Engine::serving()`)
- update (blocks): the links of a block a site shows (the form and the topics of the search, the profiles of the people found, of the recent users, of the partner of a profile) are at the addresses the site gives (`search_url`, `web_profile_url` with `[AGENT_NAME]`), in its language (`/fr/search`, `/fr/profile/…`); without them, the grid's
- update (i18n): "Wants to" is "Souhaite" in French (was "J'aimerais")
- refactor (blocks): whether a profile is published is the engine's (`Avatar::isPublished()`), not a bit read by hand; the hop:// link of a place found is written from its URI as the engine writes one (`host:port/Region/x/y/z`, `Destination::HOP`)
- update (blocks): the links to a profile (the recent users, the search of people, the partner of a profile) are the address the grid gives (`web_profile_url`, `[AGENT_NAME]` as First.Last, its host and what follows kept), made by the engine (`Avatar::profileAt()`); none when the grid gives none
- update (profile): a profile without its picture, in-world or of the real life, shows the silhouette of a person of the engine (`WebAssets::PERSON`, `?default=person` for a picture that is missing), not the landscape of nothing
- update (profile): the tabs of a profile are named for any grid, "In-world" and "Real life" (the engine's `profile_tab_inworld` and `profile_tab_real_life`), not after Second Life; the sections are `inworld` and `real-life` (`#profile-inworld`, `#profile-real-life`)
- update (packaging): the helpers depend on the engine that has everything they call (`opensim-engine (>= 3.0.0~beta.13.459)`)
- test (blocks): the recent users leave out an avatar waiting for the confirmation of its email, as well as a disabled one
- update (profile): the tabs of a profile are named by the catalog of the engine (`profile_tab_*`, `profile_sections`), as on every site, in the language of the page; the helpers keep no copy of them
- test (packaging): the check of the package is the one of the Slim application: the real .deb on a clean Debian with php-fpm and Caddy (the snippet of the kit) and MariaDB, the folders of the web server, the tasks of the search after a visit, the health page, a picture cached, an upgrade, the zip
- update (profile): a profile that is not published says "This profile is not public.", in every language
- fix (install): the helpers write their tasks and their cache where the web server can, `/var/cache/opensim-helpers/{tasks,cache}`, made by the package for `www-data` (HelpersLayout), when the folders of the install are not writable and nothing else is given; they take `OPENSIM_TASKS_ROOT`, `OPENSIM_CACHE_ROOT` and the other `OPENSIM_*` the web server gives; the health page tells both folders
- fix (search): the search index fills by itself: the API of the engine has the search tasks of the grid, and once the answer is sent (`fastcgi_finish_request`) a simulator that registered is read and the tasks that are due run, on every visit (`Engine::finish()`, the pseudo cron of the engine); no cron needed
- fix (search): the search of people finds only the avatars who published their profile ("show in search" in the viewer), as the web profile already did
- feature (blocks): `new_users`, the people who joined last (5, `count` up to 50), as the recent users of w4os: the newest active avatars of the engine, never a model, the account of the services or an account without an email; the name links to the profile, the email is not shown
- refactor: Slim 4 application (public/index.php, src/); the former scripts are gone
- update: `gettext` is recommended, not required: without it the messages stay in English
- update: the logos of w4os and OpenSimulator come from opensim-engine: composer install and update refresh them in assets/images/logos (composer logos), the four sizes the engine added included

### 3.0.0-beta.6

Freeze current state before refactor

- fix(search): a registering simulator is read after 30 s, when its regions are loaded: the snapshot it makes while starting is the one it keeps
- fix(profile): only the show in search flag of the account is written, the other flags are not the user's choice
- docs(changelog): show in search is the flags of the user account
- fix(profile): show in search is the first flag of the user account, which is what OpenSimulator shows and keeps, not a column of the profile
- fix(search): a simulator that could not be read keeps its backoff, it is not read again in 90 s
- fix(search): a simulator that registers is read again 90 s later, its regions are not all loaded when it registers
- fix(search): avatar_publish_update needs the secure session of the avatar, not the address of the caller
- fix(search): avatar_publish_update answers as the other calls do, a struct with success and errorMessage
- feat(search): avatar_publish_update, the show in search box of a profile that Robust ignores, written for the simulators of the grid only
- fix(search): a registering simulator is let go at once, not held while its snapshot is read (it was read empty); the search reads the hosts that are due once a minute
- docs(changelog): the regions table of the search, the search requests without flags
- fix(search): the table of the regions of the search is never the regions table of Robust, whether Robust has made it or not yet
- fix(search): a search request without flags or without the day|category pieces is answered, not a 500
- feat(pages): the web search, the page of an avatar, the popular places; fix: the search registry is read in process, and by the search every ten minutes
- docs(changelog): the script an address names answers it
- fix(routes): the script an address names answers it, a registration that points to query.php no longer takes the place of the search
- feat(health): an address is shown as the config says it and linked as it was asked; docs: the web search settings as tested with Firestorm
- feat(config): search_url, search of the grid info first, SearchURL of the login service when the grid has only that
- feat(pages): public pages grid_info and grid_status (ONLINE, OFFLINE) under the helpers, GridInfoURL and GridStatusURL in [Helpers]; fix(health): the profile with an avatar name is in the config section
- refactor(config): the ini files are read by the engine's OpenSim_Config; feat(health): what the grid says first, compared with the config, each service called without data and with valid data
- feat(health): one aligned table by config file, cert, time and result on the right, franker colors
- feat(health): the health page asks the engine's checks; wrong calls get their code (418, 405, 400, 404, 503), never a 500 on purpose
- refactor(functions): the helpers use the engine's functions (destinations, teleport links, regions, XML answers, currency, XML-RPC polyfill) and keep only what is theirs
- fix(health): the avatar the profile is asked for is the first account that is not the services of the grid
- fix(currency)!: no default key, without MoneyScriptAccessKey the currency is not served (bad configuration); fix(health): 404 and any error in red, orange for an untrusted certificate, the key row, the profile without a name expects a bad request
- fix(health): curl ignores the certificate and the page reports it, the profile is asked with the name of the owner and without; /helpers is a public page and health keeps its own address; the script key is only the MoneyServer's
- fix(config): the ini files are read with their includes and constants, MoneyScriptAccessKey only in [MoneyServer] of MoneyServer.ini; fix(health): sections and settings as in the ini files, links, final HTTP code or error code, every address of [GridInfoService]; the address of the helpers answers
- feat(pages): the menu lists the pages the site serves, health also under the helpers path; fix(currency): each grid has its own script key (MoneyServer's, Helpers.ini's, or made of its database password)
- docs(economy): what currency.php and landtool.php do with a module, and who sends to the page of Gloebit
- fix(economy): currency.php and landtool.php serve any module but the beta one, Gloebit adds through its region and sends to its page when it cannot; default rate 4 for 1000, UseUTCTime back
- feat(economy): the currency is what the economy module says (Gloebit, BetaGridLike), the settings of Gloebit are read, a currency sold elsewhere is never added; web_profile_url routes the profiles
- fix(routes): an address of the grid is served when its host (and its port, if it gives one) are the ones of the page served
- fix(guide): the block is destination_guide, as the setting is called: the home page shows it
- fix(config): a key given twice in a section is read as OpenSimulator reads it, the first one
- feat(config)!: the helpers read the official settings of OpenSim.ini and Robust.ini, Helpers.ini holds what has no place there; routes from the addresses of the grid on this site; each query on the database of its service
- fix(guide): the title of a category is its name
- feat(pages): templates in layouts, pages and components with suffixes, a theme folder that replaces them and the assets, the guide and directory_info as blocks of the layout (3-6-3), styles in the head
- feat(guide): an empty guide tells the one who runs the grid why, in the log and in the page source; docs(layout): the templates, the blocks, the assets
- feat(health): the addresses of the grid itself are asked at /get_grid_info
- feat(pages): one layout with a left and a right sidebar, blocks as variables of the templates, a health page, the routes of the profiles; assets in assets/
- feat(pages): blocks (grid info, grid status) and a layout that adapts to viewers and browsers; static files in static/, page scripts in pages/
- feat(guide): a guide_source that is a file name is looked for in the folder of the grid
- fix(search): the cache gives back what was stored, an empty list included
- test(helpers): the destinations, the teleport links, UUIDs, the price of the currency and the guide, with no OpenSim at all
- fix(events): opensim_latin1_only, which eventsparser calls, exists
- fix(scripts): guide through the router and from helpers.ini, textgen size and download, directory_info without grids
- docs(changelog): the currency fixes, shorter
- fix(currency): the balances table is locked with a valid query
- fix(currency): the region of a transaction is looked up in the database of the grid
- fix(currency): the transactions of a money server get an id, the purchase for a land debits nothing
- fix(currency): a default rate, the Gloebit pack, the balance for who has the session, the answers of landtool
- docs(changelog): the offline messages behind the router
- fix(router): a script called with what OpenSimulator adds to it (offline.php/SaveMessage/) is served, and gets it as PATH_INFO
- docs(changelog): the delay of the unreachable simulators
- fix(search): a simulator that cannot be reached is asked again later, as the code says, not at every run
- docs(changelog): the fixes of the search
- fix(search): a parcel without picture or group does not get the ones of the parcel before it
- fix(search): the second page and the sort by traffic: the page is a number for the SQL, the order is not a parameter
- fix(search): an answer without a result still has its list of data, which the module reads
- fix(xmlrpc): the polyfill leaves the printing to the methods, as the extension does: one answer, not two
- fix(router): a script runs in the global scope, as with a web server, the entry includes it
- refactor(search): remove SEARCH_REGISTRARS, dead code: a simulator registers to several search services with its DATA_SRV_*

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

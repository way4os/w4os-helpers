## Changelog

### Unreleased

- change (search): a place's link from Grid::placeAt(), no address assembled in the helpers
- change (blocks): every teleport link asked of its object (tpLink(): the grid, Grid::place()); Links::to() takes one
- fix (tests): the unreachable guide is one whose list cannot be read; the notices tests start with none
- fix (blocks): the popular places and the guide show nothing, their title neither, when they have nothing to show
- fix (texts): translated without the gettext extension or the system's locales (gettext/gettext, gettext/translator)
- fix (assets): the grid's pictures are served at the address of its web assets too (helpers/assets), not only /pictures
- fix (guide): the guide block keeps its own title in a browser, its category under it; no work-in-progress line
- update (git): .gitignore is the projects' template (macOS, editors, AI files, config, generated content, data) with this project's own rules after it; phpunit.xml is phpunit.xml.dist (a local phpunit.xml overrides it), .phpactor.json no longer tracked
- fix (packaging): the engine and rest-php are links in the package whatever the machine that builds it has; the web configs that name /usr/share/opensim-helpers moved to /usr/share/w4os-helpers at the upgrade; the packaging check reads the links and upgrades such a config
- fix (tests): tests/Packaging/check takes where its container runs on its command line (--connection NAME, or --local), never from the environment, and refuses to run on macOS without it; a named connection must exist, and the machine used is the one podman itself reports; the upgrade installs the empty package first, as apt does
- update (rename): the helpers are w4os-helpers: the namespace W4OS\Helpers on the engine's W4OS\Engine, the package w4os-helpers in /usr/share/w4os-helpers (opensim-helpers an empty package that brings it), their folder /var/cache/w4os-helpers (the one of before moved there), the repository way4os/w4os-helpers, the composer name w4os/w4os-helpers
- new (packaging): the copyright file of the package
- change (guide): a grid that sets no GuideSource shows the guide of 2do.directory (the engine's default)
- fix (blocks): the health in the language of the page, the sentences of the catalog of the engine
- fix (blocks): the message of the day in the language of the page, one sentence of the catalog of the engine
- fix (blocks): the grid status shows the regions online only, its rows in the words and the order of w4os-wp
- fix (texts): the texts of the profiles and the errors, missing from the catalogs, translated
- feature (blocks): each block says which it is on its root (`data-helpers-block`), for the health to find it on a page
- fix (routes): an address of the grid on another server is not routed to the helpers, whatever its path
- fix (routes): what the helpers do not provide here is refused with its reason, in XML-RPC for the viewers, never an empty 404
- change (blocks): the popular places leave out the other grids and the land for sale by default, as w4os-wp 2.x
- feature (blocks): popular places take the `include_hypergrid` and `include_landsales` options
- feature (sites): the helpers serve the grid a site gives them (`App::create`)
- change (routes): the helpers answer only the pages the grid sets under their base, anything else is a 404
- update (health): the page shows folder and file paths; grid tasks include the health check
- update (profile): the sections follow the engine's form; no Web tab when web profile URLs are not allowed
- fix (grid status): the block shows the status text in the page language, not the capitalized keyword
- fix (places): popular place cards show at least two per row on narrow screens
- update (blocks): the no-picture placeholder is half transparent in the guide and popular places
- update (guide): test hypergrid destinations are Watta, Speculoos, Craft World, LFGrid, Alternate Metaverse, OSGrid, Kitely
- update (guide): in a browser the guide is a themed block like the popular places; viewers keep the dark look
- update (profile): the stream is a timeline with relative dates; profile styles in `css/profile.css`
- update (profile): opens on its first non-empty tab; role alone in its banner; details label left, value right
- update (blocks): profile, search and guide leave their title to the page when `title` is empty
- update (profile): a banner over every tab; stream first, then About; `feed_only` shows the stream alone
- fix (profile): a viewer gets the stream alone wherever the block is shown, as the engine tells
- feature (profile): the avatar stream (w4os-flux) in a Feed tab, with JSON Feed and RSS (`?format=feed`, `?format=rss`)
- update (guide, popular places): cards show a title and a label as the engine gives them
- update (guide, popular places): cards show the engine's destination label; places come from `Grid::popularPlaces()`
- update (blocks): a site may give the visitor class (`client`) instead of its user agent
- update (blocks): the engine detects viewers for teleport links; blocks pass the user agent (`agent`)
- update (blocks): every teleport link is written by the engine for where it is shown (`Destination::AUTO`)
- update (guide): card pictures come from the engine (`Grid::guide()`); sections follow the list titles
- update (blocks): place links are written by the engine (`Destination::uri()`)
- fix (guide): "1 destination" is singular in every language
- update (guide): laid out like the viewers' Destinations panel, with category and destination cards
- update (blocks): popular places are a grid of cards showing the parcel picture
- new (blocks): parcel picture of a destination (`SearchData::picture()`); `View::blockFiles()` for sites
- fix (guide): destinations are linked as viewers and the 2.x guide open them (`Destination::HG`)
- update (blocks): dates show in the page language (`I18n::date()`)
- fix (security): the engine answers helper services locked; only public routes open
- change (api): the helpers no longer serve the engine API; pictures are at `/pictures/<uuid>.<format>`
- update (blocks): block links use the site's `search_url` and `web_profile_url`
- update (i18n): "Wants to" is "Souhaite" in French
- refactor (blocks): profile publication and hop:// links come from the engine (`Avatar::isPublished()`)
- update (blocks): profile links use `web_profile_url`, built by `Avatar::profileAt()`
- update (profile): a profile without a picture shows the engine's person silhouette
- update (profile): tabs are named "In-world" and "Real life" for any grid
- update (packaging): depend on `opensim-engine (>= 3.0.0~beta.13.459)`
- test (blocks): recent users leave out avatars awaiting email confirmation
- update (profile): tab names come from the engine catalog (`profile_tab_*`)
- test (packaging): the package check installs the real .deb on a clean Debian
- update (profile): an unpublished profile says "This profile is not public." in every language
- fix (install): tasks and cache default to `/var/cache/opensim-helpers/{tasks,cache}`; `OPENSIM_*` variables are read
- fix (search): the search index fills by itself on visits (`Engine::finish()`)
- fix (search): the people search finds only avatars with a published profile
- feature (blocks): `new_users` lists the newest avatars
- refactor: Slim 4 application (`public/index.php`, `src/`)
- update: `gettext` is recommended, not required
- update: logos come from opensim-engine (`composer logos`)

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

# Page caching: what w4os got wrong

This repository is the source of the first cause (master and 3.x; the opensim-kit branch has no `session_start()` any more). Found in the server performance audit of 2026-11-14 on speculoos.world (WordPress, w4os 3.0.1-dev with the bundled opensim-helpers, WPML, Salient). The home page takes about 0.8 s and no page cache can keep it.

## Causes

- **A PHP session on every request.** opensim-helpers `classes/class-helpers.php` calls `session_start()` when the file is loaded (master and 3.x branches; also `engine/class-form.php` and `install-wizard.php`). w4os bundles it as `helpers/`, and `W4OS3_Userless_Auth::start_session()` does the same on `init`. PHP then sends `Set-Cookie: PHPSESSID`, `Cache-Control: no-store, no-cache, must-revalidate` and `Expires: Thu, 19 Nov 1981` on every page, anonymous visitors included. WP Super Cache, W3 Total Cache, a CDN and the browser all refuse to keep these pages.
- **Live grid data read while rendering.** Grid status and similar figures are queried from the ROBUST database during the page build and kept only in the per-request object cache (`wp_cache_*` with no persistent object cache), so every page asks the grid again.

## Rules for the engine and its adapters

1. Never start a PHP session at load time or on `init`. Start it only in the request that needs it (a form step being submitted, a login), or use signed tokens, user meta or transients instead of `$_SESSION`.
2. A page for an anonymous visitor sends no cookie and no no-cache header, so the CMS page cache (WordPress, Grav) can keep it.
3. Live figures (online users, regions, grid status) are fetched in the background (cron or scheduled action) and stored with an expiry; rendering reads the stored value. Or the page loads them client-side from a small REST endpoint, and the page itself stays cacheable.
4. Visitor- or time-dependent fragments are loaded client-side, never baked into cached HTML.
5. Check: `curl -sI https://<site>/` as an anonymous visitor shows no `Set-Cookie` and no `no-store`.

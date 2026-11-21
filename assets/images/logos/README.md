The logo of the grid, shown in the top bar of its pages: `logo.svg` or `logo.png` here is the one used. Another one is set with `LogoURL` in the `[Helpers]` section of `Helpers.ini` (`OPENSIM_GRID_LOGO_URL` in `includes/config.php`).

The logos of w4os and OpenSimulator in this folder come from w4os-engine (`share/images/logos`): `composer install` and `composer update` refresh them (`composer logos`), and what changes is committed like any other file. An install without the development tools (`--no-dev`, the packages) leaves them as they are.

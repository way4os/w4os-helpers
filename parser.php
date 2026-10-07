<?php
/**
 * parser.php
 *
 * Parse data from registered hosts to feed the search database. It is done when a simulator registers and, for the data that
 * change after that, by the search itself when a host is due, looking once a minute (see includes/search-parser.php): a cron job
 * that calls this script is not needed, and may make it more often.
 *
 * Requires OpenSimulator Search module
 *   [OpenSimSearch](https://github.com/kcozens/OpenSimSearch)
 * Events are fetched from 2do HYPEvents or any other HYPEvents implementation
 *   [2do HYPEvents](https://2do.directory)
 *
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link            https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 *
 * Includes portions of code from
 *   [OpenSimSearch](https://github.com/kcozens/OpenSimSearch)
 **/

require_once 'includes/bootstrap.php';
require_once 'includes/search.php';
require_once 'includes/search-parser.php';
dontWait();

ossearch_parse();

die();

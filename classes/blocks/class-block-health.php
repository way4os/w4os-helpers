<?php
/**
 * Block health: every address the grid gives out (what the viewers read in its grid info, what Robust tells them at login,
 * the services of the helpers) with whether it answers, and what the helpers need to work. An address that is not defined
 * is shown empty, so nothing is forgotten. Kept half a minute: each look asks the grid.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
require_once dirname(__DIR__) . '/class-grid-info.php';

class OpenSim_Helpers_Block_Health
{
    /** How long a look is kept, in seconds. */
    const TTL = 30;

    /** How long an address has to answer, in seconds. */
    const TIMEOUT = 3;

    /** The addresses of the grid info that viewers read, in the order the page shows them. */
    const GRID_INFO = ['login', 'gatekeeper', 'uas', 'welcome', 'economy', 'about', 'register', 'help', 'password', 'search'];

    /** The addresses Robust gives at login ([LoginService]). */
    const LOGIN_SERVICE = ['DestinationGuide' => 'Destination guide', 'MessageUrl' => 'Message of the day', 'SearchURL' => 'Search'];

    /**
     * @param  array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function data($args = [])
    {
        $key = 'health-' . md5((defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : '') . (defined('OPENSIM_GRID_NAME') ? OPENSIM_GRID_NAME : ''));
        $groups = OpenSim_Helpers_Cache::remember($key, self::TTL, fn() => self::look());

        return ['title' => _('Health'), 'groups' => $groups, 'when' => date('c')];
    }

    /**
     * What there is to see now: the addresses and the state of the install, as groups of rows. The addresses are the ones the
     * official settings give (grid info and login service of Robust, search, registration and offline messages of the
     * simulators), each followed by what it answers; one the grid does not give is shown empty, so nothing is forgotten.
     *
     * @return list<array{title:string,rows:list<array<string,mixed>>}>
     */
    public static function look()
    {
        $settings = class_exists('OpenSim_Helpers_GridConfig') ? OpenSim_Helpers_GridConfig::settings() : null;
        $sources = $settings['sources'] ?? [];
        $get = fn($section, $key) => (string) OpenSim_Helpers_GridConfig::value($sources, $section, $key);
        $login = defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : ($settings['login_uri'] ?? null);
        $web = rtrim((string) ($settings['web_url'] ?? ''), '/');
        $live = $login ? OpenSim_Helpers_GridInfo::fetch($login) : [];
        $routes = defined('OPENSIM_ROUTES') ? OPENSIM_ROUTES : [];
        $default = function ($script) use ($routes, $web) {
            $path = array_search($script, $routes, true);

            return $path === false || $web === '' ? '' : $web . $path;
        };

        $groups = [];
        // What the viewers read at the login address (/get_grid_info), else what the config of Robust says
        $rows = [];
        foreach (self::GRID_INFO as $name) {
            $value = $name === 'login' ? (string) $login : (string) ($live[$name] ?? $get('GridInfoService', $name));
            $row = ['label' => $name, 'url' => $value, 'method' => 'get'];
            // The addresses of the grid itself answer at /get_grid_info, what the viewers ask first
            if (in_array($name, ['login', 'gatekeeper', 'uas'], true) && $value !== '') {
                $row['probe'] = rtrim($value, '/') . '/get_grid_info';
            }
            $rows[] = $row;
        }
        $groups[] = ['title' => _('Grid info (what the viewers read)'), 'rows' => $rows];

        $rows = [];
        foreach (self::LOGIN_SERVICE as $name => $label) {
            $rows[] = ['label' => $label . " ($name)", 'url' => $get('LoginService', $name), 'method' => 'get'];
        }
        $groups[] = ['title' => _('Login service (what Robust tells at login)'), 'rows' => $rows];

        // What the simulators call (OpenSim.ini): the search, the registration on a directory, the offline messages
        $rows = [['label' => 'SearchURL', 'url' => $get('Search', 'SearchURL') ?: $default('query.php'), 'method' => 'xmlrpc']];
        $registers = OpenSim_Helpers_GridConfig::values($sources, 'DataSnapshot', '/^DATA_SRV_/');
        foreach ($registers ?: [$default('register.php')] as $url) {
            $rows[] = ['label' => 'DATA_SRV', 'url' => (string) $url, 'method' => 'get'];
        }
        $rows[] = ['label' => 'OfflineMessageURL', 'url' => $get('Messaging', 'OfflineMessageURL') ?: $default('offline.php'), 'method' => 'get'];
        $groups[] = ['title' => _('Simulators (what they call)'), 'rows' => $rows];

        // The economy: the viewers add the name of the script to the address the grid gives
        $rows = [];
        $economy = (string) ($live['economy'] ?? $get('GridInfoService', 'economy'));
        foreach (['currency.php', 'landtool.php'] as $script) {
            $rows[] = ['label' => $script, 'url' => $economy === '' ? $default($script) : rtrim($economy, '/') . "/$script", 'method' => 'xmlrpc'];
        }
        $rows[] = ['label' => 'textgen.php', 'url' => $default('textgen.php'), 'method' => 'get'];
        $groups[] = ['title' => _('Helpers'), 'rows' => $rows];

        $groups = self::answers($groups);
        $groups[] = ['title' => _('System'), 'rows' => self::system($settings)];

        return $groups;
    }

    /**
     * Ask every address, all at once.
     *
     * @param  list<array{title:string,rows:list<array<string,mixed>>}> $groups
     * @return list<array{title:string,rows:list<array<string,mixed>>}>
     */
    public static function answers($groups)
    {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($groups as $g => $group) {
            foreach ($group['rows'] as $r => $row) {
                if ($row['url'] === '') {
                    continue;
                }
                $handle = curl_init($row['probe'] ?? $row['url']);
                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => self::TIMEOUT,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_USERAGENT => 'OpenSimHelpers-health',
                    // Only the answer matters, not what follows it
                    CURLOPT_WRITEFUNCTION => static fn($handle, $chunk) => 0,
                ]);
                if ($row['method'] === 'xmlrpc') {
                    curl_setopt_array($handle, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>',
                        CURLOPT_HTTPHEADER => ['Content-Type: text/xml'],
                    ]);
                }
                curl_multi_add_handle($multi, $handle);
                $handles[] = [$g, $r, $handle];
            }
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.2);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as [$g, $r, $handle]) {
            $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $groups[$g]['rows'][$r] += self::verdict($code, curl_error($handle), (int) round(curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000));
            curl_multi_remove_handle($multi, $handle);
        }
        curl_multi_close($multi);
        foreach ($groups as $g => $group) {
            foreach ($group['rows'] as $r => $row) {
                $groups[$g]['rows'][$r] += self::verdict(null, '', 0);
                unset($groups[$g]['rows'][$r]['method'], $groups[$g]['rows'][$r]['probe']);
            }
        }

        return $groups;
    }

    /**
     * What an answer means: 2xx and 3xx are fine, a 4xx is an address that answers (an API says so to a request that is not
     * its own), 5xx and no answer are not.
     *
     * @param  int|null $code the HTTP code, null for an address that is not defined
     * @return array{level:string,status:string,ms:?int}
     */
    public static function verdict($code, $error, $ms)
    {
        if ($code === null) {
            return ['level' => 'none', 'status' => _('not defined'), 'ms' => null];
        }
        if ($code === 0) {
            return ['level' => 'error', 'status' => _('no answer') . ($error !== '' && $error !== 'Operation timed out' ? ": $error" : ''), 'ms' => null];
        }
        $level = $code < 400 ? 'ok' : ($code < 500 ? 'warn' : 'error');

        return ['level' => $level, 'status' => (string) $code, 'ms' => $ms];
    }

    /**
     * What the install needs, one row per thing.
     *
     * @return list<array<string,mixed>>
     */
    public static function system($settings = null)
    {
        global $OpenSimDB;
        $ok = fn($label, $good, $detail = '') => [
            'label' => $label,
            'url' => '',
            'level' => $good ? 'ok' : 'error',
            'status' => $good ? _('ok') : _('missing'),
            'detail' => $detail,
            'ms' => null,
        ];
        $guide = defined('OPENSIM_GUIDE_SOURCE') ? OPENSIM_GUIDE_SOURCE : '';
        $rows = [
            $ok(_('Database of Robust'), !empty($OpenSimDB) && !empty($OpenSimDB->connected)),
            $ok(_('XML-RPC (extension or polyfill)'), function_exists('xmlrpc_encode')),
            $ok(_('Imagick (text to image, web assets)'), class_exists('Imagick')),
            $ok(_('Gettext (translations)'), function_exists('gettext')),
        ];
        // The ini files the helpers read, and the ones the web server user cannot
        foreach ($settings ? OpenSim_Helpers_GridConfig::files($settings['dir']) : [] as $file) {
            if (is_file($file)) {
                $rows[] = $ok(basename($file), is_readable($file), $file);
            }
        }
        $rows[] =
            $guide === ''
                ? ['label' => _('Destination guide source'), 'url' => '', 'level' => 'none', 'status' => _('not defined'), 'detail' => 'GuideSource', 'ms' => null]
                : $ok(_('Destination guide source'), preg_match('#^https?://#', $guide) || is_readable($guide), $guide);

        return $rows;
    }
}

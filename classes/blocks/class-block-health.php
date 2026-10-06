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

    /** The services of the helpers, by what they are asked: the script and how an answer is provoked. */
    const SERVICES = [
        'query.php' => ['Search (query)', 'xmlrpc'],
        'register.php' => ['Registration of the simulators', 'get'],
        'offline.php' => ['Offline messages', 'get'],
        'motd.php' => ['Message of the day', 'get'],
        'guide.php' => ['Destination guide', 'get'],
        'textgen.php' => ['Text to image', 'get'],
    ];

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
     * What there is to see now: the addresses and the state of the install, as groups of rows.
     *
     * @return list<array{title:string,rows:list<array<string,mixed>>}>
     */
    public static function look()
    {
        $settings = class_exists('OpenSim_Helpers_GridConfig') ? OpenSim_Helpers_GridConfig::settings() : null;
        $login = defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : ($settings['login_uri'] ?? null);
        $robust = $settings['robust'] ?? [];
        $web = rtrim((string) ($settings['web_url'] ?? ''), '/');
        $live = $login ? OpenSim_Helpers_GridInfo::fetch($login) : [];

        $groups = [];
        // What the viewers read at the login address (/get_grid_info), else what the config of Robust says
        $rows = [];
        foreach (self::GRID_INFO as $name) {
            $value = $name === 'login' ? $login : $live[$name] ?? ($robust['GridInfoService'][$name] ?? '');
            $rows[] = ['label' => $name, 'url' => (string) $value, 'method' => 'get'];
        }
        $groups[] = ['title' => _('Grid info (what the viewers read)'), 'rows' => $rows];

        $rows = [];
        foreach (self::LOGIN_SERVICE as $name => $label) {
            $rows[] = ['label' => $label . " ($name)", 'url' => (string) ($robust['LoginService'][$name] ?? ''), 'method' => 'get'];
        }
        $groups[] = ['title' => _('Login service (what Robust tells at login)'), 'rows' => $rows];

        // The economy: the viewers add the name of the script to the address the grid gives
        $rows = [];
        $economy = (string) ($live['economy'] ?? ($robust['GridInfoService']['economy'] ?? ''));
        foreach (['currency.php', 'landtool.php'] as $script) {
            $rows[] = ['label' => $script, 'url' => $economy === '' ? '' : rtrim($economy, '/') . "/$script", 'method' => 'xmlrpc'];
        }
        foreach (self::SERVICES as $script => [$label, $method]) {
            $path = $settings ? OpenSim_Helpers_GridConfig::script_path($settings, $script) : null;
            $rows[] = ['label' => $label, 'url' => $path && $web !== '' ? $web . $path : '', 'method' => $method];
        }
        $groups[] = ['title' => _('Helpers'), 'rows' => $rows];

        $groups = self::answers($groups);
        $groups[] = ['title' => _('System'), 'rows' => self::system()];

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
                $handle = curl_init($row['url']);
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
                unset($groups[$g]['rows'][$r]['method']);
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
    public static function system()
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
        $rows[] =
            $guide === ''
                ? ['label' => _('Destination guide source'), 'url' => '', 'level' => 'none', 'status' => _('not defined'), 'detail' => 'guide_source', 'ms' => null]
                : $ok(_('Destination guide source'), preg_match('#^https?://#', $guide) || is_readable($guide), $guide);

        return $rows;
    }
}

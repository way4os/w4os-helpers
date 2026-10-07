<?php
/**
 * Block health: every address the grid gives out, the ones its settings hold and the ones the helpers expect, with what each
 * answers, and what the helpers need to work. It is a page to check things: the sections and the settings are named as in the
 * ini files, so that nobody wonders where to look for one, an address is a link, and what an address answers is the HTTP code
 * where the redirections lead, or the error code when nothing answers. An address that is not defined is shown empty, so
 * nothing is forgotten. Kept half a minute: each look asks the grid.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
require_once dirname(__DIR__) . '/class-grid-info.php';
require_once dirname(__DIR__) . '/class-grid-config.php';

class OpenSim_Helpers_Block_Health
{
    /** How long a look is kept, in seconds. */
    const TTL = 30;

    /** How long an address has to answer, in seconds. */
    const TIMEOUT = 3;

    /** The settings of [GridInfoService] that are addresses, shown first and in this order even when the grid gives none. */
    const GRID_INFO = ['login', 'gatekeeper', 'uas', 'welcome', 'economy', 'about', 'register', 'help', 'password', 'search'];

    /** The addresses of the grid itself, which answer at /get_grid_info. */
    const GRID_ADDRESSES = ['login', 'gatekeeper', 'uas'];

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

    /** Whether a value is an address. */
    private static function is_url($value)
    {
        return (bool) preg_match('#^https?://#i', (string) $value);
    }

    /**
     * What there is to see now: the addresses and the state of the install, with what each address answers.
     *
     * @return list<array{title:string,rows:list<array<string,mixed>>}>
     */
    public static function look()
    {
        $settings = OpenSim_Helpers_GridConfig::settings();
        $login = defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : ($settings['login_uri'] ?? null);
        $live = $login ? OpenSim_Helpers_GridInfo::fetch($login) : [];
        $routes = defined('OPENSIM_ROUTES') ? OPENSIM_ROUTES : [];
        $groups = self::answers(self::plan($settings, $login, $live, $routes, defined('OPENSIM_GUIDE_SOURCE') ? (string) OPENSIM_GUIDE_SOURCE : '', self::owner()));
        $groups[] = ['title' => '[DatabaseService]', 'rows' => [self::database_row()]];
        $groups[] = ['title' => _('PHP extensions'), 'rows' => self::extensions()];
        $files = self::files($settings);
        if ($files) {
            $groups[] = ['title' => _('Files read'), 'rows' => $files];
        }

        return $groups;
    }

    /**
     * The addresses to ask, as groups of rows, a group being a section of the ini files (its title is its name) and a row a
     * setting of it (its label is its key): the addresses the settings give, and the ones the helpers expect that the grid does
     * not give, at their default address. A row has `url`, `method` (get or xmlrpc) and, when it needs, `probe` (where to ask
     * instead), `skip` (why it is not asked) and `note`.
     *
     * @param  array|null           $settings what OpenSim_Helpers_GridConfig::settings() gave, null without a config
     * @param  string|null          $login    the login address
     * @param  array<string,mixed>  $live     what the grid says at /get_grid_info
     * @param  array<string,string> $routes   the paths the helpers answer
     * @param  string               $guide    the source of the destination guide
     * @param  string|null          $owner    the name of an avatar of the grid (there is always one, the owner), Firstname.Lastname
     * @return list<array{title:string,rows:list<array<string,mixed>>}>
     */
    public static function plan($settings, $login, $live, $routes, $guide, $owner = null)
    {
        $sources = $settings['sources'] ?? [];
        $get = fn($section, $key) => (string) OpenSim_Helpers_GridConfig::value($sources, $section, $key);
        $web = rtrim((string) ($settings['web_url'] ?? ''), '/');
        // Where the helpers serve a script when the grid says nothing: an address the grid does not give is checked there
        $default = function ($script) use ($routes, $web) {
            $path = array_search($script, $routes, true);

            return $path === false || $web === '' ? '' : $web . $path;
        };
        // A setting of the grid, or where the helpers expect it when it is not given
        $setting = function ($section, $key, $script, $method = 'get') use ($get, $default) {
            $value = $get($section, $key);
            $row = ['label' => $key, 'url' => $value !== '' ? $value : $default($script), 'method' => $method];
            if ($value === '' && $row['url'] !== '') {
                $row['note'] = _('default address of the helpers');
            }

            return $row;
        };

        $groups = [];

        // What the viewers read at the login address (/get_grid_info), else what the config of Robust says: every address in it
        $info = [];
        foreach ($sources as $ini) {
            foreach ($ini['GridInfoService'] ?? [] as $key => $value) {
                if (self::is_url($value)) {
                    $info[$key] ??= $value;
                }
            }
        }
        foreach ($live as $key => $value) {
            if (is_string($value) && self::is_url($value)) {
                $info[$key] = $value;
            }
        }
        if ($login) {
            $info['login'] = (string) $login;
        }
        $rows = [];
        foreach (array_values(array_unique(array_merge(self::GRID_INFO, array_keys($info)))) as $key) {
            $value = (string) ($info[$key] ?? '');
            $row = ['label' => $key, 'url' => $value, 'method' => 'get'];
            if (in_array($key, self::GRID_ADDRESSES, true) && $value !== '') {
                // What answers at these addresses is the grid info, the first thing the viewers ask
                $row['probe'] = rtrim($value, '/') . '/get_grid_info';
                $row['note'] = '/get_grid_info';
            } elseif (str_contains($value, '[AGENT_NAME]')) {
                // An address with the place of a name in it: asked with the name of an avatar, and without any name, two
                // different things to know (the page of an avatar, the page that is under the address)
                $with = $row + ['note' => $owner === null ? _('no avatar to ask for') : sprintf(_('with the name of %s'), $owner)];
                if ($owner === null) {
                    $with['skip'] = _('not asked (no avatar)');
                } else {
                    $with['url'] = str_replace('[AGENT_NAME]', $owner, $value);
                }
                $rows[] = $with;
                $row['url'] = rtrim((string) preg_replace('#\[AGENT_NAME\].*$#', '', $value), '/');
                $row['note'] = _('without a name');
                $rows[] = $row;
                continue;
            }
            $rows[] = $row;
        }
        $groups[] = ['title' => '[GridInfoService]', 'rows' => $rows];

        $groups[] = [
            'title' => '[LoginService]',
            'rows' => [
                ['label' => 'DestinationGuide', 'url' => $get('LoginService', 'DestinationGuide'), 'method' => 'get'],
                ['label' => 'MessageUrl', 'url' => $get('LoginService', 'MessageUrl'), 'method' => 'get'],
                ['label' => 'SearchURL', 'url' => $get('LoginService', 'SearchURL'), 'method' => 'get'],
            ],
        ];

        // What the simulators call (OpenSim.ini): the search, the registration on a directory, the offline messages
        $groups[] = ['title' => '[Search]', 'rows' => [$setting('Search', 'SearchURL', 'query.php', 'xmlrpc')]];
        $rows = [];
        foreach (OpenSim_Helpers_GridConfig::keys($sources, 'DataSnapshot', '/^DATA_SRV_/') as $key => $url) {
            $rows[] = ['label' => $key, 'url' => $url, 'method' => 'get'];
        }
        if (!$rows) {
            $register = $default('register.php');
            $rows[] = ['label' => 'DATA_SRV_*', 'url' => $register, 'method' => 'get'] + ($register !== '' ? ['note' => _('default address of the helpers')] : []);
        }
        $groups[] = ['title' => '[DataSnapshot]', 'rows' => $rows];
        $groups[] = ['title' => '[Messaging]', 'rows' => [$setting('Messaging', 'OfflineMessageURL', 'offline.php')]];

        // The scripts the viewers and the simulators ask: the viewers add the name of the script to the address of the economy
        $rows = [];
        $economy = (string) ($info['economy'] ?? '');
        foreach (['currency.php', 'landtool.php'] as $script) {
            $rows[] = ['label' => $script, 'url' => $economy === '' ? $default($script) : rtrim($economy, '/') . "/$script", 'method' => 'xmlrpc'];
        }
        $rows[] = ['label' => 'textgen.php', 'url' => $default('textgen.php'), 'method' => 'get'];
        $groups[] = ['title' => 'opensim-helpers', 'rows' => $rows];

        // What has no place in OpenSimulator's files, in Helpers.ini
        $helpers = $settings['helpers'] ?? [];
        $rows = [self::is_url($guide) ? ['label' => 'GuideSource', 'url' => $guide, 'method' => 'get'] : self::file_row('GuideSource', $guide)];
        foreach (['HypEventsURL', 'LogoURL'] as $key) {
            if (self::is_url($helpers[$key] ?? '')) {
                $rows[] = ['label' => $key, 'url' => $helpers[$key], 'method' => 'get'];
            }
        }
        $groups[] = ['title' => '[Helpers]', 'rows' => $rows];

        return $groups;
    }

    /**
     * Ask every address, all at once. An address that has a `probe` is asked there, one that has a `skip` is not asked and says
     * why. What an address answers is the HTTP code where its redirections lead; an address whose certificate this server
     * does not trust is asked again without the check, and says so.
     *
     * @param  list<array{title:string,rows:list<array<string,mixed>>}> $groups
     * @return list<array{title:string,rows:list<array<string,mixed>>}>
     */
    public static function answers($groups)
    {
        $requests = [];
        foreach ($groups as $g => $group) {
            foreach ($group['rows'] as $r => $row) {
                if ($row['url'] !== '' && !isset($row['skip'])) {
                    $requests["$g/$r"] = $row;
                }
            }
        }
        $results = self::ask($requests);
        foreach ($results as $id => $result) {
            if ($result['certificate'] !== 0) {
                $results[$id]['note'] = _('certificate not trusted by this server');
            }
        }

        foreach ($groups as $g => $group) {
            foreach ($group['rows'] as $r => $row) {
                $result = $results["$g/$r"] ?? null;
                if (isset($row['skip'])) {
                    $verdict = ['level' => 'none', 'status' => $row['skip'], 'ms' => null];
                } elseif ($result === null) {
                    $verdict = self::verdict(null, '', 0);
                } else {
                    $verdict = self::verdict($result['code'], $result['error'], $result['ms'], $result['errno']);
                }
                // What the row says of itself first, then what was found out
                $notes = array_filter([$row['note'] ?? null, $result['note'] ?? null]);
                $row = array_diff_key($row, ['method' => 0, 'probe' => 0, 'skip' => 0, 'note' => 0]) + $verdict;
                if ($notes) {
                    $row['note'] = implode(', ', $notes);
                }
                $groups[$g]['rows'][$r] = $row;
            }
        }

        return $groups;
    }

    /**
     * Ask a set of addresses at once.
     *
     * @param  array<string,array<string,mixed>> $requests id => row (url, probe, method)
     * @return array<string,array{code:int,errno:int,error:string,ms:int,certificate:int}> id => what came back
     */
    private static function ask($requests)
    {
        if (!$requests) {
            return [];
        }
        $multi = curl_multi_init();
        $handles = [];
        foreach ($requests as $id => $row) {
            $handle = curl_init($row['probe'] ?? $row['url']);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_USERAGENT => 'OpenSimHelpers-health',
                // What counts is where the address leads, not the redirection it has on the way
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                // Only the answer matters, not what follows it
                CURLOPT_WRITEFUNCTION => static fn($handle, $chunk) => strlen($chunk),
            ]);
            // The certificate is not what is asked: an address with one this server does not trust is asked all the same, and
            // the page says so
            curl_setopt_array($handle, [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]);
            if (($row['method'] ?? 'get') === 'xmlrpc') {
                curl_setopt_array($handle, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>',
                    CURLOPT_HTTPHEADER => ['Content-Type: text/xml'],
                ]);
            }
            curl_multi_add_handle($multi, $handle);
            $handles[$id] = $handle;
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.2);
            }
        } while ($running && $status === CURLM_OK);

        // The code of the error of each address is told by the set, not by the handle
        $errors = [];
        while (($done = curl_multi_info_read($multi)) !== false) {
            $errors[spl_object_id($done['handle'])] = (int) $done['result'];
        }
        $results = [];
        foreach ($handles as $id => $handle) {
            $errno = $errors[spl_object_id($handle)] ?? 0;
            $results[$id] = [
                'code' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
                'errno' => $errno,
                'error' => curl_error($handle) ?: ($errno ? curl_strerror($errno) : ''),
                'ms' => (int) round(curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000),
                // How the certificate checks out, 0 when it is valid (or there is none)
                'certificate' => (int) curl_getinfo($handle, CURLINFO_SSL_VERIFYRESULT),
            ];
            curl_multi_remove_handle($multi, $handle);
        }
        curl_multi_close($multi);

        return $results;
    }

    /**
     * What an answer means: 2xx is fine, a 4xx is an address that answers (an API says so to a request that is not its own),
     * 5xx and no answer are not. An address that does not answer gives the code of the error and what it says.
     *
     * @param  int|null $code  the HTTP code at the end of the redirections, 0 when nothing answered, null for an address that is not defined
     * @param  string   $error what curl says when nothing answered
     * @param  int      $errno the code of that error
     * @return array{level:string,status:string,ms:?int,detail?:string}
     */
    public static function verdict($code, $error, $ms, $errno = 0)
    {
        if ($code === null) {
            return ['level' => 'none', 'status' => _('not defined'), 'ms' => null];
        }
        if ($code === 0) {
            return ['level' => 'error', 'status' => 'cURL ' . _('error') . ' ' . $errno, 'ms' => null, 'detail' => $error];
        }
        $level = $code < 400 ? 'ok' : ($code < 500 ? 'warn' : 'error');

        return ['level' => $level, 'status' => (string) $code, 'ms' => $ms];
    }

    /** The row of a file or a folder the helpers read, named as in the setting. */
    private static function file_row($label, $path)
    {
        if ($path === '') {
            return ['label' => $label, 'url' => '', 'method' => 'get', 'level' => 'none', 'status' => _('not defined'), 'ms' => null];
        }

        return self::row($label, is_readable($path), $path);
    }

    /** A row for what is there or not, and the file it is about. */
    private static function row($label, $good, $detail = '')
    {
        return [
            'label' => $label,
            'url' => '',
            'level' => $good ? 'ok' : 'error',
            'status' => $good ? _('ok') : _('missing'),
            'detail' => $detail,
            'ms' => null,
        ];
    }

    /**
     * The name of an avatar of the grid to ask a profile for: there is always one, its owner, the first account made.
     *
     * @return string|null Firstname.Lastname, null when the accounts cannot be read
     */
    private static function owner()
    {
        $db = function_exists('db_for') ? db_for('UserAccountService') : null;
        $statement = $db ? $db->prepareAndExecute('SELECT FirstName, LastName FROM UserAccounts ORDER BY Created, FirstName LIMIT 1', []) : false;
        $row = $statement ? $statement->fetch(PDO::FETCH_NUM) : false;

        return $row ? "$row[0].$row[1]" : null;
    }

    /** The database of Robust, the one every query starts from. */
    private static function database_row()
    {
        global $OpenSimDB;

        return self::row('ConnectionString', !empty($OpenSimDB) && !empty($OpenSimDB->connected));
    }

    /**
     * What the helpers need of PHP, one row per extension.
     *
     * @return list<array<string,mixed>>
     */
    public static function extensions()
    {
        return [
            self::row('xmlrpc', function_exists('xmlrpc_encode'), 'extension or polyfill'),
            self::row('imagick', class_exists('Imagick'), 'text to image, web assets'),
            self::row('gettext', function_exists('gettext'), 'translations'),
        ];
    }

    /**
     * The ini files the helpers read, and whether the web server user can.
     *
     * @return list<array<string,mixed>>
     */
    public static function files($settings = null)
    {
        $rows = [];
        foreach ($settings ? OpenSim_Helpers_GridConfig::files($settings['dir']) : [] as $file) {
            if (is_file($file)) {
                $rows[] = self::row(basename($file), is_readable($file), $file);
            }
        }

        return $rows;
    }
}

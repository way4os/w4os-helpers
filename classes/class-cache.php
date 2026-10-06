<?php
/**
 * A small cache in files, for what is costly and does not change at every request (the status of the grid): a value is
 * kept for a while, a new one is made when it is out of date.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_Cache
{
    /**
     * The value kept under a key, or what $make gives (and is kept, for $ttl seconds).
     *
     * @param  string   $key
     * @param  int|callable $ttl seconds, or what gives them from the value (to keep a bad answer for less time)
     * @param  callable $make
     * @param  string|null $dir where the files are (a folder of the temporary one of the machine by default)
     * @return mixed
     */
    public static function remember($key, $ttl, $make, $dir = null)
    {
        $dir = $dir ?? sys_get_temp_dir() . '/opensim-helpers-cache';
        $file = $dir . '/' . md5($key) . '.json';
        if (is_file($file)) {
            $kept = json_decode((string) @file_get_contents($file), true);
            if (is_array($kept) && array_key_exists('value', $kept)) {
                $seconds = is_callable($ttl) ? $ttl($kept['value']) : $ttl;
                if (filemtime($file) + $seconds > time()) {
                    return $kept['value'];
                }
            }
        }
        $value = $make();
        if ((is_dir($dir) || @mkdir($dir, 0o755, true)) && ($temporary = @tempnam($dir, 'tmp'))) {
            file_put_contents($temporary, json_encode(['value' => $value]));
            @rename($temporary, $file);
        }

        return $value;
    }
}

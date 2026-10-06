<?php
/**
 * The destinations of the guide: read from a file or a URL, "Name|Region URL" lines, a name alone starts a category.
 * The same lines serve the boards of the scripts (a dash alone is a new column, a pipe alone an empty row: layout hints,
 * not destinations), and the older format that gives the grid and the region in the 4th and 5th columns. A third column
 * of `x,y,z` is the place in the region.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_Guide
{
    /**
     * The text of a source: a URL, or a file.
     *
     * @param  string|null $source
     * @return string|null null when it cannot be read
     */
    public static function read($source)
    {
        if (empty($source)) {
            return null;
        }
        set_error_handler(static fn() => true);
        $text = filter_var($source, FILTER_VALIDATE_URL) || is_file($source) ? file_get_contents($source) : false;
        restore_error_handler();

        return $text === false ? null : $text;
    }

    /**
     * The destinations of a text, by category.
     *
     * @param  string      $text
     * @param  string|null $first the title of the destinations that come before any category
     * @return array<string,list<array{name:string,url:string}>>
     */
    public static function parse($text, $first = null)
    {
        $destinations = [];
        $category = $first ?? _('Destinations Guide');
        foreach (explode("\n", str_replace("\r", '', (string) $text)) as $line) {
            $line = trim($line);
            // Comments, empty lines, the layout hints of the boards
            if ($line === '' || $line[0] === '#' || str_starts_with($line, '//') || $line === '-') {
                continue;
            }
            $parts = array_map('trim', explode('|', rtrim($line, '|')));
            if ($parts[0] === '') {
                continue;
            }
            $name = $parts[0];
            if (($parts[3] ?? '') !== '') {
                // The older format: the grid and the region are the 4th and 5th columns
                $url = $parts[3] . '/' . ($parts[4] ?? '');
            } else {
                $url = $parts[1] ?? '';
                // The place in the region: 128,128,30
                if ($url !== '' && preg_match('/^\d+(?:\.\d+)?,\d+(?:\.\d+)?(?:,\d+(?:\.\d+)?)?$/', $parts[2] ?? '')) {
                    $url .= '/' . str_replace(',', '/', $parts[2]);
                }
            }
            if ($url === '') {
                $category = $name;
            } else {
                $destinations[$category][] = ['name' => $name, 'url' => $url];
            }
        }

        return $destinations;
    }
}

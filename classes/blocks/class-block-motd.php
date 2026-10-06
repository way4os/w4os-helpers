<?php
/**
 * Block motd: the message of the day of the grid, the one the viewers show at login.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_Block_Motd
{
    /**
     * @param  array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function data($args = [])
    {
        $text = self::text();
        // The viewer gives the name of the avatar, a page does not know it; `\n` is a new line
        $text = str_replace(['<USERNAME>', '\n'], [_('traveler'), "\n"], $text);

        return ['title' => _('Message of the day'), 'text' => $text];
    }

    /** The message: the one of the grid, else a welcome. */
    public static function text()
    {
        if (defined('OPENSIM_MOTD') && OPENSIM_MOTD) {
            return OPENSIM_MOTD;
        }

        return sprintf(_('Welcome to %s, <USERNAME>!'), defined('OPENSIM_GRID_NAME') ? OPENSIM_GRID_NAME : 'OpenSimulator');
    }
}

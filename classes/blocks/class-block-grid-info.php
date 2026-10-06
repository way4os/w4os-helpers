<?php
/**
 * Block grid-info: the name of the grid and the address to log in with.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_Block_GridInfo
{
    /**
     * @param  array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function data($args = [])
    {
        $login = defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : null;
        $rows = [['label' => _('Grid Name'), 'value' => defined('OPENSIM_GRID_NAME') ? OPENSIM_GRID_NAME : null]];
        if (!empty($login)) {
            $rows[] = [
                'label' => _('Login URI'),
                'value' => preg_replace('#^https?://#', '', $login),
                // The link a viewer follows to log in to the grid
                'url' => opensim_format_tp($login, TPLINK_HOP),
            ];
        }

        return ['title' => _('Grid Information'), 'rows' => array_values(array_filter($rows, fn($row) => $row['value'] !== null))];
    }
}

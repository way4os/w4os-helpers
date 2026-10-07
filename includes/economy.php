<?php
/**
 * economy.php
 *
 * Provides functions required only by currency.php and landtool.php
 *
 * Requires an OpenSimulator Money Server
 *    [DTL/NSL Money Server for OpenSim](http://www.nsl.tuis.ac.jp/xoops/modules/xpwiki/?OpenSim%2FMoneyServer)
 * or [Gloebit module](http://dev.gloebit.com/opensim/configuration-instructions/)
 *
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link            https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 *
 * Includes portions of code from original DTLS/NLS Money Server, by:
 *   Melanie Thielker and Teravus Ovares (http://opensimulator.org/)
 *   Fumi.Iseki for CMS/LMS '09 5/31
 **/

require_once 'functions.php';

// The functions of the currency, the engine's (the helpers have only what is theirs: the page of the provider, the key)
require_once dirname(__DIR__) . '/vendor/magicoli/opensim-engine/includes/functions-economy.php';

/**
 * What the viewer is told when a purchase of currency cannot be done here: the message and the address it shows. The
 * module of the currency of the grid decides: Gloebit has the user finish the transaction on its web site, Podex sends
 * to its terminals in world, any other gets the help page of the helpers.
 *
 * @param  string $agentID
 * @return array{errorMessage:string,errorURI:?string}
 */
function currency_refusal($agentID)
{
    switch (CURRENCY_PROVIDER) {
        case 'podex':
            return [
                'errorMessage' => PODEX_ERROR_MESSAGE . ' ' . PODEX_REDIRECT_URL,
                'errorURI' => null,
            ];

        case 'gloebit':
            $baseurl = defined('GLOEBIT_SANDBOX') && GLOEBIT_SANDBOX ? 'https://sandbox.gloebit.com/purchase' : 'https://www.gloebit.com/purchase';
            $server_info = opensim_get_server_info($agentID);
            $informurl = !empty($server_info['serverIP'])
                ? "http://{$server_info['serverIP']}:{$server_info['serverHttpPort']}/gloebit/buy_complete?agentId={$agentID}"
                : '';

            return [
                'errorMessage' => 'Click OK to finish the transaction on Gloebit website.',
                'errorURI' => "{$baseurl}?reset&r=&inform=$informurl",
            ];
    }

    return [
        'errorMessage' => 'Unable to process the transaction. The gateway denied your charge. Open help page?',
        'errorURI' => defined('W4OS_GRID_INFO') && !empty(W4OS_GRID_INFO['help']) ? W4OS_GRID_INFO['help'] : CURRENCY_HELPER_URL,
    ];
}

/**
 * Money is not served without the key: a bad configuration, said to whoever asks and to the log.
 */
function currency_require_key()
{
    if (currency_has_key()) {
        return;
    }
    // Not a 500, which says nothing: the service is not available until the grid is configured
    $message = 'Bad configuration: the currency is not served without its key (MoneyScriptAccessKey, [MoneyServer] of MoneyServer.ini)';
    error_log("opensim-helpers: $message");
    osXmlDie($message, 503);
}

// XML RPC


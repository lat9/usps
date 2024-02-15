<?php
/**
 * USPS Module for Zen Cart v1.5.6 through 2.0.0
 *
 * @version $Id: usps.php 2024-02-14 lat9 Version K11j $
 *
 */
class UspsAdminMessaging
{
    // -----
    // An admin class to warn the admin if the USPS shipping module is installed, but
    // not configured to receive quotes.
    //
    public function __construct()
    {
        global $current_page, $messageStack;

        // -----
        // If the USPS shipping module isn't installed, nothing further to be done.
        //
        if (!defined('MODULE_SHIPPING_USPS_STATUS') || empty($current_page)) {
            return;
        }

        // -----
        // The message, if needed, will be displayed *only* when an admin is viewing either
        // the Configuration :: Shipping/Packaging or Modules :: Shipping pages.
        switch (str_replace('.php', '', $current_page)) {
            case FILENAME_CONFIGURATION:
                if (!isset($_GET['gID']) || ((int)$_GET['gID']) !== 7) {
                    return;
                }
                break;
            case FILENAME_MODULES:
                if (!isset($_GET['set']) || $_GET['set'] !== 'shipping') {
                    return;
                }
                break;
            default:
                return;
                break;
        }

        // -----
        // At this point, it's known that the USPS shipping module is installed and that
        // an admin is viewing one of the two pages on which the messaging is displayed.
        //
        // If either the USPS USERID is its default 'NONE' or an empty string OR if the
        // USPS PASSWORD hasn't been set, the message is displayed.  There's an additional
        // message displayed for versions _prior to_ zc200, since there is 'core' messaging
        // still in place in those releases that contains no-longer-valid instructions.
        //
        if (MODULE_SHIPPING_USPS_USERID === 'NONE' || MODULE_SHIPPING_USPS_USERID === '' || !defined('MODULE_SHIPPING_USPS_PASSWORD') || MODULE_SHIPPING_USPS_PASSWORD === '') {
            $usps_warning_message = MODULE_SHIPPING_USPS_WARNING;
            if (PROJECT_VERSION_MAJOR === '1') {
                $usps_warning_message .= '<br>' . MODULE_SHIPPING_USPS_IGNORE;
            }
            $messageStack->add($usps_warning_message, 'warning');
        }
    }
}

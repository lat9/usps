<?php
/**
 * USPS Module language file for Zen Cart v1.5.8+
 *
 * Last updated: 2024-08-25 Version K11l (created)
 */
$define = [
    'MODULE_SHIPPING_USPS_TEXT_TITLE' => 'United States Postal Service',
    'MODULE_SHIPPING_USPS_TEXT_SHORT_TITLE' => 'USPS',
    'MODULE_SHIPPING_USPS_TEXT_DESCRIPTION' => 'United States Postal Service<br><br>You will need to have registered an account with USPS at https://secure.shippingapis.com/registration/ to use this module<br><br>USPS expects you to use pounds as weight measure for your products.',

    'MODULE_SHIPPING_USPS_TEXT_TEST_MODE_NOTICE' => '<br><span class="alert">Your account is in TEST MODE. Do not expect to see usable rate quotes until your USPS account is moved to the production server (1-800-344-7779) and you have set the module to production mode in Zen Cart admin.</span>',
    'MODULE_SHIPPING_USPS_TEXT_SERVER_ERROR' => 'An error occurred in obtaining USPS shipping quotes.<br>If you prefer to use USPS as your shipping method, please try refreshing this page, or contact the store owner.',
    'MODULE_SHIPPING_USPS_TEXT_ERROR' => 'We are unable to find a USPS shipping quote suitable for your mailing address and the shipping methods we typically use.<br>If you prefer to use USPS as your shipping method, please contact us for assistance.<br>(Please check that your Zip Code is entered correctly.)',

    'MODULE_SHIPPING_USPS_TEXT_DAY' => 'day',
    'MODULE_SHIPPING_USPS_TEXT_DAYS' => 'days',
    'MODULE_SHIPPING_USPS_TEXT_WEEKS' => 'weeks',

    'MODULE_SHIPPING_USPS_TEXT_INTL_SHOW' => 'View Shipping Regulations',
    'MODULE_SHIPPING_USPS_TEXT_INTL_HIDE' => 'Hide Shipping Regulations',
];

// -----
// A collection of 'soft' configuration settings.  Some of these might be considered for
// updates to the shipping-module's database configuration in the future.
//
// -----
// For international orders, should the USPS shipping regulations for the ship-to country be displayed
// to the customer?  Choose 'True' to display the regulations or 'False' (the default) otherwise.
//
zen_define_default('MODULE_SHIPPING_USPS_REGULATIONS', 'False');      //-Either 'False' (default) or 'True'

// -----
// Identifies the shipping cut-off time for the store, in the format 'HHMM', in the range '1200' to '2300'.
// Out-of-range values will be reset to '1400' (the default).
//
zen_define_default('MODULE_SHIPPING_USPS_SHIPPING_CUTOFF', '1400');

return $define;

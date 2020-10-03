<?php
/**
 * USPS Module for Zen Cart v1.3.x thru v1.6
 * USPS RateV4 Intl RateV2 - Sept 16, 2017 Version K10

 * Prices from: Sept 16, 2017
 * Rates Names: Sept 16, 2017
 * Services Names: Sept 16, 2017
 *
 * @package shippingMethod
 * @copyright Copyright 2003-2016 Zen Cart Development Team

 * @copyright Portions Copyright 2003 osCommerce
 * @copyright Portions adapted from 2012 osCbyJetta
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: usps.php 2017-09-16 ajeh - tflmike Version K10 $
 */
define('MODULE_SHIPPING_USPS_TEXT_TITLE', 'United States Postal Service');
define('MODULE_SHIPPING_USPS_TEXT_SHORT_TITLE', 'USPS');
define('MODULE_SHIPPING_USPS_TEXT_DESCRIPTION', 'United States Postal Service<br /><br />You will need to have registered an account with USPS at https://secure.shippingapis.com/registration/ to use this module<br /><br />USPS expects you to use pounds as weight measure for your products.');

define('MODULE_SHIPPING_USPS_TEXT_TEST_MODE_NOTICE', '<br /><span class="alert">Your account is in TEST MODE. Do not expect to see usable rate quotes until your USPS account is moved to the production server (1-800-344-7779) and you have set the module to production mode in Zen Cart admin.</span>');
define('MODULE_SHIPPING_USPS_TEXT_SERVER_ERROR', 'An error occurred in obtaining USPS shipping quotes.<br />If you prefer to use USPS as your shipping method, please try refreshing this page, or contact the store owner.');
define('MODULE_SHIPPING_USPS_TEXT_ERROR', 'We are unable to find a USPS shipping quote suitable for your mailing address and the shipping methods we typically use.<br />If you prefer to use USPS as your shipping method, please contact us for assistance.<br />(Please check that your Zip Code is entered correctly.)');

define('MODULE_SHIPPING_USPS_TEXT_DAY', 'day');
define('MODULE_SHIPPING_USPS_TEXT_DAYS', 'days');
define('MODULE_SHIPPING_USPS_TEXT_WEEKS', 'weeks');

define('MODULE_SHIPPING_USPS_TEXT_INTL_SHOW', 'View Shipping Regulations');
define('MODULE_SHIPPING_USPS_TEXT_INTL_HIDE', 'Hide Shipping Regulations');

// -----
// A collection of 'soft' configuration settings.  Some of these might be considered for
// updates to the shipping-module's database configuration in the future.
//
// -----
// For international orders, should the USPS shipping regulations for the ship-to country be displayed
// to the customer?  Choose 'True' to display the regulations or 'False' (the default) otherwise.
//
if (!defined('MODULE_SHIPPING_USPS_REGULATIONS')) define('MODULE_SHIPPING_USPS_REGULATIONS', 'True');      //-Either 'False' (default) or 'True'

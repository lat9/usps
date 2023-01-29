<?php
/**
 * USPS Module for Zen Cart v1.5.4 through 1.5.8
 * USPS RateV4 Intl RateV2 - Jamuary 29, 2023 K11f

 * Prices from: Sept 16, 2017
 * Rates Names: Sept 16, 2017
 * Services Names: Sept 16, 2017
 *
 * @package shippingMethod
 * @copyright Copyright 2003-2016 Zen Cart Development Team

 * @copyright Portions Copyright 2003 osCommerce
 * @copyright Portions adapted from 2012 osCbyJetta
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: usps.php 2017-09-16 ajeh - tflmike, 2018-03-28 - bislewl  Version K10 $
 * @version $Id: usps.php 2020-09-24 lat9 Version K11 $
 * @version $Id: usps.php 2021-05-05 lat9 Version K11a $
 * @version $Id: usps.php 2022-07-10 lat9 Version K11b $
 * @version $Id: usps.php 2022-07-12 lat9 Version K11c $
 * @version $Id: usps.php 2022-07-30 lat9 Version K11d $
 * @version $Id: usps.php 2022-08-07 lat9 Version K11e $
 * @version $Id: usps.php 2023-01-29 lat9 Version K11f $
 */
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal Access');
}
/**
 * USPS Shipping Module class
 *
 */
class usps extends base
{
  /**
   * Declare shipping module alias code
   *
   * @var string
   */
    public
        $code,
  /**
   * Shipping module display name
   *
   * @var string
   */
        $title,
  /**
   * Shipping module display description
   *
   * @var string
   */
        $description,
  /**
   * Shipping module icon filename/path
   *
   * @var string
   */
        $icon,
  /**
   * Shipping module status
   *
   * @var boolean
   */
        $enabled,
        $sort_order;

  /**
   * Shipping module list of supported countries
   *
   * @var array
   */
    protected
        $countries,
    /**
     *  use USPS translations for US shops
     *  @var string
     */
        $usps_countries,
    /**
     * List of services checkboxes, extracted out into an array
     * @var array
     */
        $typeCheckboxesSelected,
    /**
     * USPS certain methods don't qualify if declared value is greater than $400
     * @var array
     */
        $types_to_skip_over_certain_value,
    /**
     * Uninsurable value for the quote.
     * @var float
     */
        $uninsurable_value,
    /**
     * Insurable value for the quote.
     * @var float
     */
        $insurable_value,

        $tax_class,                     //- int
        $tax_basis,                     //- string
        $debug_enabled,                 //- bool
        $debug_filename,                //- string
        $getTransitTime,                //- bool
        $shipping_cutoff_time,          //- string
        $pounds,                        //- int
        $ounces,                        //- float
        $is_us_shipment,                //- bool
        $machinable,                    //- string
        $transitTimeCalculationMode,    //- string
        $uspsQuote,                     //- ???
        $quotes,                        //- array
        $transittime,                   //- array
        $_check,                        //- ???
        $orders_tax,                    //- mixed
        $shipment_value,                //- float
        $commError,                     //- ???
        $commErrNo,                     //- ???
        $commInfo;                      //- ??

    // -----
    // Class constant to define the current module version.
    //
    const USPS_CURRENT_VERSION = '2023-01-29 K11f';

    // -----
    // Class constant to define the shipping-method's Zen Cart plugin ID.
    //
    const USPS_ZEN_CART_PLUGIN_ID = 1292;   //- Set to 0 to disable plugin-version checking.

  /**
   * Constructor
   *
   * @return object
   */

    public function __construct()
    {
        global $template, $current_page_base, $current_page;

        $this->code = 'usps';
        $this->title = (defined('MODULE_SHIPPING_USPS_TITLE_SIZE') && MODULE_SHIPPING_USPS_TITLE_SIZE === 'Short' ? MODULE_SHIPPING_USPS_TEXT_SHORT_TITLE : MODULE_SHIPPING_USPS_TEXT_TITLE);
        $this->description = MODULE_SHIPPING_USPS_TEXT_DESCRIPTION;
        $this->sort_order = (defined('MODULE_SHIPPING_USPS_SORT_ORDER')) ? MODULE_SHIPPING_USPS_SORT_ORDER : null;
        if ($this->sort_order === null) {
            return false;
        }

        $this->enabled = (MODULE_SHIPPING_USPS_STATUS === 'True');

        $this->tax_class = (int)MODULE_SHIPPING_USPS_TAX_CLASS;
        $this->tax_basis = MODULE_SHIPPING_USPS_TAX_BASIS;

        // -----
        // Set debug-related variables for use by the uspsDebug method.
        //
        $this->debug_enabled = (MODULE_SHIPPING_USPS_DEBUG_MODE === 'Logs');
        $this->debug_filename = DIR_FS_LOGS . '/SHIP_usps_Debug_' . date('Ymd_His') . '.log';

        $this->typeCheckboxesSelected = explode(', ', MODULE_SHIPPING_USPS_TYPES);
        $this->update_status();

        // -----
        // If the shipping module is enabled, some additional environment-specific checks are
        // needed to see if the module can remain enabled and/or to notify the current admin
        // of any configuration issues.
        //
        if ($this->enabled === true) {
            // -----
            // Admin-specific processing, limited to "Modules :: Shipping" so that any additions
            // to the shipping-module's name don't show up during 'Edit Orders' (and possibly other
            // admin plugins).
            //
            if (IS_ADMIN_FLAG === true) {
                // -----
                // During admin processing (Modules :: Shipping), let the admin know of some test
                // conditions.  Limiting to that script so that the additions don't show up during
                // Edit Orders (and possibly others).
                //
                if ($current_page === 'modules.php') {
                    $this->adminInitializationChecks();
                }
            // -----
            // Otherwise, storefront checks and initializations.
            //
            } else {
                if (isset($template)) {
                    $this->icon = $template->get_template_dir('shipping_usps.gif', DIR_WS_TEMPLATE, $current_page_base, 'images/icons') . '/shipping_usps.gif';
                }
                $this->storefrontInitialization();
            }
        }
        $this->notify('NOTIFY_SHIPPING_USPS_CONSTRUCTOR_COMPLETED');
    }

    // -----
    // Admin-specific initialization checks, called from the class constructor.
    //
    protected function adminInitializationChecks()
    {
        global $db, $messageStack;

        if ($this->debug_enabled === true) {
            $this->title .=  '<span class="alert"> (Debug is ON: ' . MODULE_SHIPPING_USPS_DEBUG_MODE . ')</span>';
        }
        if (MODULE_SHIPPING_USPS_SERVER !== 'production') {
            $this->title .=  '<span class="alert"> (USPS Server set to: ' . MODULE_SHIPPING_USPS_SERVER . ')</span>';
        }

        $new_version_details = plugin_version_check_for_updates(self::USPS_ZEN_CART_PLUGIN_ID, self::USPS_CURRENT_VERSION);
        if ($new_version_details !== false) {
            $this->title .= '<span class="alert">' . ' - NOTE: A NEW VERSION OF THIS PLUGIN IS AVAILABLE. <a href="' . $new_version_details['link'] . '" target="_blank">[Details]</a>' . '</span>';
        }

        // -----
        // If still enabled, check to make sure that at least one shipping-method has been chosen (otherwise,
        // no quotes can be returned on the storefront.  If the condition is found, indicate that the module
        // is disabled so that the amber warning symbol appears in the admin shipping-modules' listing.
        //
        if ($this->enabled === true) {
            $this->checkConfiguration();

            // -----
            // If the store's version has changed from the current version or if the number of configuration 'keys'
            // has changed, check first to see if automatic updates can be performed; if so do them!  Otherwise,
            // the site's admin will need to save the current settings and uninstall/reinstall the module.
            //
            $chk_sql = $db->Execute(
                "SELECT * 
                   FROM " . TABLE_CONFIGURATION . " 
                  WHERE configuration_key like 'MODULE\_SHIPPING\_USPS\_%' "
            );
            if (MODULE_SHIPPING_USPS_VERSION !== self::USPS_CURRENT_VERSION || count($this->keys()) !== $chk_sql->RecordCount()) {
                switch (true) {
                    case (MODULE_SHIPPING_USPS_VERSION ==='2021-05-05 K11a'):
                        $db->Execute(
                            "UPDATE " . TABLE_CONFIGURATION . "
                                SET configuration_value = REPLACE(configuration_value, 'Priority MailTM', 'Priority MailRM'),
                                    set_function = REPLACE(set_function, 'Priority MailTM', 'Priority MailRM')
                              WHERE configuration_key = 'MODULE_SHIPPING_USPS_TYPES'
                              LIMIT 1"
                        );
                        $db->Execute(
                            "UPDATE " . TABLE_CONFIGURATION . "
                                SET configuration_value = REPLACE(configuration_value, 'Priority Mail ExpressTM', 'Priority Mail ExpressRM'),
                                    set_function = REPLACE(set_function, 'Priority Mail ExpressTM', 'Priority Mail ExpressRM')
                              WHERE configuration_key = 'MODULE_SHIPPING_USPS_TYPES'
                              LIMIT 1"
                        );

                    case (MODULE_SHIPPING_USPS_VERSION === '2022-07-10 K11b'):          //- Fall-through from above to continue checks
                        $db->Execute(
                            "UPDATE " . TABLE_CONFIGURATION . "
                                SET configuration_value = REPLACE(configuration_value, 'Priority Mail Express InternationalTM', 'Priority Mail Express InternationalRM'),
                                    set_function = REPLACE(set_function, 'Priority Mail Express InternationalTM', 'Priority Mail Express InternationalRM')
                              WHERE configuration_key = 'MODULE_SHIPPING_USPS_TYPES'
                              LIMIT 1"
                        );

                    case (MODULE_SHIPPING_USPS_VERSION === '2022-07-12 K11c'):          //- Fall-through from above to continue checks
                    case (MODULE_SHIPPING_USPS_VERSION === '2022-07-30 K11d'):          //- Fall-through from above to continue checks
                    case (MODULE_SHIPPING_USPS_VERSION === '2022-08-07 K11e'):          //- Fall-through from above to continue checks
                        // -----
                        // 'Priority MailRM Regional Rate Box A' and 'Priority MailRM Regional Rate Box B' methods are no longer
                        // supported by USPS; remove them from the configured shipping types and update the parameters for that
                        // setting's 'set_function'.
                        //
                        // The configured shipping types are laid out as an imploded string of a numerically-indexed array, either 3 or 4 elements
                        // per selection.
                        //
                        // - If the current element matches one of the shipping types, implying that it is currently selected, then
                        //   the type's entry has 4 elements:
                        //   - The name, minimum weight, maximum weight and handling fee
                        // - Otherwise, the associated shipping type is *not* selected and the type's entry has 3 elements:
                        //   - The minimum weight, maximum weight and handling fee.
                        //
                        // See the zen_cfg_usps_services function at the bottom of this module for admin-level configuration processing.
                        //
                        $usps_configured_types = explode(', ', MODULE_SHIPPING_USPS_TYPES);
                        $usps_shipping_types_old = [
                            'First-Class Mail Letter',
                            'First-Class Mail Large Envelope',
                            'First-Class Package Service - RetailTM',
                            'First-ClassTM Package Service',
                            'Media Mail Parcel',
                            'USPS Retail GroundRM',
                            'Priority MailRM',
                            'Priority MailRM Flat Rate Envelope',
                            'Priority MailRM Legal Flat Rate Envelope',
                            'Priority MailRM Padded Flat Rate Envelope',
                            'Priority MailRM Small Flat Rate Box',
                            'Priority MailRM Medium Flat Rate Box',
                            'Priority MailRM Large Flat Rate Box',
                            'Priority MailRM Regional Rate Box A',
                            'Priority MailRM Regional Rate Box B',
                            'Priority Mail ExpressRM',
                            'Priority Mail ExpressRM Flat Rate Envelope',
                            'Priority Mail ExpressRM Legal Flat Rate Envelope',
                            'First-Class MailRM International Letter',
                            'First-Class MailRM International Large Envelope',
                            'First-Class Package International ServiceTM',
                            'Priority Mail InternationalRM',
                            'Priority Mail InternationalRM Flat Rate Envelope',
                            'Priority Mail InternationalRM Small Flat Rate Box',
                            'Priority Mail InternationalRM Medium Flat Rate Box',
                            'Priority Mail InternationalRM Large Flat Rate Box',
                            'Priority Mail Express InternationalTM',
                            'Priority Mail Express InternationalTM Flat Rate Envelope',
                            'USPS GXGTM Envelopes',
                            'Global Express GuaranteedRM (GXG)',
                        ];
                        $usps_configured_types = explode(', ', MODULE_SHIPPING_USPS_TYPES);
                        $configured_indices_to_remove = [];
                        for ($sto = 0, $sto_cnt = count($usps_shipping_types_old), $ct = 0; $sto < $sto_cnt; $sto++) {
                            $current_shipping_type = $usps_shipping_types_old[$sto];
                            $item_entries = ($usps_configured_types[$ct] === $current_shipping_type) ? 4 : 3;
                            if ($current_shipping_type === 'Priority MailRM Regional Rate Box A' || $current_shipping_type === 'Priority MailRM Regional Rate Box B') {
                                $configured_indices_to_remove = array_merge($configured_indices_to_remove, range($ct, $ct + $item_entries - 1));
                            }
                            $ct += $item_entries;
                        }
                        foreach ($configured_indices_to_remove as $ct) {
                            unset($usps_configured_types[$ct]);
                        }
                        $usps_configured_types = implode(', ', $usps_configured_types);
                        $db->Execute(
                            "UPDATE " . TABLE_CONFIGURATION . "
                                SET configuration_value = '$usps_configured_types',
                                    set_function = 'zen_cfg_usps_services([\'First-Class Mail Letter\', \'First-Class Mail Large Envelope\', \'First-Class Package Service - RetailTM\', \'First-ClassTM Package Service\', \'Media Mail Parcel\', \'USPS Retail GroundRM\', \'Priority MailRM\', \'Priority MailRM Flat Rate Envelope\', \'Priority MailRM Legal Flat Rate Envelope\', \'Priority MailRM Padded Flat Rate Envelope\', \'Priority MailRM Small Flat Rate Box\', \'Priority MailRM Medium Flat Rate Box\', \'Priority MailRM Large Flat Rate Box\', \'Priority Mail ExpressRM\', \'Priority Mail ExpressRM Flat Rate Envelope\', \'Priority Mail ExpressRM Legal Flat Rate Envelope\', \'First-Class MailRM International Letter\', \'First-Class MailRM International Large Envelope\', \'First-Class Package International ServiceTM\', \'Priority Mail InternationalRM\', \'Priority Mail InternationalRM Flat Rate Envelope\', \'Priority Mail InternationalRM Small Flat Rate Box\', \'Priority Mail InternationalRM Medium Flat Rate Box\', \'Priority Mail InternationalRM Large Flat Rate Box\', \'Priority Mail Express InternationalTM\', \'Priority Mail Express InternationalTM Flat Rate Envelope\', \'USPS GXGTM Envelopes\', \'Global Express GuaranteedRM (GXG)\'], ',
                                    last_modified = now()
                              WHERE configuration_key = 'MODULE_SHIPPING_USPS_TYPES'
                              LIMIT 1"
                        );
                        break;                                                          //- END OF AUTOMATIC UPDATE CHECKS!

                    default:
                        $this->title .= '<span class="alert">' . ' - Missing Keys or Out of date you should reinstall!' . '</span>';
                        $this->enabled = false;
                        break;
                }
                if ($this->enabled === true) {
                    $db->Execute(
                        "UPDATE " . TABLE_CONFIGURATION . "
                            SET configuration_value = '" . self::USPS_CURRENT_VERSION . "',
                                set_function = 'zen_cfg_select_option(array(\'" . self::USPS_CURRENT_VERSION . "\'),'
                          WHERE configuration_key = 'MODULE_SHIPPING_USPS_VERSION'
                          LIMIT 1"
                    );
                    $messageStack->add_session('The USPS shipping module was automatically updated to v' . self::USPS_CURRENT_VERSION . '.', 'success');
                }
            }
        }
    }

    // -----
    // Called from the class-constructor during storefront operations.
    //
    protected function storefrontInitialization()
    {
        // -----
        // Quick return if the shipping-module's configuration will not allow it to
        // gather valid USPS quotes.  The shipping-module is disabled if this is the case.
        //
        if ($this->checkConfiguration() === false) {
            return;
        }

        // prepare list of countries which USPS ships to
        $this->countries = $this->country_list();

        // use USPS translations for US shops (USPS treats certain regions as "US States" instead of as different "countries", so we translate here)
        $this->usps_countries = $this->usps_translation();

        // certain methods don't qualify if declared value is greater than $400
        $this->types_to_skip_over_certain_value = [
           'Priority Mail InternationalRM Flat Rate Envelope' => 400,
           'Priority Mail InternationalRM Small Flat Rate Envelope' => 400,
           'Priority Mail InternationalRM Small Flat Rate Box' => 400,
           'Priority Mail InternationalRM Legal Flat Rate Envelope' => 400,
           'Priority Mail InternationalRM Padded Flat Rate Envelope' => 400,
           'Priority Mail InternationalRM Gift Card Flat Rate Envelope' => 400,
           'Priority Mail InternationalRM Window Flat Rate Envelope' => 400,
           'First-Class MailRM International Letter' => 400,
           'First-Class MailRM International Large Envelope' => 400,
           'First-Class Package International ServiceTM' => 400,
        ];

        $this->getTransitTime = (strpos(MODULE_SHIPPING_USPS_OPTIONS, 'Display transit time') !== false);

        // -----
        // Save the store's shipping cut-off time, in the range '1200' to '2300', removing any non-digit
        // characters.  If the value's empty, i.e. no digits present, reset the value to '1400' (the default).
        //
        $this->shipping_cutoff_time = MODULE_SHIPPING_USPS_SHIPPING_CUTOFF; // 1400 = 14:00 = 2pm ---- must be HHMM without punctuation
        $this->shipping_cutoff_time = preg_replace('/[^\d]/', '', $this->shipping_cutoff_time);
        if (empty($this->shipping_cutoff_time)) {
            $this->shipping_cutoff_time = '1400';
        }
    }

    // -----
    // Common storefront/admin configuration checking.  Called from adminInitializationChecks
    // and storefrontInitialization.  Will auto-disable the shipping method if either no services
    // have been selected or the country-of-origin is not the US.
    //
    protected function checkConfiguration()
    {
        $usps_shipping_methods_cnt = 0;
        foreach ($this->typeCheckboxesSelected as $requested_type) {
            if (is_numeric($requested_type)) {
                continue;
            }
            $usps_shipping_methods_cnt++;
        }
        if ($usps_shipping_methods_cnt === 0) {
            $this->enabled = false;
            if (IS_ADMIN_FLAG === true) {
                $this->title .= '<span class="alert">' . ' - Nothing has been selected for Quotes.' . '</span>';
            }
        }

        if (SHIPPING_ORIGIN_COUNTRY !== '223') {
            $this->enabled = false;
            if (IS_ADMIN_FLAG === true) {
                $this->title .= '<span class="alert">' . ' - USPS can only ship from USA, but your store is configured with another origin! See Admin->Configuration->Shipping/Packaging.' . '</span>';
            }
        }
        return $this->enabled;
    }

    /**
      * check whether this module should be enabled or disabled based on zone assignments and any other rules
    */
    public function update_status()
    {
        global $order, $db;
        if (IS_ADMIN_FLAG === true) {
            return;
        }

        // disable only when entire cart is free shipping
        if (!zen_get_shipping_enabled($this->code)) {
            $this->enabled = false;
        }

        if ($this->enabled === true && isset($order) && (int)MODULE_SHIPPING_USPS_ZONE > 0) {
            $check_flag = false;
            $check = $db->Execute(
                "SELECT zone_id 
                   FROM " . TABLE_ZONES_TO_GEO_ZONES . " 
                  WHERE geo_zone_id = " . (int)MODULE_SHIPPING_USPS_ZONE . " 
                    AND zone_country_id = " . (int)$order->delivery['country']['id'] . " 
                  ORDER BY zone_id ASC"
            );

            // -----
            // NOTE: Using the legacy form of traversing the $db output; will be updated once support
            // is dropped for Zen Cart versions prior to v1.5.7!
            //
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1 || $check->fields['zone_id'] === $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }

        global $template, $current_page_base;
        // CUSTOMIZED CONDITIONS GO HERE
        // Optionally add additional code here to change $this->enabled to false based on whatever custom rules you require.
        // -----

        // -----
        // eof: optional additional code

        $this->notify('NOTIFY_SHIPPING_USPS_UPDATE_STATUS');
    }

    /**
     * Prepare request for quotes and process obtained results
     *
     * @param string $method
     * @return array of quotation results
     */
    public function quote($method = '')
    {
        global $order, $shipping_weight, $shipping_num_boxes, $currencies;

        $usps_shipping_quotes = '';
        $iInfo = '';
        $methods = [];
        $usps_shipping_weight = ($shipping_weight < 0.0625) ? 0.0625 : $shipping_weight;
        $this->pounds = (int)$usps_shipping_weight;

        // usps currently cannot handle more than 5 digits on international
        // change to 2 if International rates fail based on Tare Settings
        $this->ounces = ceil(round(16 * ($usps_shipping_weight - $this->pounds), MODULE_SHIPPING_USPS_DECIMALS));

        // Determine machinable or not
        // weight must be less than 35lbs and greater than 6 ounces or it is not machinable
        switch (true) {
            // force machinable for $0.49 remove the false && from the first case
            case (false && ($this->is_us_shipment === true && ($this->pounds === 0 && $this->ounces <= 1))):
                // override admin choice, too light
                $this->machinable = 'True';
                break;

            case ($this->is_us_shipment === true && ($this->pounds === 0 && $this->ounces < 6)):
                // override admin choice, too light
                $this->machinable = 'False';
                break;

            case (!$this->is_us_shipment === true && ($this->pounds === 0 && $this->ounces < 3.5)):
                // override admin choice, too light
                $this->machinable = 'False';
                break;

            case ($usps_shipping_weight > 35):
                // override admin choice, too heavy
                $this->machinable = 'False';
                break;

            default:
                // admin choice on what to use
                $this->machinable = MODULE_SHIPPING_USPS_MACHINABLE;
                break;
        }

        // What method to use for calculating display of transit times
        // Options: 'NEW' = <ShipDate>, 'OLD' = extra API calls, 'CUSTOM' = hard-coded elsewhere in the parseDomesticTransitTimeResults() function.
        $this->transitTimeCalculationMode = MODULE_SHIPPING_USPS_TRANSIT_TIME_CALCULATION_MODE;
        // NOTE: at the present time, with the Test/Staging server, using the new method of adding shipdate adds a lot more time to obtaining quotes

        // request quotes
        $this->notify('NOTIFY_SHIPPING_USPS_BEFORE_GETQUOTE', [], $order, $usps_shipping_weight, $shipping_num_boxes);
        
        $this->uspsQuote = $this->_getQuote();

        $this->notify('NOTIFY_SHIPPING_USPS_AFTER_GETQUOTE', [], $order, $usps_shipping_weight, $shipping_num_boxes);
        $uspsQuote = $this->uspsQuote;

        // were errors encountered?
        if ($uspsQuote === -1) {
            $this->quotes = [
                'module' => $this->title,
                'error' => MODULE_SHIPPING_USPS_TEXT_SERVER_ERROR . (MODULE_SHIPPING_USPS_SERVER == 'test' ? MODULE_SHIPPING_USPS_TEXT_TEST_MODE_NOTICE : '')
            ];
            return $this->quotes;
        }
        if (!is_array($uspsQuote)) {
            $this->quotes = [
                'module' => $this->title,
                'error' => MODULE_SHIPPING_USPS_TEXT_ERROR . (MODULE_SHIPPING_USPS_SERVER == 'test' ? MODULE_SHIPPING_USPS_TEXT_TEST_MODE_NOTICE : '')
            ];
            return $this->quotes;
        }
        if (isset($uspsQuote['Number']) && !isset($uspsQuote['error'])) {
            $uspsQuote['error'] = $uspsQuote['Number'] . ' - ' . $uspsQuote['Description'];
        }
        if (isset($uspsQuote['Package']['Error'])) {
            $uspsQuote['error'] = $uspsQuote['Package']['Error']['Number'] . ' - ' . $uspsQuote['Package']['Error']['Description'];
        }
        if (isset($uspsQuote['error'])) {
            if (isset($uspsQuote['Number']) && $uspsQuote['Number'] == -2147219085) {
                $this->quotes = [
                    'module' => $this->title,
                    'error' => 'NO OPTIONS INSTALLED: ' . $uspsQuote['error']
                ];
            } else {
                $this->quotes = [
                    'module' => $this->title,
                    'error' => $uspsQuote['error']
                ];
            }
            return $this->quotes;
        }

        // if we got here, there were no errors, so proceed with evaluating the obtained quotes

        $services_domestic = 'Domestic Services Selected: ' . "\n";
        $services_international = 'International Services Selected: ' . "\n";

        // obtain list of selected services ... so we can evaluate returned quoted services against the services selected by the store administrator (since USPS returns more than we ask for)
        $servicesSelectedDomestic = $this->special_services();
        $servicesSelectedIntl = $this->extra_service();

        // Domestic/US destination:
        if ($this->is_us_shipment === true) {
            $dExtras = []; // We're going to populate this with a list of "friendly names" of services "checked" to "Y" in our checkboxes
            $dOptions = explode(', ', MODULE_SHIPPING_USPS_DMST_SERVICES); // domestic

            foreach ($dOptions as $key => $val) {
                if (strlen($dOptions[$key]) > 1) {
                    if ($dOptions[$key + 1] === 'C' || $dOptions[$key + 1] === 'S' || $dOptions[$key + 1] === 'Y') {
                        $services_domestic .= $dOptions[$key] . "\n";
                        $dExtras[$dOptions[$key]] = $dOptions[$key + 1];
                    }
                }
            }
        } else {
            // International destination:
            $iExtras = [];
            $iOptions = explode(', ', MODULE_SHIPPING_USPS_INTL_SERVICES);
            foreach ($iOptions as $key => $val) {
                if (strlen($iOptions[$key]) > 1) {
                    if ($iOptions[$key + 1] === 'C' || $iOptions[$key + 1] === 'S' || $iOptions[$key + 1] === 'Y') {
                        $services_international .= $iOptions[$key] . "\n";
                        $iExtras[$iOptions[$key]] = $iOptions[$key + 1];
                    }
                }
            }
    
            if (MODULE_SHIPPING_USPS_REGULATIONS === 'True') {
                $iInfo = 
                    '<div id="iInfo">' . "\n" .
                    '  <div id="showInfo" class="ui-state-error" style="cursor:pointer; text-align:center;" onclick="$(\'#showInfo\').hide();$(\'#hideInfo, #Info\').show();">' . MODULE_SHIPPING_USPS_TEXT_INTL_SHOW . '</div>' . "\n" .
                    '  <div id="hideInfo" class="ui-state-error" style="cursor:pointer; text-align:center; display:none;" onclick="$(\'#hideInfo, #Info\').hide();$(\'#showInfo\').show();">' . MODULE_SHIPPING_USPS_TEXT_INTL_HIDE .'</div>' . "\n" .
                    '  <div id="Info" class="ui-state-highlight" style="display:none; padding:10px; max-height:200px; overflow:auto;">' .
                    '    <b>Prohibitions:</b><br>' . nl2br($uspsQuote['Package']['Prohibitions']) . '<br><br>' .
                    '    <b>Restrictions:</b><br>' . nl2br($uspsQuote['Package']['Restrictions']) . '<br><br>' .
                    '    <b>Observations:</b><br>' . nl2br($uspsQuote['Package']['Observations']) . '<br><br>' .
                    '    <b>CustomsForms:</b><br>' . nl2br($uspsQuote['Package']['CustomsForms']) . '<br><br>' .
                    '    <b>ExpressMail:</b><br>' . nl2br($uspsQuote['Package']['ExpressMail']) . '<br><br>' .
                    '    <b>AreasServed:</b><br>' . nl2br($uspsQuote['Package']['AreasServed']) . '<br><br>' .
                    '    <b>AdditionalRestrictions:</b><br>' . nl2br($uspsQuote['Package']['AdditionalRestrictions']) .
                    '  </div>' . "\n" .
                    '</div>';
            }
        }

        if ($this->is_us_shipment === true) {
            if (!isset($uspsQuote['Package']) || !is_array($uspsQuote['Package'])) {
                $PackageSize = 0;
            } else {
                $PackageSize = count($uspsQuote['Package']);
                // if object has no legitimate children, turn it into a firstborn:
                if (isset($uspsQuote['Package']['ZipDestination']) && !isset($uspsQuote['Package'][0]['Postage'])) {
                    $uspsQuote['Package'][] = $uspsQuote['Package'];
                    $PackageSize = 1;
                }
            }
        } else {
            $PackageSize = count($uspsQuote['Package']['Service']);
        }

        // display 1st occurance of First Class and skip others for the US - start counter
        $cnt_first = 0;

        // *** Customizations once per display ***

        // bof: example to block USPS Priority MailRM Small Flat Rate Box when anything from master_categories_id 12 or 15 are in the cart
        // change false to true to use
        if (false) {
            $chk_cart = 0;
            $chk_cart += $_SESSION['cart']->in_cart_check('master_categories_id','12');
            $chk_cart += $_SESSION['cart']->in_cart_check('master_categories_id','15');
        }
        // see below use of $chk_cart
        // eof: example to block USPS Priority MailRM Small Flat Rate Box when anything from master_categories_id 12 or 15 are in the cart

        for ($i = 0; $i < $PackageSize; $i++) {
            if (!empty($uspsQuote['Package'][$i]['Error'])) {
                continue;
            }
            $Services = [];
            $hiddenServices = [];
            $hiddenCost = 0;
            $handling = 0;
            $usps_insurance_charge = 0;

            $Package = ($this->is_us_shipment) ? $uspsQuote['Package'][$i]['Postage'] : $uspsQuote['Package']['Service'][$i];

            // Domestic first
            if ($this->is_us_shipment) {
                if (!empty($Package['SpecialServices']['SpecialService'])) {
                    // if object has no legitimate children, turn it into a firstborn:
                    if (isset($Package['SpecialServices']['SpecialService']['ServiceName']) && !isset($Package['SpecialServices']['SpecialService'][0])) {
                        $Package['SpecialServices']['SpecialService'] = [
                            $Package['SpecialServices']['SpecialService']
                        ];
                    }

                    foreach ($Package['SpecialServices']['SpecialService'] as $key => $val) {
                        // translate friendly names for Insurance Restricted Delivery 177, 178, 179, since USPS rebranded to remove all sense of explanations
                        if ($val['ServiceName'] === 'Insurance Restricted Delivery') {
                            if ($val['ServiceID'] === '178') {
                                $val['ServiceName'] = 'Insurance Restricted Delivery (Priority Mail Express)';
                            } elseif ($val['ServiceID'] === '179') {
                                $val['ServiceName'] = 'Insurance Restricted Delivery (Priority Mail)';
                            }
                        }
                        // translate friendly names for insurance 100, 101, 125, since USPS rebranded to remove all sense of explanations
                        if ($val['ServiceName'] === 'Insurance') {
                            if ($val['ServiceID'] === '125') {
                                $val['ServiceName'] = 'Priority Mail Insurance';
                            } elseif ($val['ServiceID'] === '101') {
                                $val['ServiceName'] = 'Priority Mail Express Insurance';
                            }
                        }

                        $val['ServiceName'] = $this->clean_usps_marks($val['ServiceName']);

                        if (!empty($dExtras[$val['ServiceName']]) && ((MODULE_SHIPPING_USPS_RATE_TYPE === 'Online' && !empty($val['AvailableOnline']) && strtoupper($val['AvailableOnline']) === 'TRUE') || (MODULE_SHIPPING_USPS_RATE_TYPE === 'Retail' && !empty($val['Available']) && strtoupper($val['Available']) === 'TRUE'))) {
                            $val['ServiceAdmin'] = $this->clean_usps_marks($dExtras[$val['ServiceName']]);
                            $Services[] = $val;
                        }
                    }
                }

                $cost = (MODULE_SHIPPING_USPS_RATE_TYPE === 'Online' && isset($Package['CommercialRate'])) ? $Package['CommercialRate'] : $Package['Rate'];
                $type = $this->clean_usps_marks($Package['MailService']);
                // methods shipping zone
                $usps_shipping_methods_zone = $uspsQuote['Package'][$i]['Zone'];
            } else {
                // International
                if (isset($Package['ExtraServices']['ExtraService']) && is_array($Package['ExtraServices']['ExtraService'])) {
                    // if object has no legitimate children, turn it into a firstborn:
                    if (isset($Package['ExtraServices']['ExtraService']['ServiceName']) && !isset($Package['ExtraServices']['ExtraService'][0])) {
                        $Package['ExtraServices']['ExtraService'] = [
                            $Package['ExtraServices']['ExtraService']
                        ];
                    }

                    foreach ($Package['ExtraServices']['ExtraService'] as $key => $val) {
                        $val['ServiceName'] = $this->clean_usps_marks($val['ServiceName']);
                        if (isset($iExtras[$val['ServiceName']]) && !empty($iExtras[$val['ServiceName']]) && ((MODULE_SHIPPING_USPS_RATE_TYPE === 'Online' && strtoupper($val['OnlineAvailable']) === 'TRUE') || (MODULE_SHIPPING_USPS_RATE_TYPE === 'Retail' && strtoupper($val['Available']) === 'TRUE'))) {
                            $val['ServiceAdmin'] = $this->clean_usps_marks($iExtras[$val['ServiceName']]);
                            $Services[] = $val;
                        }
                    }
                }
                $cost = (MODULE_SHIPPING_USPS_RATE_TYPE === 'Online' && !empty($Package['CommercialPostage'])) ? $Package['CommercialPostage'] : $Package['Postage'];
                $type = $this->clean_usps_marks($Package['SvcDescription']);
            }
            if ($cost == 0) {
                continue;
            }

            // -----
            // USPS, as of 7/10/2022, is now returning the 'Priority MailRM Flat Rate Envelope' as
            // 'Priority Mail Flat RateRM Envelope'.  If that value's found, which they'll most likely
            // update in yet-another update, change it to the value it's expected to be.
            //
            if ($type === 'Priority Mail Flat RateRM Envelope') {
                $type = 'Priority MailRM Flat Rate Envelope';
            }
            $type_rebuilt = $type;

            // bof: example to block USPS Priority MailRM Small Flat Rate Box when anything from master_categories_id 12 or 15 are in the cart
            // see above for $chk_cart settings
            // change false to true to use
            if (false) {
                if ($chk_cart > 0 && $type == 'Priority MailRM Small Flat Rate Box') {
                    continue;
                }
            }
            // eof: example to block USPS Priority MailRM Small Flat Rate Box when anything from master_categories_id 12 or 15 are in the cart

            // Detect which First-Class type has been quoted, since USPS doesn't consistently return the type in the name of the service
            if (!isset($Package['FirstClassMailType']) || $Package['FirstClassMailType'] === '') {
                if (isset($uspsQuote['Package'][$i]) && isset($uspsQuote['Package'][$i]['FirstClassMailType']) && $uspsQuote['Package'][$i]['FirstClassMailType'] !== '') {
                    $Package['FirstClassMailType'] = $uspsQuote['Package'][$i]['FirstClassMailType']; // LETTER or FLAT or PARCEL
                }
            }

            // init vars used later
            $minweight = $maxweight = $handling = 0;

            // Build a match pattern for regex compare later against selected allowed services
            $Package['lookupRegex'] = preg_quote($type) . '(?:RM|TM)?$';
            if (isset($Package['FirstClassMailType'])) {
                if (strcasecmp($Package['FirstClassMailType'], 'LETTER') === 0) {
                    $Package['lookupRegex'] = preg_replace('#Mail(?:RM|TM)?#', 'Mail(?:RM|TM)?(?: Stamped )?.*', preg_quote($type)) . (!$this->is_us_shipment ? '(GXG|International)?.*' : '') . $Package['FirstClassMailType'];
                } elseif (strcasecmp($Package['FirstClassMailType'], 'PARCEL') === 0) {
                     $Package['lookupRegex'] = preg_replace('#Mail(?:RM|TM)?#', 'Mail.*', preg_quote($type)) . (!$this->is_us_shipment ? '(GXG|International)?.*' : '') . $Package['FirstClassMailType'];
                } elseif (strcasecmp($Package['FirstClassMailType'], 'FLAT') === 0) {
                    $Package['lookupRegex'] = preg_replace('#Mail(?:RM|TM)?#', 'Mail.*', preg_quote($type)) . (!$this->is_us_shipment ? '(GXG|International)?.*' : '') . 'Envelope';
                }
            }
            $Package['lookupRegex'] = str_replace('Stamped Letter', 'Letter', $Package['lookupRegex']);
            $Package['lookupRegex'] = str_replace('LetterLETTER', 'Letter', $Package['lookupRegex']);
            $Package['lookupRegex'] = str_replace('ParcelEnvelope', 'Envelope', $Package['lookupRegex']);
            $Package['lookupRegex'] = str_replace('EnvelopeEnvelope', 'Envelope', $Package['lookupRegex']);
            $Package['lookupRegex'] = str_replace('ParcelPARCEL', 'Parcel', $Package['lookupRegex']);

            // Certain methods cannot ship if declared value is over $400, so we "continue" which skips the current $type and proceeds with the next one in the loop:
            if (isset($this->types_to_skip_over_certain_value[$type]) && $_SESSION['cart']->total > $this->types_to_skip_over_certain_value[$type]) {
                continue;
            }

            // process weight/handling settings from admin checkboxes
            foreach ($this->typeCheckboxesSelected as $key => $val) {
                if (is_numeric($val) || $val === '') {
                    continue;
                }

                if ($val == $type || preg_match('#' . $Package['lookupRegex'] . '#i', $val) ) {
                    if (strpos($val, 'International') && !strpos($type, 'International')) {
                        continue;
                    }
                    if (strpos($val, 'GXG') && !strpos($type, 'GXG')) {
                        continue;
                    }

                    $minweight = $this->typeCheckboxesSelected[$key + 1];
                    $maxweight = $this->typeCheckboxesSelected[$key + 2];
                    $handling = $this->typeCheckboxesSelected[$key + 3];

                    if ($val != $type && preg_match('#' . $Package['lookupRegex'] . '#i', $val) ) {
                        $type_rebuilt = $val;
                    }
                    break;
                }
            }

            $rate_type = (MODULE_SHIPPING_USPS_RATE_TYPE === 'Online') ? 'PriceOnline' : 'Price';
            foreach ($Services as $key => $val) {
                if ($Services[$key]['ServiceAdmin'] === 'Y') {
                    $hiddenServices[] = array(
                        $Services[$key]['ServiceName'] . ' [' . $Services[$key]['ServiceID'] . ']' => $Services[$key][$rate_type]
                    );
                }
            }

            // prepare costs associated with selected additional services
            $hidden_costs_breakdown = '';
            foreach ($hiddenServices as $key => $val) {
                foreach ($hiddenServices[$key] as $key1 => $val1) {
                    // add the cost to the accumulator
                    $hiddenCost += $val1;

                    // now check for insurance-specific codes, in order to augment the insurance counter

                    // extract the ServiceID, so we can test for specific insurance types
                    preg_match('/\[([0-9]{1,3})\]/', $key1, $matches);
                    $serviceID = $matches[1];
                    $hidden_costs_breakdown .= ($this->is_us_shipment ? ' SpecialServices: ' : ' ExtraServices: ') . $key1 . ' Amount: ' . number_format($val1, 2) . "\n";
                    // Test for Insurance type being returned  100=(General) Insurance, 125=Priority Mail, 101=Priority Mail Express

                    // Domestic Insurance 100, 101, 125 International 1
                    $insurance_test_flag = false;
                    if (stripos($key1, 'Insurance') !== false) {
                        // Domestic
                        if ($order->delivery['country']['id'] === SHIPPING_ORIGIN_COUNTRY || $this->is_us_shipment === true) {
                            if (strpos($servicesSelectedDomestic, $serviceID) !== false) {
                                if (strpos($type, 'Priority Mail') !== false) {
                                    if (strpos($type, 'Express') !== false) {
                                        if ($serviceID == 101) {
                                            $insurance_test_flag = true;
                                        }
                                    } else {
                                        if ($serviceID == 125) {
                                            $insurance_test_flag = true;
                                        }
                                    }
                            } else {
                                if ($serviceID == 100) {
                                    $insurance_test_flag = true;
                                }
                            }
                        }
                        } else { // international
                            if ($serviceID == 1 && strpos($servicesSelectedIntl, $serviceID) !== false) {
                                $insurance_test_flag = true;
                            }
                        }
                        if ($insurance_test_flag === true) {
                            $usps_insurance_charge = $val1;
                        }
                    }
                }
            }

            // set module-specific handling fee
            if ($order->delivery['country']['id'] === SHIPPING_ORIGIN_COUNTRY || $this->is_us_shipment === true) {
                // domestic/national
                $usps_handling_fee = MODULE_SHIPPING_USPS_HANDLING;
            } else {
                // international
                $usps_handling_fee = MODULE_SHIPPING_USPS_HANDLING_INT;
            }

            // -----
            // Give an observer the opportunity to modify the overall USPS handling fee for the order.
            //
            $this->notify('NOTIFY_SHIPPING_USPS_AFTER_HANDLING', [], $order, $usps_shipping_weight, $shipping_num_boxes, $usps_handling_fee);

            // COST
            // clean out invalid characters
            $cost = preg_replace('/[^0-9.]/', '',  $cost);

            // add handling for shipping method costs for extra services applied
            $cost_original = $cost;
            $cost = ($cost + $handling + $hiddenCost) * $shipping_num_boxes;
            // add handling fee per Box or per Order
            $cost += (MODULE_SHIPPING_USPS_HANDLING_METHOD === 'Box') ? ($usps_handling_fee * $shipping_num_boxes) : $usps_handling_fee;

            // set the output title display name back to correct format
            $title = str_replace(
                [
                    'RM',
                    'TM',
                    '**'
                ],
                [
                    '&reg;',
                    '&trade;',
                    ''
                ],
                $type_rebuilt
            );

            // process customization of transit times in quotes
            if (strpos(MODULE_SHIPPING_USPS_OPTIONS, 'Display transit time') !== false) {
                if ($order->delivery['country']['id'] === SHIPPING_ORIGIN_COUNTRY || $this->is_us_shipment === true) {
                    if ($this->transitTimeCalculationMode !== 'OLD') {
                        $this->parseDomesticTransitTimeResults($Package, $type_rebuilt);
                    }
                } else {
                    $this->parseIntlTransitTimeResults($Package, $type_rebuilt);
                }
            }

            // Add transit time -- if the transit time feature is enabled, then the transittime variable will not be blank, so this adds it. If it's disabled, will be blank, so adding here will have no negative effect.
            $title .= (isset($this->transittime[$type_rebuilt])) ? $this->transittime[$type_rebuilt] : '';

            // build USPS output for valid methods based on selected and weight limits
            if ($usps_shipping_weight <= $maxweight && $usps_shipping_weight > $minweight) {
                $found = true;
                if ($method != $type && $method != $type_rebuilt) {
                    if ($method !== '') {
                        continue;
                    }
                    $found = false;
                    foreach ($this->typeCheckboxesSelected as $key => $val) {
                        if (is_numeric($val) || $val === '') {
                            continue;
                        }
                        if ($val == $type || preg_match('#' . $Package['lookupRegex'] . '#i', $val)) {
                            $found = true;
                            break;
                        }
                    }
                }
                if ($found === false) {
                    continue;
                }

                // display 1st occurance of First Class and skip others for the US
                if (preg_match('#First\-Class.*(?!GXG|International)#i', $type)) {
                    $cnt_first++;
                }

                // USPS customize for filtering displayed methods and costs
                if ($this->is_us_shipment === true && MODULE_SHIPPING_USPS_FIRST_CLASS_FILTER_US === 'True' && stripos($type, 'First-Class') !== false && $cnt_first > 1) {
                    continue;
                }

                // -----
                // Give an observer the opportunity to disallow this shipping method or to modify the method's title/cost/insurance cost.
                //
                $allow_type = true;
                $saved_title = $title;
                $saved_cost = $cost;
                $saved_insurance_cost = $usps_insurance_charge;
                $this->notify('NOTIFY_USPS_UPDATE_OR_DISALLOW_TYPE', $type_rebuilt, $allow_type, $title, $cost, $usps_insurance_charge);

                if ($allow_type === false) {
                    $this->uspsDebug("Shipping method '$type_rebuilt' disallowed by observer.");
                    continue;
                }
                if ($saved_title !== $title || $saved_cost !== $cost || $saved_insurance_cost !== $usps_insurance_charge) {
                    $this->uspsDebug("Shipping method '$type_rebuilt', parameters changed by observer:\n\tTitle: $saved_title vs. $title.\n\tCost: $saved_cost vs. $cost.\n\tInsurance: $saved_insurance_cost vs. $usps_insurange_charge.");
                }
                $methods[] = [
                    'id' => $type_rebuilt,
                    'title' => $title,
                    'cost' => $cost,
                    'insurance' => $usps_insurance_charge,
                ];
                $usps_shipping_quotes .=
                    $title . "\n" .
                    ' Original cost: ' .
                    number_format($cost_original, 2) .
                    ($hiddenCost ? ($this->is_us_shipment ? ' SpecialServices: ' : ' ExtraServices: ') .
                    number_format($hiddenCost, 2) : '') .
                    ' Total Cost: ' . number_format($cost, 2) .
                    ($usps_insurance_charge ? ' - $usps_insurance_charge: ' . number_format($usps_insurance_charge, 2) : '') . "\n";
                $usps_shipping_quotes .= $hidden_costs_breakdown . "\n";
            }
        }  // end for $i to $PackageSize

        if (count($methods) === 0) {
            return false;
        }

        // add shipping quotes to logs
        $usps_shipping_quotes = str_replace(
            [
                '&amp;reg;',
                '&amp;trade;',
            ],
            [
                'RM',
                'TM'
            ],
            htmlspecialchars($usps_shipping_quotes)
        );
        $usps_shipping_quotes = '==================================' . "\n\n" . 'Rate Quotes:' . "\n" . $usps_shipping_quotes . "\n\n";
        $this->uspsDebug($usps_shipping_quotes);

        // sort results
        if (MODULE_SHIPPING_USPS_QUOTE_SORT !== 'Unsorted') {
            if (count($methods) !== 0) {
                if (strpos(MODULE_SHIPPING_USPS_QUOTE_SORT, 'Price') === 0) {
                    foreach ($methods as $c => $key) {
                        $sort_cost[] = $key['cost'];
                        $sort_id[] = $key['id'];
                    }
                    array_multisort($sort_cost, (MODULE_SHIPPING_USPS_QUOTE_SORT === 'Price-LowToHigh' ? SORT_ASC : SORT_DESC), $sort_id, SORT_ASC, $methods);
                } else {
                    foreach ($methods as $c => $key) {
                        $sort_key[] = $key['title'];
                        $sort_id[] = $key['id'];
                    }
                    array_multisort($sort_key, (MODULE_SHIPPING_USPS_QUOTE_SORT === 'Alphabetical' ? SORT_ASC : SORT_DESC), $sort_id, SORT_ASC, $methods);
                }
            }
        }

        // Show box weight if enabled
        $show_box_weight = '';
        if (strpos(MODULE_SHIPPING_USPS_OPTIONS, 'Display weight') !== false) {
            switch (SHIPPING_BOX_WEIGHT_DISPLAY) {
                case '0':
                    $show_box_weight = '';
                    break;
                case '1':
                    $show_box_weight = ' (' . $shipping_num_boxes . ' ' . TEXT_SHIPPING_BOXES . ')';
                    break;
                case '2':
                    $show_box_weight = ' (' . number_format($usps_shipping_weight * $shipping_num_boxes, 2) . TEXT_SHIPPING_WEIGHT . ')';
                    break;
                default:
                    $show_box_weight = ' (' . $shipping_num_boxes . ' ' . TEXT_SHIPPING_BOXES . ')  (' . $this->pounds . ' lbs, ' . $this->ounces . ' oz' . ')';
                    break;
            }
        }
        $this->quotes = [
            'id' => $this->code,
            'module' => $this->title . $show_box_weight,
            'methods' => $methods,
            'tax' => ($this->tax_class > 0) ? zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) : null,
        ];

        // add icon/message, if any
        if ($this->icon !== '') {
            $this->quotes['icon'] = zen_image($this->icon, $this->title);
        }
        if ($iInfo !== '') {
            $this->quotes['icon'] .= '<br>' . $iInfo;
        }

        $this->notify('NOTIFY_SHIPPING_USPS_QUOTES_READY_TO_RETURN');
        return $this->quotes;
    }

    /**
     * check status of module
     *
     * @return boolean
     */
    public function check()
    {
        global $db;

        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_SHIPPING_USPS_STATUS' LIMIT 1");
            $this->_check = $check_query->RecordCount();
        }
        global $sniffer;
        $results = $sniffer->field_type(TABLE_ORDERS, 'shipping_method', 'varchar(255)', true);
        if ($results !== true) {
            $sql = "ALTER TABLE " . TABLE_ORDERS . " MODIFY shipping_method varchar(255) NOT NULL DEFAULT ''";
            $db->Execute($sql);
        }

        $this->notify('NOTIFY_SHIPPING_USPS_CHECK');
        return $this->_check;
    }

    /**
     * Install this module
     */
    public function install()
    {
        global $db;
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('USPS Version Date', 'MODULE_SHIPPING_USPS_VERSION', '" . self::USPS_CURRENT_VERSION . "', 'You have installed:', 6, 0, 'zen_cfg_select_option([\'" . self::USPS_CURRENT_VERSION . "\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Enable USPS Shipping', 'MODULE_SHIPPING_USPS_STATUS', 'True', 'Do you want to offer USPS shipping?', 6, 0, 'zen_cfg_select_option([\'True\', \'False\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Full Name or Short Name', 'MODULE_SHIPPING_USPS_TITLE_SIZE', 'Long', 'Do you want to use a Long or Short name for USPS shipping?', 6, 0, 'zen_cfg_select_option([\'Long\', \'Short\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)
             VALUES
                ('Shipping Zone', 'MODULE_SHIPPING_USPS_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', 6, 0, 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('Sort Order', 'MODULE_SHIPPING_USPS_SORT_ORDER', '0', 'Sort order of display.', 6, 0, now())"
        );

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('Enter the USPS Web Tools User ID', 'MODULE_SHIPPING_USPS_USERID', 'NONE', 'Enter the USPS USERID assigned to you for Rate Quotes/ShippingAPI.', 6, 0, now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Which server to use', 'MODULE_SHIPPING_USPS_SERVER', 'production', 'An account at USPS is needed to use the Production server', 6, 0, 'zen_cfg_select_option([\'test\', \'production\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('All Packages are Machinable?', 'MODULE_SHIPPING_USPS_MACHINABLE', 'False', 'Are all products shipped machinable based on C700 Package Services 2.0 Nonmachinable PARCEL POST USPS Rules and Regulations?<br><br><strong>Note: Nonmachinable packages will usually result in a higher Parcel Post Rate Charge.<br><br>Packages 35lbs or more, or less than 6 ounces (.375), will be overridden and set to False</strong>', 6, 0, 'zen_cfg_select_option([\'True\', \'False\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Quote Sort Order', 'MODULE_SHIPPING_USPS_QUOTE_SORT', 'Price-LowToHigh', 'Sorts the returned quotes using the service name Alphanumerically or by Price. Unsorted will give the order provided by USPS.', 6, 0, 'zen_cfg_select_option([\'Unsorted\',\'Alphabetical\', \'Price-LowToHigh\', \'Price-HighToLow\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Decimal Settings', 'MODULE_SHIPPING_USPS_DECIMALS', '3', 'Decimal Setting can be 1, 2 or 3. Sometimes International requires 2 decimals, based on Tare Rates or Product weights. Do you want to use 1, 2 or 3 decimals?', 6, 0, 'zen_cfg_select_option([\'1\', \'2\', \'3\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)
             VALUES
                ('Tax Class', 'MODULE_SHIPPING_USPS_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', 6, 0, 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Tax Basis', 'MODULE_SHIPPING_USPS_TAX_BASIS', 'Shipping', 'On what basis is Shipping Tax calculated. Options are<br>Shipping - Based on customers Shipping Address<br>Billing Based on customers Billing address<br>Store - Based on Store address if Billing/Shipping Zone equals Store zone', 6, 0, 'zen_cfg_select_option([\'Shipping\', \'Billing\', \'Store\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('USPS Options', 'MODULE_SHIPPING_USPS_OPTIONS', '--none--', 'Select from the following the USPS options.<br>note: this adds a considerable delay in obtaining quotes.', '6', '16', 'zen_cfg_select_multioption([\'Display weight\', \'Display transit time\'], ',  now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('USPS Domestic Transit Time Calculation Mode', 'MODULE_SHIPPING_USPS_TRANSIT_TIME_CALCULATION_MODE', 'NEW', 'Select from the following the USPS options.<br>note: NEW and OLD will add additional time to quotes. CUSTOM allows your custom shipping days.', '6', '16', 'zen_cfg_select_option([\'CUSTOM\', \'NEW\', \'OLD\'], ',  now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Debug Mode', 'MODULE_SHIPPING_USPS_DEBUG_MODE', 'Off', 'Would you like to enable debug mode?  If set to <em>Logs</em>, a file will be written to the store\'s /logs directory on each USPS request.', 6, 0, 'zen_cfg_select_option([\'Off\', \'Logs\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('Handling Fee - US', 'MODULE_SHIPPING_USPS_HANDLING', '0', 'National Handling fee for this shipping method.', 6, 0, now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('Handling Fee - International', 'MODULE_SHIPPING_USPS_HANDLING_INT', '0', 'International Handling fee for this shipping method.', 6, 0, now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Handling Per Order or Per Box', 'MODULE_SHIPPING_USPS_HANDLING_METHOD', 'Box', 'Do you want to charge Handling Fee Per Order or Per Box?', 6, 0, 'zen_cfg_select_option([\'Order\', \'Box\'], ', now())"
        );

        /*
        Small Flat Rate Box 8-5/8" x 5-3/8" x 1-5/8"
        MODULE_SHIPPING_USPS_LENGTH 8.625
        MODULE_SHIPPING_USPS_WIDTH  5.375
        MODULE_SHIPPING_USPS_HEIGHT 1.625
        
        2021-05-05 K11a, now using the same defaults for international
        */
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('USPS Domestic minimum Length', 'MODULE_SHIPPING_USPS_LENGTH', '8.625', 'The Minimum Length, Width and Height are used to determine shipping methods available for Domestic Shipping.<br>While dimensions are not supported at this time, the Minimums are sent to USPS for obtaining Rate Quotes.<br>In most cases, these Minimums should never have to be changed.<br><br><strong>Enter the Domestic</strong><br>Minimum Length - default 8.625', 6, 0, now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('USPS minimum Width', 'MODULE_SHIPPING_USPS_WIDTH', '5.375', 'Enter the Minimum Width - default 5.375', 6, 0, now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('USPS minimum Height', 'MODULE_SHIPPING_USPS_HEIGHT', '1.625', 'Enter the Minimum Height - default 1.625', 6, 0, now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('USPS International minimum Length', 'MODULE_SHIPPING_USPS_LENGTH_INTL', '8.625', 'The Minimum Length, Width and Height are used to determine shipping methods available for International Shipping.<br>While dimensions are not supported at this time, the Minimums are sent to USPS for obtaining Rate Quotes.<br>In most cases, these Minimums should never have to be changed.<br><br><strong>Enter the International</strong><br>Minimum Length - default 8.625', 6, 0, now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('USPS minimum Width', 'MODULE_SHIPPING_USPS_WIDTH_INTL', '5.375', 'Enter the Minimum Width - default 5.375', 6, 0, now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES
                ('USPS minimum Height', 'MODULE_SHIPPING_USPS_HEIGHT_INTL', '1.625', 'Enter the Minimum Height - default 1.625', 6, 0, now())"
        );

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Enable USPS First-Class filter for US shipping', 'MODULE_SHIPPING_USPS_FIRST_CLASS_FILTER_US', 'True', 'Do you want to enable the US First-Class filter to display only 1 First-Class shipping rate?', 6, 0, 'zen_cfg_select_option([\'True\', \'False\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Shipping Methods (Domestic and International)',  'MODULE_SHIPPING_USPS_TYPES',  '0, .21875, 0.00, 0, .8125, 0.00, 0, .8125, 0.00, 0, .9375, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, 70, 0.00, 0, .21875, 0.00, 0, 4, 0.00, 0, 4, 0.00, 0, 66, 0.00, 0, 4, 0.00, 0, 4, 0.00, 0, 20, 0.00, 0, 20, 0.00, 0, 66, 0.00, 0, 4, 0.00, 0, 70, 0.00, 0, 70, 0.00', '<b><u>Checkbox:</u></b> Select the services to be offered<br><b><u>Minimum Weight (lbs)</u></b>first input field<br><b><u>Maximum Weight (lbs):</u></b>second input field<br><br>USPS returns methods based on cart weights.  These settings will allow further control (particularly helpful for flat rate methods) but will not override USPS limits', 6, 0, 'zen_cfg_usps_services([\'First-Class Mail Letter\', \'First-Class Mail Large Envelope\', \'First-Class Package Service - RetailTM\', \'First-ClassTM Package Service\', \'Media Mail Parcel\', \'USPS Retail GroundRM\', \'Priority MailRM\', \'Priority MailRM Flat Rate Envelope\', \'Priority MailRM Legal Flat Rate Envelope\', \'Priority MailRM Padded Flat Rate Envelope\', \'Priority MailRM Small Flat Rate Box\', \'Priority MailRM Medium Flat Rate Box\', \'Priority MailRM Large Flat Rate Box\', \'Priority Mail ExpressRM\', \'Priority Mail ExpressRM Flat Rate Envelope\', \'Priority Mail ExpressRM Legal Flat Rate Envelope\', \'First-Class MailRM International Letter\', \'First-Class MailRM International Large Envelope\', \'First-Class Package International ServiceTM\', \'Priority Mail InternationalRM\', \'Priority Mail InternationalRM Flat Rate Envelope\', \'Priority Mail InternationalRM Small Flat Rate Box\', \'Priority Mail InternationalRM Medium Flat Rate Box\', \'Priority Mail InternationalRM Large Flat Rate Box\', \'Priority Mail Express InternationalTM\', \'Priority Mail Express InternationalTM Flat Rate Envelope\', \'USPS GXGTM Envelopes\', \'Global Express GuaranteedRM (GXG)\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Extra Services (Domestic)', 'MODULE_SHIPPING_USPS_DMST_SERVICES', 'Certified MailRM, N, USPS TrackingTM Electronic, N, USPS TrackingTM, N, Insurance, N, Priority Mail Express Insurance, N, Priority Mail Insurance, N, Adult Signature Restricted Delivery, N, Adult Signature Required, N, Registered MailTM, N, Collect on Delivery, N, Return Receipt, N, Certificate of Mailing (Form 3665), N, Certificate of Mailing (Form 3817), N, Signature ConfirmationTM Electronic, N, Signature ConfirmationTM, N, Priority Mail Express 1030 AM Delivery, N, Certified MailRM Restricted Delivery, N, Certified MailRM Adult Signature Required, N, Certified MailRM Adult Signature Restricted Delivery, N, Signature ConfirmationTM Restricted Delivery, N, Signature ConfirmationTM Electronic Restricted Delivery, N, Collect on Delivery Restricted Delivery, N, Registered MailTM Restricted Delivery, N, Insurance Restricted Delivery, N, Insurance Restricted Delivery (Priority Mail Express), N, Insurance Restricted Delivery (Priority Mail), N', 'Included in postage rates.  Not shown to the customer.<br>WARNING: Some services cannot work with other services.', 6, 0, 'zen_cfg_usps_extraservices([\'Certified MailRM\', \'USPS TrackingTM Electronic\', \'USPS TrackingTM\', \'Insurance\', \'Priority Mail Express Insurance\', \'Priority Mail Insurance\', \'Adult Signature Restricted Delivery\', \'Adult Signature Required\', \'Registered MailTM\', \'Collect on Delivery\', \'Return Receipt\', \'Certificate of Mailing (Form 3665)\', \'Certificate of Mailing (Form 3817)\', \'Signature ConfirmationTM Electronic\', \'Signature ConfirmationTM\', \'Priority Mail Express 1030 AM Delivery\', \'Certified MailRM Restricted Delivery\', \'Certified MailRM Adult Signature Required\', \'Certified MailRM Adult Signature Restricted Delivery\', \'Signature ConfirmationTM Restricted Delivery\', \'Signature ConfirmationTM Electronic Restricted Delivery\', \'Collect on Delivery Restricted Delivery\', \'Registered MailTM Restricted Delivery\', \'Insurance Restricted Delivery\', \'Insurance Restricted Delivery (Priority Mail Express)\', \'Insurance Restricted Delivery (Priority Mail)\'], ', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Extra Services (International)', 'MODULE_SHIPPING_USPS_INTL_SERVICES', 'Registered Mail, N, Insurance, N, Return Receipt, N, Electronic USPS Delivery Confirmation International, N, Certificate of Mailing, N', 'Included in postage rates.  Not shown to the customer.<br>WARNING: Some services cannot work with other services.', 6, 0, 'zen_cfg_usps_extraservices([\'Registered Mail\', \'Insurance\', \'Return Receipt\', \'Electronic USPS Delivery Confirmation International\', \'Certificate of Mailing\'], ', now())"
        );

        // Special Services prices and availability will not be returned when Service = ALL or ONLINE
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
             VALUES
                ('Retail pricing or Online pricing?', 'MODULE_SHIPPING_USPS_RATE_TYPE', 'Online', 'Rates will be returned ONLY for methods available in this pricing type.  Applies to prices <u>and</u> add on services', 6, 0, 'zen_cfg_select_option([\'Retail\', \'Online\'], ', now())"
        );
        $this->notify('NOTIFY_SHIPPING_USPS_INSTALLED');
    }

    /**
     * For removing this module's settings
     */
    public function remove()
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE\_SHIPPING\_USPS\_%' ");
        $this->notify('NOTIFY_SHIPPING_USPS_UNINSTALLED');
    }

    /**
     * Build array of keys used for installing/managing this module
     *
     * @return array
     */
    public function keys()
    {
        $keys_list = [
            'MODULE_SHIPPING_USPS_VERSION',
            'MODULE_SHIPPING_USPS_STATUS',
            'MODULE_SHIPPING_USPS_TITLE_SIZE',
            'MODULE_SHIPPING_USPS_USERID',
            'MODULE_SHIPPING_USPS_SERVER',
            'MODULE_SHIPPING_USPS_QUOTE_SORT',
            'MODULE_SHIPPING_USPS_HANDLING',
            'MODULE_SHIPPING_USPS_HANDLING_INT',
            'MODULE_SHIPPING_USPS_HANDLING_METHOD',
            'MODULE_SHIPPING_USPS_DECIMALS',
            'MODULE_SHIPPING_USPS_TAX_CLASS',
            'MODULE_SHIPPING_USPS_TAX_BASIS',
            'MODULE_SHIPPING_USPS_ZONE',
            'MODULE_SHIPPING_USPS_SORT_ORDER',
            'MODULE_SHIPPING_USPS_MACHINABLE',
            'MODULE_SHIPPING_USPS_OPTIONS', 
            'MODULE_SHIPPING_USPS_TRANSIT_TIME_CALCULATION_MODE',
            'MODULE_SHIPPING_USPS_LENGTH',
            'MODULE_SHIPPING_USPS_WIDTH',
            'MODULE_SHIPPING_USPS_HEIGHT',
            'MODULE_SHIPPING_USPS_LENGTH_INTL',
            'MODULE_SHIPPING_USPS_WIDTH_INTL',
            'MODULE_SHIPPING_USPS_HEIGHT_INTL',
            'MODULE_SHIPPING_USPS_FIRST_CLASS_FILTER_US',
            'MODULE_SHIPPING_USPS_TYPES',
            'MODULE_SHIPPING_USPS_DMST_SERVICES',
            'MODULE_SHIPPING_USPS_INTL_SERVICES',
            'MODULE_SHIPPING_USPS_RATE_TYPE',
            'MODULE_SHIPPING_USPS_DEBUG_MODE',
        ];
        $this->notify('NOTIFY_SHIPPING_USPS_KEYS', '', $keys_list);
        return $keys_list;
    }

    /**
     * Get actual quote from USPS
     *
     * @return array of results or boolean false if no results
     */
    protected function _getQuote()
    {
        global $order, $shipping_weight, $currencies;

        $package_id = 'USPS DOMESTIC RETURNED: ' . "\n";

        // -----
        // Force GroundOnly results in USPS Retail Ground only being offered.  See the shipping-module's
        // language file for additional information.
        //
        $usps_groundonly = '';
        if (MODULE_SHIPPING_USPS_GROUNDONLY === 'force' || (MODULE_SHIPPING_USPS_GROUNDONLY === 'true' && $_SESSION['cart']->in_cart_check('products_groundonly', '1'))) {
            $usps_groundonly = '<Content><ContentType>HAZMAT</ContentType></Content><GroundOnly>true</GroundOnly>';
        }

        // -----
        // Force Fragile.  If the store's indicated that some of its products are fragile, check
        // to see if any fragile products are in the cart, setting that indication in the shipping
        // request.  Fragile rates will be returned.  The store can also indicate that *all* products
        // are fragile.
        //
        $usps_fragile = '';
        if (MODULE_SHIPPING_USPS_FRAGILE === 'force' || (MODULE_SHIPPING_USPS_FRAGILE === 'true' && $_SESSION['cart']->in_cart_check('products_fragile', '1'))) {
            $usps_fragile = '<Content><ContentType>Fragile</ContentType></Content>';
        }

        if ((int)SHIPPING_ORIGIN_ZIP === 0) {
            // no quotes obtained no 5 digit zip code origin set
            return [
                'module' => $this->title,
                'error' => MODULE_SHIPPING_USPS_TEXT_ERROR . ((MODULE_SHIPPING_USPS_SERVER == 'test') ? MODULE_SHIPPING_USPS_TEXT_TEST_MODE_NOTICE : '')
            ];
        }

        $transreq = [];

        //@TODO
        // reduce order value by products not shipped
        // should this be an option or automatic?
        // International uses $max_usps_allowed_price which is total/boxes not correct for insurance quotes?

        // -----
        // If the order's tax-value isn't set (like when a quote is requested from
        // the shipping-estimator), set that value to 0 to prevent follow-on PHP
        // notices from this module's quote processing.
        //
        $this->orders_tax = (!isset($order->info['tax'])) ? 0 : $order->info['tax'];

        // reduce order value for virtual, downloads and Gift Certificates
        $this->shipment_value = ($order->info['subtotal'] > 0) ? ($order->info['subtotal'] + $this->orders_tax) : $_SESSION['cart']->total;

        global $uninsurable_value;
        $this->uninsurable_value = (isset($uninsurable_value)) ? (float)$uninsurable_value : 0;
        $this->insurable_value = $this->shipment_value - $this->uninsurable_value;

        // -----
        // Log, if enabled, the base USPS configuration for this quote request.
        //
        $this->quoteLogConfiguration();

        // US Domestic destinations
        if ($order->delivery['country']['id'] === SHIPPING_ORIGIN_COUNTRY || $this->is_us_shipment === true) {
            // build special services for domestic
            // Some Special Services cannot work with others
            $special_services_domestic = $this->special_services(); // original

            // -----
            // If the delivery postcode isn't set (like during the shipping-estimator), set it to
            // an empty string to cause 'no quote' to be returned.
            //
            if (!isset($order->delivery['postcode'])) {
                $order->delivery['postcode'] = '';
            }
            $ZipDestination = substr(trim($order->delivery['postcode']), 0, 5);
            if ($ZipDestination === '') {
                return -1;
            }
            $request =
                '<RateV4Request USERID="' . MODULE_SHIPPING_USPS_USERID . '">' .
                    '<Revision>2</Revision>';
            $package_count = 0;
            $ship_date = $this->zen_usps_shipdate();

            foreach ($this->typeCheckboxesSelected as $requested_type) {
                if (is_numeric($requested_type) || preg_match('#(GXG|International)#i' , $requested_type)) {
                    continue;
                }
                $FirstClassMailType = '';
                $Container = 'VARIABLE';
                if (stripos($requested_type, 'First-Class') !== false) {
                    // disable request for all First Class at 13oz. - First-Class MailRM Letter, First-Class MailRM Large Envelope, First-Class MailRM Parcel
                    // disable request for all First Class at 13oz. - First-Class Mail Letter, First-Class Mail Large Envelope, First-Class Package Service - RetailTM
                    // disable all first class requests if item is over 15oz.
                    // First-Class Retail and Commercial
                    if ($shipping_weight > 15/16) {
                        continue;
                    } else {
                        // First-Class MailRM Letter\', \'First-Class MailRM Large Envelope\', \'First-Class Package Service - RetailTM
                        $service = 'First Class';
                        // disable request for First-Class MailRM Letter at > .21875 and not Retail
                        if (($requested_type === 'First-Class Mail Letter') && (MODULE_SHIPPING_USPS_RATE_TYPE === 'Retail') && ($shipping_weight <= .21875)) {
                            $FirstClassMailType = 'LETTER';
                        // disable request for First-Class Mail Large Envelope at > 13oz and not Retail
                        } elseif (($requested_type === 'First-Class Mail Large Envelope') && (MODULE_SHIPPING_USPS_RATE_TYPE === 'Retail') && ($shipping_weight <= 13/16)) {
                            $FirstClassMailType = 'FLAT';
                        // disable request for First-Class Package Service - RetailTM(new retail parcel designation) at > 13oz and not Retail
                        } elseif (($requested_type === 'First-Class Package Service - RetailTM') && (MODULE_SHIPPING_USPS_RATE_TYPE === 'Retail') && ($shipping_weight <= 13/16)) {
                            $FirstClassMailType = 'PARCEL';
                        // disable request for First-ClassTM Package Service(existing commercial parcel designation) at > 1 lb and not Online(commercial pricing)
                        } elseif (($requested_type === 'First-ClassTM Package Service') && (MODULE_SHIPPING_USPS_RATE_TYPE === 'Online') && ($shipping_weight <= 15/16)) {
                            $service = 'First Class Commercial';
                            $FirstClassMailType = 'PACKAGE SERVICE';
                        } else {
                            continue;
                        }
                    }
                } elseif ($requested_type === 'Media Mail Parcel') {
                    $service = 'MEDIA';
                } elseif ($requested_type === 'USPS Retail GroundRM') {
                    $service = 'PARCEL';
                } elseif (preg_match('#Priority Mail(?! Express)#i', $requested_type)) {
                    $service = 'PRIORITY COMMERCIAL';
                    if ($requested_type === 'Priority Mail Flat RateRM Envelope' || $requested_type === 'Priority MailRM Flat Rate Envelope') {
                        $Container = 'FLAT RATE ENVELOPE';
                    } elseif ($requested_type === 'Priority MailRM Legal Flat Rate Envelope') {
                        $Container = 'LEGAL FLAT RATE ENVELOPE';
                    } elseif ($requested_type === 'Priority MailRM Padded Flat Rate Envelope') {
                        $Container = 'PADDED FLAT RATE ENVELOPE';
                    } elseif ($requested_type === 'Priority MailRM Small Flat Rate Box') {
                        $Container = 'SM FLAT RATE BOX';
                    } elseif ($requested_type === 'Priority MailRM Medium Flat Rate Box') {
                        $Container = 'MD FLAT RATE BOX';
                    } elseif ($requested_type === 'Priority MailRM Large Flat Rate Box') {
                        $Container = 'LG FLAT RATE BOX';
                    }
                } elseif (stripos($requested_type, 'Priority Mail Express') !== false) {
                    $service = 'EXPRESS COMMERCIAL';
                    if ($requested_type === 'Priority Mail ExpressRM Flat Rate Envelope') {
                        $Container = 'FLAT RATE ENVELOPE';
                    } elseif ($requested_type === 'Priority Mail ExpressRM Legal Flat Rate Envelope') {
                        $Container = 'LEGAL FLAT RATE ENVELOPE';
                    }
                } else {
                    continue;
                }

                // build special services for domestic
                $specialservices = $special_services_domestic;

                // uncomment to force turn off SpecialServices requests completely
                //$specialservices = '';

                $width = MODULE_SHIPPING_USPS_WIDTH;
                $length = MODULE_SHIPPING_USPS_LENGTH;
                $height = MODULE_SHIPPING_USPS_HEIGHT;
                $girth = 108;

                // turn on dimensions
                $dimensions =
                    '<Width>' . $width . '</Width>' .
                    '<Length>' . $length . '</Length>' .
                    '<Height>' . $height . '</Height>' .
                    '<Girth>' . $girth . '</Girth>';

                // uncomment to force turn off dimensions
                $dimensions = '';

                $request .=
                    '<Package ID="' . $package_count . '">' .
                        '<Service>' . $service . '</Service>' .
                        (($FirstClassMailType !== '') ? ('<FirstClassMailType>' . $FirstClassMailType . '</FirstClassMailType>') : '') .
                        '<ZipOrigination>' . SHIPPING_ORIGIN_ZIP . '</ZipOrigination>' .
                        '<ZipDestination>' . $ZipDestination . '</ZipDestination>' .
                        '<Pounds>' . $this->pounds . '</Pounds>' .
                        '<Ounces>' . $this->ounces . '</Ounces>' .
                        '<Container>' . $Container . '</Container>' .
                        '<Size>REGULAR</Size>' .
                        $dimensions .
                        '<Value>' . number_format($this->insurable_value, 2, '.', '') . '</Value>' .
                        $specialservices .
                        $usps_groundonly .
                        $usps_fragile .
                        '<Machinable>' . (($this->machinable === 'True') ? 'TRUE' : 'FALSE') . '</Machinable>' .
//'<DropOffTime>23:59</DropOffTime>' .
                        (($this->getTransitTime && $this->transitTimeCalculationMode === 'NEW') ? ('<ShipDate>' . $ship_date . '</ShipDate>') : '') .
                    '</Package>';

                $package_id .=
                    'Package ID returned: ' . $package_count .
                    ' $requested_type: ' . $requested_type .
                    ' $service: ' . $service .
                    ' $Container: ' . $Container . "\n";
                $package_count++;

                // ask for Domestic transit times using old double-request method to individual USPS API for each shipping service requested
                if ($this->getTransitTime && $this->transitTimeCalculationMode === 'OLD') {
                    $transitreq = 'USERID="' . MODULE_SHIPPING_USPS_USERID . '">' . '<OriginZip>' . SHIPPING_ORIGIN_ZIP . '</OriginZip>' . '<DestinationZip>' . $ZipDestination . '</DestinationZip>';
                    switch ($service) {
                        case 'PRIORITY COMMERCIAL':
                        case 'PRIORITY': $transreq[$requested_type] = 'API=PriorityMail&XML=' . urlencode( '<PriorityMailRequest ' . $transitreq . '</PriorityMailRequest>');
                            break;
                        case 'PARCEL':   $transreq[$requested_type] = 'API=StandardB&XML=' . urlencode( '<StandardBRequest ' . $transitreq . '</StandardBRequest>');
                            break;
                        case 'First-Class Mail':$transreq[$requested_type] = 'API=FirstClassMail&XML=' . urlencode( '<FirstClassMailRequest ' . $transitreq . '</FirstClassMailRequest>');
                            break;
                        case 'MEDIA':
                        default:
                            $transreq[$requested_type] = '';
                            break;
                    }
                }
            }

            $request .=  '</RateV4Request>';
            $request_raw = $request;
            $request = 'API=RateV4&XML=' . urlencode($request);
        } else {
            // INTERNATIONAL destinations
// for Machinable Rates place below Ounces
// NOTE: With Machinable set Rates will not show for: First-Class Mail International Large Envelope
// NOTE: With Machinable set Rates will be higher for: First-Class Mail International Letter
//                '<Machinable>' . ($this->machinable == 'True' ? 'True' : 'False') . '</Machinable>' .

// DestinationPostalCode is currently option, but this may change in the future
// associated with this maybe the AcceptanceDateTime with a valid XsdDateTime - <AcceptanceDateTime>2015-05-22T13:15:00-06:00</AcceptanceDateTime>
// when required, this will not allow black DestinationPostalCode
//      $DestinationPostalCode = trim($order->delivery['postcode']);
//      if ($DestinationPostalCode == '') return -1; // allow blank DestinationPostalCode

            // build extra services for international
            // Some Extra Services cannot work with others
            $extra_service_international = $this->extra_service(); // original

            $intl_gxg_requested = 0;
            foreach ($this->typeCheckboxesSelected as $requested_type) {
                if (!is_numeric($requested_type) && preg_match('#(GXG)#i', $requested_type)) {
                    $intl_gxg_requested++;
                }
            }

            // rudimentary dimensions, since they cannot be passed as blanks
            if (true || $intl_gxg_requested) {
        //@@TODO - force International to always use International settings - should be okay to make permanent at a future date
// obtain the most International settings
//        $width = 1.0; // $width = 0.75 for some International Methods to work
//        $length = 9.5;
//        $height = 5.5;
                $width = MODULE_SHIPPING_USPS_WIDTH_INTL;
                $length = MODULE_SHIPPING_USPS_LENGTH_INTL;
                $height = MODULE_SHIPPING_USPS_HEIGHT_INTL;
                $girth = 0;
            } else {
                $width = MODULE_SHIPPING_USPS_WIDTH;
                $length = MODULE_SHIPPING_USPS_LENGTH;
                $height = MODULE_SHIPPING_USPS_HEIGHT;
                $girth = 0;
            }

            // adjust <ValueOfContents> to not exceed $2499 per box
            global $shipping_num_boxes;
            $max_usps_allowed_price = ($order->info['subtotal'] > 0) ? ($order->info['subtotal'] + $this->orders_tax) : $_SESSION['cart']->total;
            $max_usps_allowed_price = ($max_usps_allowed_price / $shipping_num_boxes);

            // build extra services for international
            $extraservices = $extra_service_international;

            // uncomment to force turn off ExtraServices
            // $extraservices = '';

            // $max_usps_allowed_price - adjust <ValueOfContents> to not exceed $2499 per box
            $submission_value = ($this->insurable_value > $max_usps_allowed_price) ? $max_usps_allowed_price : $this->insurable_value;

            $request =
                '<IntlRateV2Request USERID="' . MODULE_SHIPPING_USPS_USERID . '">' .
                    '<Revision>2</Revision>' .
                    '<Package ID="0">' .
                        '<Pounds>' . $this->pounds . '</Pounds>' .
                        '<Ounces>' . $this->ounces . '</Ounces>' .
                        '<MailType>All</MailType>' .
                        '<GXG>' .
                            '<POBoxFlag>N</POBoxFlag>' .
                            '<GiftFlag>N</GiftFlag>' .
                        '</GXG>' .
                        '<ValueOfContents>' . number_format($submission_value, 2, '.', '') . '</ValueOfContents>' .
                        '<Country>' . (empty($this->countries[$order->delivery['country']['iso_code_2']]) ? zen_get_country_name($order->delivery['country']['id']) : $this->countries[$order->delivery['country']['iso_code_2']]) . '</Country>' .
                        '<Container>RECTANGULAR</Container>' .
                        '<Size>REGULAR</Size>' .
// Small Flat Rate Box - 'maxLength'=>'8.625', 'maxWidth'=>'5.375','maxHeight'=>'1.625'
// Global Express Guaranteed - Minimum 'minLength'=>'9.5', 'minHeight'=>'5.5' ; Maximum - 'maxLength'=>'46', 'maxWidth'=>'35', 'maxHeight'=>'46' and max. length plus girth combined 108"
// NOTE: sizes for Small Flat Rate Box prevent Global Express Guaranteed
// NOTE: sizes for Global Express Guaranteed prevent Small Flat Rate Box
// Not set up:
// Video - 'maxLength'=>'9.25', 'maxWidth'=>'6.25','maxHeight'=>'2'
// DVD - 'maxLength'=>'7.5625', 'maxWidth'=>'5.4375','maxHeight'=>'.625'
// defaults
// MODULE_SHIPPING_USPS_LENGTH 8.625
// MODULE_SHIPPING_USPS_WIDTH  5.375
// MODULE_SHIPPING_USPS_HEIGHT 1.625
                        '<Width>' . $width . '</Width>' .
                        '<Length>' . $length . '</Length>' .
                        '<Height>' . $height . '</Height>' .
                        '<Girth>' . $girth . '</Girth>' .

//'<CommercialPlusFlag>N</CommercialPlusFlag>' .
                        '<OriginZip>' . SHIPPING_ORIGIN_ZIP . '</OriginZip>' .

                        // In the following line, changed N to Y to activate optional commercial base pricing for international services - 01/27/13 a.forever edit
                        '<CommercialFlag>Y</CommercialFlag>' .
// '<AcceptanceDateTime>2015-05-30T13:15:00-06:00</AcceptanceDateTime>' .
// '<DestinationPostalCode>' . $DestinationPostalCode . '</DestinationPostalCode>' .
                        $extraservices .
                    '</Package>' .
                 '</IntlRateV2Request>';

            if ($this->getTransitTime) {
                $transreq[$requested_type] = '';
            }
            $request_raw = $request;
            $request = 'API=IntlRateV2&XML=' . urlencode($request);
        }

        // Prepare to make quote-request to USPS servers
        switch (MODULE_SHIPPING_USPS_SERVER) {
            // -----
            // 20200924 Update: USPS will be phasing out the http:// (non-secure) URL.
            //
            // Secure APIs: https://secure.shippingapis.com/ShippingAPI.dll
            // Non-secure APIs: http://production.shippingapis.com
            //
            case 'production':
                $usps_server = 'https://secure.shippingapis.com';
                $api_dll = 'ShippingAPI.dll';
                break;
            case 'test':
            default:
                $usps_server = 'https://stg-secure.shippingapis.com';
                $api_dll = 'ShippingApi.dll';
                break;
        }

        // BOF CURL
        // Send quote request via CURL
        global $request_type;
        $ch = curl_init();
        $curl_options = [
            CURLOPT_URL => $usps_server . '/' . $api_dll,
            CURLOPT_REFERER => ($request_type == 'SSL') ? (HTTPS_SERVER . DIR_WS_HTTPS_CATALOG) : (HTTP_SERVER . DIR_WS_CATALOG),
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_VERBOSE => 0,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $request,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Zen Cart',
        ];
        if (CURL_PROXY_REQUIRED === 'True') {
            $curl_options[CURLOPT_HTTPPROXYTUNNEL] = !defined('CURL_PROXY_TUNNEL_FLAG') || strtoupper(CURL_PROXY_TUNNEL_FLAG) !== 'FALSE';
            $curl_options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
            $curl_options[CURLOPT_PROXY] = CURL_PROXY_SERVER_DETAILS;
        }
        curl_setopt_array($ch, $curl_options);

        // -----
        // Log the starting time of the to-be-sent USPS request.
        //
        $this->uspsDebug('Sending request to USPS' . "\n");

        // -----
        // Submit the request to USPS via CURL.
        //
        $body = curl_exec($ch);
        $this->commError = curl_error($ch);
        $this->commErrNo = curl_errno($ch);
        $this->commInfo = curl_getinfo($ch);

        // SUBMIT ADDITIONAL REQUESTS FOR DELIVERY TIME ESTIMATES
        if ($this->transitTimeCalculationMode === 'OLD' && $this->getTransitTime && count($transreq) !== 0) {
            $transitResp = [];
            foreach ($transreq as $key => $value) {
                $transitResp[$key] = '';
                if ($value != '') {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $value);
                    $transitResp[$key] = curl_exec($ch);
                }
            }
            $this->parseDomesticLegacyAPITransitTimeResults($transitResp);
        }

        // done with CURL, so close connection
        curl_close ($ch);

        // -----
        // Log the CURL response (will also capture the time the response was received) to capture any
        // CURL-related errors and the shipping-methods being requested.  If a CURL error was returned,
        // no XML is returned in the response (aka $body).
        //
        $this->quoteLogCurlResponse($request_raw);

        //if communication error, return -1 because no quotes were found, and user doesn't need to see the actual error message (set DEBUG mode to get the messages logged instead)
        if ($this->commErrNo != 0) {
            return -1;
        }
        // EOF CURL

        // -----
        // A valid XMP response was received from USPS, log the information to the debug-output file.
        //
        $this->quoteLogXMLResponse($body);

        // This occasionally threw an error with simplexml; may only be needed for the test server but could change in the future for the production server
        /* $body = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '<?xml version="1.0"?>', $body);
        */
        $this->notify('NOTIFY_SHIPPING_USPS_QUOTES_RECEIVED');
        $body_array = simplexml_load_string($body);
        $body_encoded = json_decode(json_encode($body_array), true);
        return $body_encoded;
    }

    protected function quoteLogConfiguration()
    {
        global $order, $shipping_weight, $currencies;

        if ($this->debug_enabled === false) {
            return;
        }
        $message  = 'USPS build: ' . MODULE_SHIPPING_USPS_VERSION . "\n\n";
        $message .= 'Server: ' . MODULE_SHIPPING_USPS_SERVER . "\n";
        $message .= 'Quote Request Rate Type: ' . MODULE_SHIPPING_USPS_RATE_TYPE . "\n";
        $message .= 'Quote from main_page: ' . $_GET['main_page'] . "\n";
        $message .= 'USPS Options (weight, time): ' . MODULE_SHIPPING_USPS_OPTIONS . "\n";
        $message .= 'USPS Domestic Transit Time Calculation Mode: ' . MODULE_SHIPPING_USPS_TRANSIT_TIME_CALCULATION_MODE . "\n";

        $message .= 'Cart Weight: ' . $_SESSION['cart']->weight . "\n";
        $message .= 'Total Quote Weight: ' . $shipping_weight . ' Pounds: ' . $this->pounds . ' Ounces: ' . $this->ounces . "\n";
        $message .= 'Maximum: ' . SHIPPING_MAX_WEIGHT . ' Tare Rates: Small/Medium: ' . SHIPPING_BOX_WEIGHT . ' Large: ' . SHIPPING_BOX_PADDING . "\n";
        $message .= 'Handling method: ' . MODULE_SHIPPING_USPS_HANDLING_METHOD . ' Handling fee Domestic: ' . $currencies->format(MODULE_SHIPPING_USPS_HANDLING) . ' Handling fee International: ' . $currencies->format(MODULE_SHIPPING_USPS_HANDLING_INT) . "\n";

        $message .= 'Decimals: ' . MODULE_SHIPPING_USPS_DECIMALS . "\n";
        $message .= 'Domestic Length: ' . MODULE_SHIPPING_USPS_LENGTH . ' Width: ' . MODULE_SHIPPING_USPS_WIDTH . ' Height: ' . MODULE_SHIPPING_USPS_HEIGHT . "\n";
        $message .= 'International Length: ' . MODULE_SHIPPING_USPS_LENGTH_INTL . ' Width: ' . MODULE_SHIPPING_USPS_WIDTH_INTL . ' Height: ' . MODULE_SHIPPING_USPS_HEIGHT_INTL . "\n";

        $message .= 'All Packages are Machinable: ' . MODULE_SHIPPING_USPS_MACHINABLE . "\n";
        $message .= 'Enable USPS First-Class filter for US shipping: ' . MODULE_SHIPPING_USPS_FIRST_CLASS_FILTER_US . "\n";
        $message .= 'Sorts the returned quotes: ' . MODULE_SHIPPING_USPS_QUOTE_SORT . "\n\n";

        $message .=
            'ZipOrigination: ' . ((int)SHIPPING_ORIGIN_ZIP === 0 ? '***WARNING: NO STORE 5 DIGIT ZIP CODE SET' : SHIPPING_ORIGIN_ZIP) . "\n" .
            'ZipDestination: ' .
            (!empty($order->delivery['postcode']) ? 'Postcode: ' . $order->delivery['postcode'] : '') . 
            (!empty($this->countries[$order->delivery['country']['iso_code_2']]) ? ' Country: ' . $this->countries[$order->delivery['country']['iso_code_2']] : '') .
            (!empty($order->delivery['city']) ? ' City: ' . $order->delivery['city'] : '') .
            (!empty($order->delivery['state']) ? ' State: ' . $order->delivery['state'] : '') . "\n";

        $message .= 'Order SubTotal: ' . $currencies->format($order->info['subtotal']) . "\n";
        $message .= 'Order Total: ' . $currencies->format($this->shipment_value) . "\n";
        $message .= 'Uninsurable Portion: ' . $currencies->format($this->uninsurable_value) . "\n";
        $message .= 'Insurable Value: ' . $currencies->format($this->insurable_value) . "\n";

        $this->uspsDebug($message);
    }

    // -----
    // Called by the _getQuote processing after the CURL request/response has been
    // received.  If USPS debug is enabled, log the basics of the sent request.  Another
    // log record will record the USPS response if a communications error didn't occur.
    //
    protected function quoteLogCurlResponse($request)
    {
        global $order;

        if ($this->debug_enabled === false) {
            return;
        }

        $message =
            '==================================' . "\n\n" .
            'SENT TO USPS:' . "\n\n" .
            str_replace(
                [
                    '</Revision>',
                    '</Package>',
                ],
                [
                    '</Revision>' . PHP_EOL,
                    '</Package>' . PHP_EOL,
                ],
                $request
            ) .
            "\n\n";

        $message .= "\n" . 'RESPONSE FROM USPS: ' . "\n";
        $message .= "\n" . '==================================' . "\n";
        $message .= 'CommErr (should be 0): ' . $this->commErrNo . ' - ' . $this->commError . "\n\n";
        
        $message .= '==================================' . "\n\n" . 'USPS Country - $order->delivery[country][iso_code_2]: ' . $this->countries[$order->delivery['country']['iso_code_2']] . ' $this->usps_countries: ' . $this->usps_countries . "\n";

        // -----
        // Build a list of requested shipping services for the log.
        //
        $services_domestic = 'Domestic Services Selected: ' . "\n";
        $services_international = 'International Services Selected: ' . "\n";
        // Domestic/US destination:
        if ($this->is_us_shipment === true) {
            $message .= 'Domestic Services Selected: ' . "\n";
            $options = explode(', ', MODULE_SHIPPING_USPS_DMST_SERVICES);
        } else {
            $message .= 'International Services Selected: ' . "\n";
            $options = explode(', ', MODULE_SHIPPING_USPS_INTL_SERVICES);
        }
        foreach ($options as $key => $val) {
            if (strlen($options[$key]) > 1) {
                switch ($options[$key + 1]) {
                    case 'C':
                    case 'S':
                    case 'Y':
                        $message .= $options[$key] . "\n";
                        break;
                    default:
                        break;
                }
            }
        }
        $this->uspsDebug($message);
    }

    protected function quoteLogXMLResponse($response)
    {
        if ($this->debug_enabled === false) {
            return;
        }
 
        $message = 'RESPONSE FROM USPS:' . "\n" . '==================================' . "\n";
        $response = str_replace(
            [
                '<sup>&#8482;</sup>',
                '<sup>&#174;</sup>',
                '<Service ID',
                '<SvcDescription',
                '</Service>',
                '<MaxDimensions>',
                '<MaxWeight>',
                '<Package ID',
                '<Postage CLASSID',
                '<Rate>',
                '<SpecialServices',
                '<ServiceID>',
                '<Description>',
                '</Postage>',               //-US shipments only
                '<Location>',               //-US shipments only
                '</RateV4Response>',        //-US shipments only
                '<Postage>',                //-International shipments only
                '<ValueOfContents>',        //-International shipments only
            ],
            [
                'TM', 
                'RM',
                "\n\n" . '<Service ID',
                "\n" . '<SvcDescription',
                '</Service>' . "\n",
                "\n" . '<MaxDimensions>',
                "\n" . '<MaxWeight>',
                "\n\n\n" . '<Package ID',
                "\n" . '<Postage CLASSID',
                "\n" . '<Rate>',
                "\n" . '<SpecialServices',
                "\n" . '<ServiceID>',
                "\n" . '<Description>',
                "\n" . '</Postage>',        //-US shipments only
                "\n\t\t\t" .'<Location>',   //-US shipments only
                "\n" . '</RateV4Response>', //-US shipments only
                "\n" . '<Postage>',         //-International shipments only
                "\n" . '<ValueOfContents>', //-International shipments only
            ],
            $response
        );

        $message .= "============\n\nRAW XML FROM USPS:\n\n" . print_r(simplexml_load_string($response), true) . "\n\n";

        $this->uspsDebug($message);
    }

    /**
     * Legacy method:
     * Parse the domestic-services transit time results data obtained from special extra API calls
     * @param array $transresp
     */
    protected function parseDomesticLegacyAPITransitTimeResults($transresp)
    {
        $this->uspsDebug('TRANSIT TIME PARSING (domestic legacy API)' . "\n\n");
        foreach ($transresp as $service => $val) {
            $val = json_decode(json_encode(simplexml_load_string($val)), true);
            switch (true) {
                case (stripos($service, 'Priority Mail Express') !== false):
                    $time = $val['CommitmentTime'];
                    if ($time == '' || $time == 'No Data') {
                        $time = '1 - 2 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    } else {
                        $time = 'Tomorrow by ' . $time;
                    }
                    break;
                case (stripos($service, 'Priority Mail') !== false):
                    $time = $val['Days'];
                    if ($time === '' || $time === 'No Data') {
                        $time = '2 - 3 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    } elseif ($time === '1') {
                        $time .= ' ' . MODULE_SHIPPING_USPS_TEXT_DAY;
                    } else {
                        $time .= ' ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    }
                    break;
                case (stripos($service, 'USPS Retail Ground') !== false):
                    $time = $val['Days'];
                    if ($time === '' || $time === 'No Data') {
                        $time = '4 - 7 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    } elseif ($time === '1') {
                        $time .= ' ' . MODULE_SHIPPING_USPS_TEXT_DAY;
                    } else {
                        $time .= ' ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    }
                    break;
                case (stripos($service, 'First-Class') !== false):
                    $time = '2 - 5 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    break;
//                case (preg_match('#Media Mail Parcel#i', $service)):
                default:
                    $time = '';
                    break;
            }
            $this->transittime[$service] = ($time === '') ? '' : (' (' . $time . ')');
 
            $this->uspsDebug(
                'Transit Time' . "\n" .
                'Service' . $service . "\n" .
                'CommitmentTime (from USPS): ' . $val['CommitmentTime'] . "\n" .
                'Days(from USPS): ' . $val['Days'] . "\n" .
                '$time (calculated): ' . $time . "\n" .
                'Translation:' . $this->transittime[$service] . "\n\n"
            );
        }
    }

    /**
     * Parse the domestic-services transit time results data returned by passing the <ShipDate> request parameter
     * @param array $Package - The package details array to parse, received from USPS and semi-sanitized already
     * @param string $service - The delivery service being evaluated
     * ref: <CommitmentDate>2013-07-23</CommitmentDate><CommitmentName>1-Day</CommitmentName>
     */
    protected function parseDomesticTransitTimeResults($Package, $service)
    {
        $time = !empty($Package['CommitmentName']) ? $Package['CommitmentName'] : '';
        if ($time === '' || $this->transitTimeCalculationMode === 'CUSTOM') {
            switch (true) {
      /********************* CUSTOM START:  IF YOU HAVE CUSTOM TRANSIT TIMES ENTER THEM HERE ***************/
                case (stripos($service, 'Priority Mail Express') !== false):
                    $time = '1 - 2 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    break;
                case (stripos($service, 'Priority Mail') !== false):
                    $time = '2 - 3 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    break;
                case (stripos($service, 'USPS Retail GroundRM') !== false):
                    $time = '4 - 7 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                break;
                case (stripos($service, 'First-Class') !== false):
                    $time = '2 - 5 ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    break;
//                case (preg_match('#Media Mail Parcel#i', $service)):
                default:
                    $time = '';
                    break;
      /********************* CUSTOM END:  IF YOU HAVE CUSTOM TRANSIT TIMES ENTER THEM HERE ***************/
            }
        } else {
            // fix USPS issues with CommitmentName, example: GUAM
            if (is_array($time)) {
                $time = '';
            } else {
                $time = str_replace(
                    [
                        'Weeks',
                        'Days',
                        'Day',
                    ],
                    [
                        MODULE_SHIPPING_USPS_TEXT_WEEKS,
                        MODULE_SHIPPING_USPS_TEXT_DAYS,
                        MODULE_SHIPPING_USPS_TEXT_DAY,
                    ],
                    $time
                );
            }
        }

        // bof: not guaranteed on NOT Priority Mail Express
        // remove comment // marks to use
        if (stripos($service, 'Priority Mail Express') !== false) {
            //  $time .= ' - not guaranteed Domestic';
        }
        // eof: not guaranteed on NOT Priority Mail Express

        $this->transittime[$service] = ($time === '') ? '' : (' (' . $time . ')');

        if (empty($Package['CommitmentName'])) {
            $Package['CommitmentName'] = 'Not Returned';
        }
        $this->uspsDebug(
            ' Transit Time (Domestic)' .
            "\nService:                    " . $service .
            "\nCommitmentName (from USPS): " . $Package['CommitmentName'] . "\n" .
            '$time (calculated):         ' . $time .
            "\nTranslation:               " . $this->transittime[$service] .
            "\n\n"
        );
    }

    /**
     * Parse the international-services transit time results data
     * Parse the domestic-services transit time results data returned by passing the <ShipDate> request parameter
     * @param array $Package - The package details array to parse, received from USPS and semi-sanitized already
     * @param string $service - The delivery service being evaluated
     * ref: <SvcCommitments>value</SvcCommitments>
     */
    protected function parseIntlTransitTimeResults($Package, $service)
    {
        if (!preg_match('#(GXG|International)#i', $service)) {
            $this->uspsDebug('Transit Time (Intl)' . "\nService: " . $service . "\nWARNING: NOT INTERNATIONAL. SKIPPING.\n\n");
            return;
        }

        $time = isset($Package['SvcCommitments']) ? $Package['SvcCommitments'] : '';
        if ($time === '' || $this->transitTimeCalculationMode === 'CUSTOM') {
            switch (true) {
            /********************* CUSTOM START:  IF YOU HAVE CUSTOM TRANSIT TIMES ENTER THEM HERE ***************/
                case (stripos($service, 'Priority Mail Express') !== false):
                    $time = '3 - 5 business ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    break;
                case (stripos($service, 'Priority Mail') !== false):
                    $time = '6 - 10 business ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    break;
                case (stripos($service, 'Global Express Guaranteed') !== false):
                    $time = '1 - 3 business ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    break;
                case (preg_match('#USPS GXG.* Envelopes#i', $service)):
                    $time = '1 - 3 business ' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    break;
                case (stripos($service, 'First-Class') !== false):
                    $time = 'Varies by destination'; // '' . MODULE_SHIPPING_USPS_TEXT_DAYS;
                    break;
                default:
                    $time = '';
                    break;
            /********************* CUSTOM END:  IF YOU HAVE CUSTOM TRANSIT TIMES ENTER THEM HERE ***************/
            }
        } else {
            $time = str_replace(
                [
                    'Weeks',
                    'Days',
                    'Day',
                ],
                [
                    MODULE_SHIPPING_USPS_TEXT_WEEKS,
                    MODULE_SHIPPING_USPS_TEXT_DAYS,
                    MODULE_SHIPPING_USPS_TEXT_DAY,
                ],
                $time
            );
        }

        // uncomment to remove extra text in times
        //    $time = str_replace(' to many major markets', '', $time);

        // bof: not guaranteed on NOT Priority Mail Express
        // remove comment // marks to use
        if (stripos($service, 'Priority Mail Express') !== false) {
            //  $time .= ' - not guaranteed International';
        }
        // eof: not guaranteed on NOT Priority Mail Express

        $this->transittime[$service] = ($time === '') ? '' : (' (' . $time . ')');

        // do logging if the file was opened earlier by the config switch
        $this->uspsDebug(
            ' Transit Time (Intl)' .
            "\nService: " . $service .
            "\nSvcCommitments (from USPS): " . $Package['SvcCommitments'] .
            "\n" . '$time (calculated): ' . $time .
            "\nTranslation: " . $this->transittime[$service] .
            "\n\n"
        );
    }

    /**
     * USPS Country Code List
     * This list is used to compare the 2-letter ISO code against the order country ISO code, and provide the proper/expected
     * spelling of the country name to USPS in order to obtain a rate quote
     *
     * @return array
     */
    protected function country_list()
    {
        $list = [
            'AF' => 'Afghanistan',
            'AL' => 'Albania',
            'AX' => 'Aland Island (Finland)',
            'DZ' => 'Algeria',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AG' => 'Antigua and Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BQ' => 'Bonaire (Curacao)',
            'BA' => 'Bosnia-Herzegovina',
            'BW' => 'Botswana',
            'BR' => 'Brazil',
            'VG' => 'British Virgin Islands',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'MM' => 'Burma',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island (Australia)',
            'CC' => 'Cocos Island (Australia)',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo, Republic of the',
            'CD' => 'Congo, Democratic Republic of the',
            'CK' => 'Cook Islands (New Zealand)',
            'CR' => 'Costa Rica',
            'CI' => 'Cote d Ivoire (Ivory Coast)',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CW' => 'Curacao',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia, Republic of',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GB' => 'Great Britain and Northern Ireland',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GT' => 'Guatemala',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Laos',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'North Macedonia, Republic of',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte (France)',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States of',
            'MD' => 'Moldova',
            'MC' => 'Monaco (France)',
            'MN' => 'Mongolia',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'AN' => 'Netherlands Antilles',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'KP' => 'North Korea (Korea, Democratic People\'s Republic of)',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn Island',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russia',
            'RW' => 'Rwanda',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts (Saint Christopher and Nevis)',
            'LC' => 'Saint Lucia',
            'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SX' => 'Sint Maarten (Dutch)',
            'SK' => 'Slovak Republic',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia (Falkland Islands)',
            'KR' => 'South Korea (Korea, Republic of)',
            'SS' => 'South Sudan',      //-Note, as of zc157a this country is not included in the standard countries' list.
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SZ' => 'Eswatini',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TL' => 'East Timor (Indonesia)',
            'TG' => 'Togo',
            'TK' => 'Tokelau (Union Group) (Western Samoa)',
            'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Türkiye',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'UY' => 'Uruguay',
            'US' => 'United States',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VA' => 'Vatican City',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
            'WF' => 'Wallis and Futuna Islands',
            'WS' => 'Western Samoa',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
            'PS' => 'Palestinian Territory', // usps does not ship
            'ME' => 'Montenegro',
            'GG' => 'Guernsey',
            'IM' => 'Isle of Man',
            'JE' => 'Jersey'
        ];
        $this->notify('NOTIFY_SHIPPING_USPS_COUNTRY_LIST', [], $list);
        return $list;
    }

    // translate for US Territories
    protected function usps_translation()
    {
        $this->notify('NOTIFY_SHIPPING_USPS_TRANSLATION');
        global $order;
        $delivery_country = 'US';
        if (SHIPPING_ORIGIN_COUNTRY === '223') {
            switch ($order->delivery['country']['iso_code_2']) {
                case 'AS': // Samoa American
                case 'GU': // Guam
                case 'MP': // Northern Mariana Islands
                case 'PW': // Palau
                case 'PR': // Puerto Rico
                case 'VI': // Virgin Islands US
    // which is right
                case 'FM': // Micronesia, Federated States of
                    break;
    // stays as original country
    //        case 'FM': // Micronesia, Federated States of
                default:
                    $delivery_country = $order->delivery['country']['iso_code_2'];
                    break;
            }
        } else {
            $delivery_country = $order->delivery['country']['iso_code_2'];
        }

        // -----
        // If the delivery country is the US, set a multi-use processing flag
        // to simplify the remaining code.
        //
        $this->is_us_shipment = ($delivery_country === 'US');
        
        return $delivery_country;
    }

    // used for shipDate
    protected function zen_usps_shipdate()
    {
        // safety calculation for cutoff time
        if ($this->shipping_cutoff_time < 1200 || $this->shipping_cutoff_time > 2300) {
            $this->shipping_cutoff_time = 1400;
        }
        // calculate today vs tomorrow based on time
        if (date('Hi') < $this->shipping_cutoff_time) { // expects it in the form of HHMM
            $datetime = new DateTime('today');
        } else {
            $datetime = new DateTime('tomorrow');
        //         $datetime = new DateTime((date('l') == 'Friday') ? 'Monday next week' : 'tomorrow');
        }
        return $datetime->format('Y-m-d');
    }

    protected function clean_usps_marks($string)
    {
        // strip reg and trade symbols
        $string = str_replace(
            [
                '&amp;lt;sup&amp;gt;&amp;#174;&amp;lt;/sup&amp;gt;',
                '&amp;lt;sup&amp;gt;&amp;#8482;&amp;lt;/sup&amp;gt;',
            ],
            [
                'RM',
                'TM',
            ],
            htmlspecialchars($string)
        );

        // shipdate info removed from names as it is contained in the shipping methods
        // refers to this field for Domestic: <CommitmentName>  or International: <SvcCommitments>
        $string = str_replace(
            [
                'Mail 1-Day',
                'Mail 2-Day',
                'Mail 3-Day',
                'Mail Military',
                'Mail DPO',
            ],
            'Mail',
            $string
        );
        $string = str_replace(
            [
                'Express 1-Day',
                'Express 2-Day',
                'Express 3-Day',
                'Express Military',
                'Express DPO',
            ],
            'Express',
            $string
        );
        return $string;
    }

    // return SpecialService tags based on checked choices only
    protected function special_services()
    {
/*
The Special service definitions are as follows:
USPS Special Service Name ServiceID - Our Special Service Name
  Certified 0 - Certified MailRM
  Insurance 1 - Insurance
Restricted Delivery 3
  Registered without Insurance 4 - Registered without Insurance - REMOVED
  Registered with Insurance 5 - Registered MailTM
  Collect on Delivery 6 - Collect on Delivery
  Return Receipt for Merchandise 7 - Return Receipt for Merchandise - REMOVED 20201003
  Return Receipt 8 - Return Receipt
  Certificate of Mailing (Form 3817) (per individual article) 9 - Certificate of Mailing (Form 3817)
Certificate of Mailing (for firm mailing books) 10
  Express Mail Insurance 11 - Priority Mail Express Insurance
  USPS Tracking/Delivery Confirmation 13 - USPS TrackingTM
  USPS TrackingTM Electronic 12 - USPS TrackingTM Electronic
  Signature Confirmation 15 - Signature ConfirmationTM
  Signature ConfirmationTM Electronic 14 - Signature ConfirmationTM Electronic
Return Receipt Electronic 16
  Adult Signature Required 19 - Adult Signature Required
  Adult Signature Restricted Delivery 20 - Adult Signature Restricted Delivery
Priority Mail Express 1030 AM Delivery 200 - Priority Mail Express 1030 AM Delivery

All in order:
$specialservicesdomestic: Certified MailRM USPS TrackingTM Insurance Priority Mail Express Insurance Adult Signature Restricted Delivery Adult Signature Required Registered without Insurance Registered MailTM Collect on Delivery Return Receipt for Merchandise Return Receipt Certificate of Mailing (Form 3817) Signature ConfirmationTM Priority Mail Express 1030 AM Delivery
*/

//@@TODO
// Return Receipt Electronic 110 (16) missing
        $options2codes = [
            'Certified MailRM' => '105',                        // 0
            'Insurance' => '100',                               // 1
            'Registered MailTM' => '109',                       // 5, docs said 4
            'Collect on Delivery' => '103',                     // 6
            'Return Receipt' => '102',                          // 8
            'Certificate of Mailing (Form 3665)' => '160',      // 10
            'Certificate of Mailing (Form 3817)' => '104',      // 9
            'Priority Mail Express Insurance' => '101',         // 11
            'Priority Mail Insurance' => '125',                 // 1
            'USPS TrackingTM Electronic' => '155',              // 12, docs said 13
            'USPS TrackingTM' => '106',                         // 13
            'Signature ConfirmationTM Electronic' => '156',     // 14, docs said 15
            'Signature ConfirmationTM' => '108',                // 15
            'Adult Signature Required' => '119',                // 19
            'Adult Signature Restricted Delivery' => '120',     // 20
            'Priority Mail Express 1030 AM Delivery' => '161',  // 200 (not currently working)
        // Added 2015_0531
            'Certified MailRM Restricted Delivery' => '170',
            'Certified MailRM Adult Signature Required' => '171',
            'Certified MailRM Adult Signature Restricted Delivery' => '172',
            'Signature ConfirmationTM Restricted Delivery' => '173',
            'Signature ConfirmationTM Electronic Restricted Delivery' => '174',
            'Collect on Delivery Restricted Delivery' => '175',
            'Registered MailTM Restricted Delivery' => '176',
            'Insurance Restricted Delivery' => '177',
            'Insurance Restricted Delivery (Priority Mail Express)' => '178',
            'Insurance Restricted Delivery (Priority Mail)' => '179',
        ];
        $serviceOptions = explode(', ', MODULE_SHIPPING_USPS_DMST_SERVICES); // domestic
        $specialservicesdomestic = '';
        foreach ($serviceOptions as $key => $val) {
            if (strlen($serviceOptions[$key]) > 1) {
                if ($serviceOptions[$key + 1] === 'C' || $serviceOptions[$key + 1] === 'S' || $serviceOptions[$key + 1] === 'Y') {
                    if (array_key_exists($serviceOptions[$key], $options2codes)) {
                        $specialservicesdomestic .= '  <SpecialService>' . $options2codes[$serviceOptions[$key]] . '</SpecialService>' . "\n";
                    }
                }
            }
        }
        if ($specialservicesdomestic !== '') {
            $specialservicesdomestic = '<SpecialServices>' . $specialservicesdomestic . '</SpecialServices>';
        }
        return $specialservicesdomestic;
    }

    // return ExtraService tags based on checked choices only
    protected function extra_service()
    {
/*
The extra service definitions are as follows:
USPS Extra Service Name ServiceID - Our Extra Service Name
 Registered Mail 0 - Registered Mail
 Insurance 1 - Insurance
 Return Receipt 2 - Return Receipt
 Certificate of Mailing 6 - Certificate of Mailing
 Electronic USPS Delivery Confirmation International 9 - Electronic USPS Delivery Confirmation International
*/
        $iserviceOptions = explode(', ', MODULE_SHIPPING_USPS_INTL_SERVICES);
        $extraserviceinternational = '';
        foreach ($iserviceOptions as $key => $val) {
            if (strlen($iserviceOptions[$key]) > 1) {
                if ($iserviceOptions[$key + 1] === 'C' || $iserviceOptions[$key + 1] === 'S' || $iserviceOptions[$key + 1] === 'Y') {
                    switch ($iserviceOptions[$key]) {
                        case 'Registered Mail':
                            $extraserviceinternational .= '  <ExtraService>0</ExtraService>' . "\n";
                            break;
                        case 'Insurance':
                            $extraserviceinternational .= '  <ExtraService>1</ExtraService>' . "\n";
                            break;
                        case 'Return Receipt':
                            $extraserviceinternational .= '  <ExtraService>2</ExtraService>' . "\n";
                            break;
                        case 'Certificate of Mailing':
                            $extraserviceinternational .= '  <ExtraService>6</ExtraService>' . "\n";
                            break;
                        case 'Electronic USPS Delivery Confirmation International':
                            $extraserviceinternational .= '  <ExtraService>9</ExtraService>' . "\n";
                            break;
                        default:
                            break;
                    }
                }
            }
        }
        if ($extraserviceinternational !== '') {
            $extraserviceinternational = '<ExtraServices>' . $extraserviceinternational . '</ExtraServices>';
        }
        return $extraserviceinternational;
    }

    // -----
    // If debug-logging is enabled, write the requested message to the log-file determined in this
    // module's class-constructor.
    //
    protected function uspsDebug($message)
    {
        if ($this->debug_enabled === true) {
            error_log(date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL, 3, $this->debug_filename);
        }
    }
}

// admin display functions inspired by osCbyJetta
function zen_cfg_usps_services($select_array, $key_value, $key = '')
{
    $key_values = explode(', ', $key_value);
    $name = ($key) ? ('configuration[' . $key . '][]') : 'configuration_value';

    $w20pxl = 'width:20px;float:left;text-align:center;';
    $w60pxl = 'width:60px;float:left;text-align:center;';
    $frc = 'float:right;text-align:center;';

    $string =
        '<b>' .
            '<div style="' . $w20pxl . '">&nbsp;</div>' .
            '<div style="' . $w60pxl . '">Min</div>' .
            '<div style="' . $w60pxl . '">Max</div>' .
            '<div style="float:left;"></div>' .
            '<div style="' . $frc . '">Handling</div>' .
        '</b>' .
        '<div style="clear:both;"></div>';
    $string_spacing = '<div><br><br><b>&nbsp;International Rates:</b><br></div>' . $string;
    $string_spacing_international = 0;
    $string = '<div><br><b>&nbsp;Domestic Rates:</b><br></div>' . $string;
    for ($i = 0, $n = count($select_array); $i < $n; $i++) {
        if (stripos($select_array[$i], 'international') !== false) {
            $string_spacing_international++;
        }
        if ($string_spacing_international === 1) {
            $string .= $string_spacing;
        }

        $string .= '<div id="' . $key . $i . '">';
        $string .=
            '<div style="' . $w20pxl . '">' .
                zen_draw_checkbox_field($name, $select_array[$i], (in_array($select_array[$i], $key_values) ? 'CHECKED' : '')) .
            '</div>';
        if (in_array($select_array[$i], $key_values)) {
            next($key_values);
        }
        $string .=
            '<div style="' . $w60pxl . '">' .
                zen_draw_input_field($name, current($key_values), 'size="5"') .
            '</div>';
        next($key_values);

        $string .=
            '<div style="' . $w60pxl . '">' .
                zen_draw_input_field($name, current($key_values), 'size="5"') .
            '</div>';
        next($key_values);

        $string .=
            '<div style="float:left;">' .
                preg_replace(
                    [
                        '/RM/',
                        '/TM/',
                        '/International/',
                        '/Envelope/',
                        '/ Mail/',
                        '/Large/',
                        '/Medium/',
                        '/Small/',
                        '/First/',
                        '/Legal/',
                        '/Padded/',
                        '/Flat Rate/',
                        '/Express Guaranteed /',
                        '/Package\hService\h-\hRetail/',
                        '/Package Service/',
                    ],
                    [
                        '',
                        '',
                        'Intl',
                        'Env',
                        '',
                        'Lg.',
                        'Md.',
                        'Sm.',
                        '1st',
                        'Leg.',
                        'Pad.',
                        'F/R',
                        'Exp Guar',
                        'Pkgs - Retail',
                        'Pkgs - Comm',
                    ],
                    $select_array[$i]
                ) .
            '</div>';
        $string .=
            '<div style="'. $frc . '">$' .
                zen_draw_input_field($name, current($key_values), 'size="4"') .
            '</div>';
        next($key_values);

        $string .= '<div style="clear:both;"></div></div>';
    }
    return $string;
}

function zen_cfg_usps_extraservices($select_array, $key_value, $key = '')
{
    $key_values = explode(', ', $key_value);
    $name = ($key) ? ('configuration[' . $key . '][]') : 'configuration_value';
    $style = 'width:20px;float:left;text-align:center;';
    $string =
        '<b>' .
            '<div style="' . $style . '">N</div>' .
            '<div style="' . $style . '">Y</div>' .
        '</b>' .
        '<div style="clear:both;"></div>';
    for ($i = 0, $n = count($select_array); $i < $n; $i++) {
        $string .= zen_draw_hidden_field($name, $select_array[$i]);
        next($key_values);

        $string .= '<div id="' . $key . $i . '">';
        $string .=
            '<div style="' . $style . '">' .
                '<input type="checkbox" name="' . $name . '" value="N" ' . ((current($key_values) === 'N' || current($key_values) === '') ? 'checked' : '') . ' id="N-'. $key . $i . '" onclick="if(this.checked==1)document.getElementById(\'Y-'.$key.$i.'\').checked=false;else document.getElementById(\'Y-'.$key.$i.'\').checked=true;">' .
            '</div>';
        $string .=
            '<div style="' . $style . '">' .
                '<input type="checkbox" name="' . $name . '" value="Y" ' . ((current($key_values) === 'Y') ? 'checked' : '') . ' id="Y-' . $key . $i . '" onclick="if(this.checked==1)document.getElementById(\'N-' . $key . $i .'\').checked=false;else document.getElementById(\'N-' . $key . $i . '\').checked=true;">' .
            '</div>';
        next($key_values);

        $string .=
            str_replace(
                [
                    'Signature',
                    'without',
                    'Merchandise',
                    'TM',
                    'RM',
                ],
                [
                    'Sig',
                    'w/out',
                    'Merch.',
                    '',
                    '',
                ],
                $select_array[$i]
            ) .
            '<br>';
        $string .= '<div style="clear:both;"></div></div>';
    }
    return $string;
}

USPS RateV4 Intl RateV2 - 2023-mm-dd Version K11f

Note: This shipping-module now has a GitHub repository:  https://github.com/lat9/usps.  Additional documentation is available online.

This module requires that you have CURL installed on your server.

If you do not already have a USPS Web Tools account ...

Registering and Creating a customer account for USPS realtime shipping quotes ...

If you do not already have a USPS Web Tools account ...

1. Register and create a USPS Web Tools account:
https://www.usps.com/business/web-tools-apis/welcome.htm

2. Fill in your customer information details and click Submit

3. You will receive an email containing your USPS rate-quote Web Tools User ID

4. Insert the Web Tools User ID in the Zen Cart USPS shipping module.

5. Telephone USPS 1-800-344-7779 and ask them to move your account to the Production Server or email them at icustomercare@usps.com, quoting your Web Tools User ID.

6. They will send another confirmation email. Set the Zen Cart module into Production mode (instead of Test mode) to finish activation.


To install or update this code ...

1. go to your Zen Cart Admin to the Modules ... Shipping ...

2. If USPS exists, click on USPS and see what version is currently installed.
   a. If you currently have either '2021-05-05 K11a' or later installed, continue the update at
      step 3.  You don't need to save your settings or remove/reinstall to get the module updated.
   b. Edit the USPS module, so that you can see your current settings.  Save those settings using a screenshot
      or copy/paste them into a text editor (like NotePad or NotePad++).
   c. Cancel out of the USPS module's settings edit and 'REMOVE' the module to uninstall the current version
      of USPS.

3. Load the new files with your FTP program they go in the same directories so you can copy the directory
   /includes to your server and overwrite the old files:

   - /includes/modules/shipping/usps.php
   - /includes/languages/english/modules/shipping/usps.php
   - /includes/templates/template_default/images/icons/shipping_usps.gif

4. Go to your Zen Cart Admin and to the Modules ... Shipping ...
   A. If you continued an update from step 2a above, the module has automatically updated itself and
      preserved your previous settings -- you're all finished!
   B. Otherwise, you are performing an initial installation or an update from an older version of USPS:
      a.  Click on the USPS shipping module and then click the "Install" button.
      b. If you are updating from an older version, copy your previous configuration settings from
          those you saved at step 2b.  Otherwise, for an initial installation, enter the configuration
          settings for your freshly-installed version of USPS.
      c. Click the "Update" button to save your changes.

===== CHANGE HISTORY =====
2023-01-29 by lat9 2023-mm-dd Version K11f
    - Refactoring and declaring all class variables for use under PHP 7.4 and later
    - 'Priority MailRM Regional Rate Box A' and 'Priority MailRM Regional Rate Box B' no longer supported
       by USPS.  Remove from current configuration on auto-update; don't include in initial configuration
       on fresh install.
    - Country full-spelling name changed from 'Turkey' to 'Türkiye'.
    - Correct PHP Warning when US-destined USPS quote returns no values.
    - Add a notification to enable an observer to modify the overall handling fee for the current order.
2022-08-07 by lat9/proseLA 2022-08-07 Version K11e
    - Auto-update not recognized for K11c -> K11d version, due to version name misspelling
2022-07-30 by lat9/proseLA 2022-07-30 Version K11d
    - CommitmentName fields for some methods returning as an empty array and resulting in debug-logs being generated.
2022-07-12 by lat9 2022-07-12 Version K11c
    - USPS branding has changed for "Priority Mail Express International", too; no quotes being returned.
2022-07-10 by lat9 2022-07-10 Version K11b
    - Enabling 'soft' configuration settings to be used as overrides.
    - Correcting PHP warnings when no quote is returned from USPS.
    - USPS branding has changed for "Priority Mail" and "Priority Mail Express" domestic shipments; no quotes being returned.
2021-05-05 by lat9 2021-05-05 Version K11a
    - Adding notification to enable store-specific customizations for methods/prices offered (see https://github.com/lat9/usps for additional documentation).
    - Modify default international shipping 'base' dimensions to enable the offering of the International Small Flat-Rate Box method.
2020-10-21 by lat9 2020-09-24 Version K11
    - Restructuring to prevent PHP notices and warnings.
      - Re-factored to use now-current code styling.
      - Includes modifications to use stripos/strpos instead of preg_match for 'simple' string-in-string checks.
      - Use foreach() instead of deprecated each()
    - Use secure (https://secure.shippingapis.com/ShippingAPI.dll) endpoint for API requests; unsecure endpoint being retired.
    - Simplifies debug handling, 'Screen' and 'Email' no longer supported.
        - Debug filename changed to enable sort-by-name to mimic sort-by-date on the files.
    - Restores the USPS icon to this shipping-method's distribution zip-file.
    - Correct missing constant warning (MODULE_SHIPPING_USPS_REGULATIONS)
        - That 'soft' configuration setting is now available in the usps.php language file.
        - Additional, previously undefined language constants added in support of the display.
    - 'Return Receipt for Merchandise [107]' retired and USPS will return an error if requested.
    - Country name changes:
            - Country ('MK') changed from 'Macedonia, Republic of' to 'North Macedonia, Republic of'.
            - Country ('SZ') changed from 'Swaziland' to 'Eswatini'.
            - Country ('SS') added (South Sudan); note that the country is not currently registered in the countries table.
    - Add 'soft' configuration settings, present in the module's language file (refer to that file for additional information):
        - MODULE_SHIPPING_USPS_SHIPPING_CUTOFF ... the shipping cut-off time, used to determine the delivery date.
        - MODULE_SHIPPING_USPS_GROUNDONLY ... identifies whether the database field 'products::products_groundonly' should be interrogated.
        - MODULE_SHIPPING_USPS_FRAGILE ... identifies whether the database field 'products::products_fragile' should be interrogated.
    - Remove 'plugin_check_for_updates' function.  It's now expected to be present as part of the base Zen Cart distribution (zc152+).
    - Auto-disable on the storefront if no shipping services have been selected or if the store's country-of-origin isn't the US (country code 223).

2018-03-28 by bislewl
    - Changed "USPS Retail GroundTM" -> "USPS Retail GroundRM" as it is now a registrered trademark otherwise it won't show the rates

2017-09-16 by tflmike
    - In zen cart 1.5.1 the version is not reporting correctly, In 1.5.2, 1.5.3, 1.5.4, 1.5.5 it was showing it as version 2017-09-04 this update corrects both of those problems. Nothing else was   modified just dates and version id for the function that checks for current version in 1.5.1

2017-09-07 by tflmike
    - Modified config to include updated naming for First Class Packages per the changes made to the USPS API 
      from First Class Mail Parcel to First Class Package Service - Retail. 
    - Slight modification to format xml with the correct names to the USPS Web Service. 
    - Slight modification to correct check box listing to include Package Retail option. 
    - Modified structure for First Class to filter out commercial pricing when retail pricing is selected and also
      to filter weight requirements between first class package with retail max 13oz and commercial max 15oz.

2015-05-31
    - Update to the USPS Production Server should include all new SpecialService introduced and new ServiceID value.
    - Ability to select Long or Short USPS Title of either USPS or United States Postal Service.

2014-10-30
    - Updates to the USPS Production Server should include the missing quotes for: First-Class Mail Large Envelope
    - This version removes the CURLOPT_SSLVERSION setting in response to the POODLE bug.
    - This USPS module will also update the table: orders field: shipping_method to manage larger USPS shipping method names.
    - For Online quotes, and addition for: First-ClassTM Package Service has been added. This is ONLY available for Online quotes.
    - Also added is $usps_insurance_charge
          $methods[] = array('id' => $type_rebuilt,
                             'title' => $title . $show_hiddenCost,
                             'cost' => $cost,
                             'insurance' => $usps_insurance_charge,
                            );
      which could be used for creating an Order Total module to offer an optional insurance opt-out by customers.

===== USAGE NOTES =====

 1. Rates are quoted ONLY for those methods that are checked.

 2. USPS minimum Length, Width and Height are only included for obtaining International Rate Quotes. Dimensions are NOT supported by this shipping module.
 3. There are now separate settings for Domestic and International.
 4. Minimum Weight and Maximum Weight per shipping method will enable/disable the methods based on the total weight.
 5. Handling has both Global National and International fees per Box or per Order. In addition, individual Handling Fees per order can be added for each shipping method.
 6. Extra Service charges for National and International are available where applicable.
 7. Quotes can be obtained based on Retail or Online pricing.
 8. Debug logs can be saved to the /logs directory, when enabled to review quote request sent to USPS and quote received back from USPS.
 9. For US shipping, First Class has a filter to help prevent multiple First Class quotes from being displayed, due to the way USPS responds back to quote requests for multiple First Class methods.
    Enable USPS First-Class filter for US shipping. This will use the 1st quote from USPS for First-Class and skip other First-Class methods to help avoid duplicate display quotes
    NOTE: using weight minimum/maximum is usually a better method to control this
10. USPS Options for the Display Transit Times may slow down quotes
11. USPS Domestic Transit Time Calculation Mode
    a) NEW: uses whatever the new option "ShipDate" returns. This is something new added by USPS on July 28, and the way this Version J5 module implements it causes it to
       ask USPS to quote based on "ship this item today if quoting before 2pm, else ship tomorrow".
       This may affect the number of days you see, and maybe that's why you were confused about the meaning of this "NEW" option.

    b) OLD: uses the older legacy APIs (multiple separate calls to each Service) to get dates. This is how it worked before July 28.
       At this point there is no indicator whether this option will still be supported by USPS for much longer.

       NOTE: If blank from USPS, uses CUSTOM values from parseDomesticLegacyAPITransitTimeResults() in usps.php

    c) CUSTOM: uses only what's hard-coded into usps.php in the parseDomesticTransitTimeResults() function. Completely ignores whatever USPS provides.
       NOTE: Ignored for International destinations.

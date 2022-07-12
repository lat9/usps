# USPS Shipping for Zen Cart

This repository is the home of the USPS Shipping Module, supporting Zen Cart versions 1.5.6 through 1.5.7.

USPS interface API documentation: https://www.usps.com/business/web-tools-apis/documentation-updates.htm

Zen Cart download link: https://www.zen-cart.com/downloads.php?do=file&id=1292

Zen Cart support-thread link: https://www.zen-cart.com/showthread.php?227284-USPS-Shipping-Module-Support-Thread

--------------------

## Current Version: 2022-07-12 K11c

Starting with version `2022-07-10 K11b`, the module will attempt to automatically update its settings so that you no longer need to remove and re-install the module when a _small_ update is needed.

Starting with version `2021-05-05 K11a`, there is now a notification raised by the USPS shipping module to enable site-specific customizations.  A site now has additional control over whether or not a configured shipping method is enabled as well as control of the title, pricing and insurance (if enabled) cost displayed to the customer.

Refer to the in-module comments present in `/extras/includes/classes/observers/auto.usps_overrides.php` for additional information.  To use this module, you'll make your modifications and then copy that file to your site's `/includes/classes/observers/auto.usps_overrides.php`.  The module, as shipped, implements the customization provided by @Ajeh in [this](https://www.zen-cart.com/showthread.php?212699-Media-Mail-restriction-mod-to-new-3-7-14-usps-module-any-help&p=1241681) Zen Cart posting.
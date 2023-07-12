# USPS Shipping for Zen Cart

This repository is the home of the USPS Shipping Module, supporting Zen Cart versions 1.5.6 through 1.5.8.

USPS interface API documentation: https://www.usps.com/business/web-tools-apis/documentation-updates.htm

Zen Cart download link: https://www.zen-cart.com/downloads.php?do=file&id=1292

Zen Cart support-thread link: https://www.zen-cart.com/showthread.php?227284-USPS-Shipping-Module-Support-Thread

--------------------

## Current Version: 2023-07-12 K11i

This version supports the newly-introduced *USPS Ground Advantage*&trade; shipping-method and removes the *USPS Retail Ground* and various *USPS First Class* domestic shipping methods.

**On an upgrade**, if you were an early-adopter of version `2023-07-05 K11i-beta1`, an upgrade to `2023-07-12 K11i` will be automatic.  Otherwise, you'll need to capture the current module's settings, "Remove", re-"Install" and re-apply the previous settings!

Refer to the in-module comments present in `/extras/includes/classes/observers/auto.usps_overrides.php` for additional information.  To use this module, you'll make your modifications and then copy that file to your site's `/includes/classes/observers/auto.usps_overrides.php`.  The module, as shipped, implements the customization provided by @Ajeh in [this](https://www.zen-cart.com/showthread.php?212699-Media-Mail-restriction-mod-to-new-3-7-14-usps-module-any-help&p=1241681) Zen Cart posting.
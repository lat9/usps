<?php
// -----
// An auto-loaded observer that enables a site to override USPS shipping methods.
//
// Copyright (C) 2021, Vinos de Frutas Tropicales.  All rights reserved.
//
class zcObserverUspsOverrides extends base 
{
    public function __construct() 
    {
        $this->attach(
            $this,
            array(
                'NOTIFY_USPS_UPDATE_OR_DISALLOW_TYPE',
            )
        );
    }

    public function update(&$class, $eventID, $p1, &$p2, &$p3, &$p4, &$p5, &$p6, &$p7) 
    {
        switch ($eventID) {
            // -----
            // This notification, issued by /includes/modules/shipping/usps.php prior to adding a configured shipping
            // method to the list of valid shipping methods, allows a site's customizations to either disallow one or
            // more shipping-methods or to change the title/cost/insurance cost of one of the methods.
            //
            // On entry:
            //
            // $p1 ... (r/o) Contains the shipping-method to be checked.
            // $p2 ... (r/w) Identifies whether (true, the default) or not (false) the shipping method should be allowed.
            // $p3 ... (r/w) Identifies the title displayed for the shipping-method.
            // $p4 ... (r/w) Identifies the cost (a currency value, e.g. 3.49) of the shipping-method.
            // $p5 ... (r/w) Identifies the insurance cost (also a currency value) for the shipping-method.
            //
            // The example here provides the functionality originally posted in the following Zen Cart posting by @Ajeh:
            //  (https://www.zen-cart.com/showthread.php?212699-Media-Mail-restriction-mod-to-new-3-7-14-usps-module-any-help&p=1241681
            //
            case 'NOTIFY_USPS_UPDATE_OR_DISALLOW_TYPE':
                // -----
                // Disallow "Media Mail" unless all products in the cart have their master_categories_id within categories 10, 12 or 15.
                //
                if (stripos($p1, 'Media Mail') !== false) {
                    $chk_media = 0;
                    $chk_media += $_SESSION['cart']->in_cart_check('master_categories_id', '10');
                    $chk_media += $_SESSION['cart']->in_cart_check('master_categories_id', '12');
                    $chk_media += $_SESSION['cart']->in_cart_check('master_categories_id', '15');
                    if ($chk_media == $_SESSION['cart']->count_contents()) {
                        $p2 = false;
                    }
                }
                break;

            default:
                break;
        }
    }
}

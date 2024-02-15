<?php
/**
 * USPS Module for Zen Cart v1.5.6 through 1.5.8
 *
 * @version $Id: usps.php 2024-02-14 lat9 Version K11j $
 *
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

// -----
// Load and instantiate the UspsAdminMessaging class.
//
$autoLoadConfig[9999][] = [
    'autoType'  => 'class',
    'loadFile'  => 'UspsAdminMessaging.php',
    'classPath'=> DIR_FS_ADMIN . DIR_WS_CLASSES,
];
$autoLoadConfig[9999][] = [
    'autoType'   => 'classInstantiate',
    'className'  => 'UspsAdminMessaging',
    'objectName' => 'UspsAdminMessaging'
];

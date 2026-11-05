<?php
/**
 * IP2Location Country Blocker - storefront auto-loader.
 *
 * Runs the block check at breakpoint 65: after DB configuration constants (40)
 * and storefront functions incl. zen_get_ip_address()/zen_redirect() (60) are
 * available, but before sessions (70) and any template/language output.
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$autoLoadConfig[65][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_ip2location_country_blocker.php',
];

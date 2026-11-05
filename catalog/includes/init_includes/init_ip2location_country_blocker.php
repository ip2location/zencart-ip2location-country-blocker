<?php
/**
 * IP2Location Country Blocker - storefront init script (breakpoint 65).
 *
 * Performs the early country-block check before any session or page output.
 * The helper fails open at every step, so a misconfiguration never breaks the
 * storefront.
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

require_once dirname(__DIR__) . '/ip2location/class.ip2location_country_blocker.php';
ip2location_country_blocker::block_access();

<?php
/**
 * IP2Location Country Blocker - Zen Cart plugin installer.
 *
 * Creates the configuration group + settings rows, registers the admin page
 * (Tools > IP2Location Country Blocker) and the per-IP lookup cache table.
 * Idempotent: safe to run on an existing legacy install of the module.
 */
use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected function executeInstall()
    {
        $groupId = $this->getOrCreateConfigGroupId('IP2Location Country Blocker', 'IP2Location Country Blocker settings', 100);
        // Keep the group out of the stock Configuration menu: this module's
        // settings are managed from its own admin page.
        $this->updateConfigurationGroup($groupId, ['visible' => 0]);

        $configRows = [
            ['Blocking enabled', 'IP2LOCATION_COUNTRY_BLOCKER_STATUS', '0', 'Enable or disable the IP2Location country blocker.', 1],
            ['Lookup mode', 'IP2LOCATION_COUNTRY_BLOCKER_DETECTION_MODE', 'bin', 'Country lookup mode: bin = BIN database, api = IP2Location.io Web API.', 2],
            ['IP2Location.io API Key', 'IP2LOCATION_COUNTRY_BLOCKER_IO_API_KEY', '', 'Register free API key from https://www.ip2location.io/', 3],
            ['Blocked Countries', 'IP2LOCATION_COUNTRY_BLOCKER_BLOCKED_COUNTRIES', '', 'Comma-separated ISO-3166 alpha-2 country codes to block, e.g. US,CN.', 4],
            ['Block Action', 'IP2LOCATION_COUNTRY_BLOCKER_ACTION', '403', 'Block action: 403 (default page), page (custom HTML page), redirect (custom URL).', 5],
            ['Custom Block Page (HTML)', 'IP2LOCATION_COUNTRY_BLOCKER_CUSTOM_PAGE', '', 'Full HTML displayed with a 403 status for blocked visitors (used by the "page" action).', 6],
            ['Redirect URL', 'IP2LOCATION_COUNTRY_BLOCKER_REDIRECT_URL', '', 'URL blocked visitors are redirected to (used by the "redirect" action).', 7],
            ['Trust X-Forwarded-For', 'IP2LOCATION_COUNTRY_BLOCKER_TRUST_XFF', '0', 'Use the X-Forwarded-For header for the visitor IP (only when behind a trusted proxy).', 8],
            ['IP2Location Download Token', 'IP2LOCATION_COUNTRY_BLOCKER_DOWNLOAD_TOKEN', '', 'Register your free account from https://www.ip2location.com to obtain your download token.', 9],
            ['Database File (download code)', 'IP2LOCATION_COUNTRY_BLOCKER_DATABASE_FILE', 'DB1LITEBINIPV6', 'BIN database package code to download: DB1BINIPV6 (Commercial) or DB1LITEBINIPV6 (LITE).', 10],
            ['Current BIN Filename', 'IP2LOCATION_COUNTRY_BLOCKER_BIN_FILENAME', '', 'Filename of the BIN database currently in use.', 11],
            ['Last Database Update', 'IP2LOCATION_COUNTRY_BLOCKER_LAST_UPDATE', '', 'Date/time of the last BIN database update.', 12],
        ];
        foreach ($configRows as [$title, $key, $default, $description, $sortOrder]) {
            $this->addConfigurationKey($key, [
                'configuration_title' => $title,
                'configuration_value' => $default,
                'configuration_description' => $description,
                'configuration_group_id' => $groupId,
                'sort_order' => $sortOrder,
            ]);
        }

        zen_deregister_admin_pages(['ip2locationCountryBlocker']);
        zen_register_admin_page('ip2locationCountryBlocker', 'BOX_IP2LOCATION_COUNTRY_BLOCKER', 'FILENAME_IP2LOCATION_COUNTRY_BLOCKER', '', 'tools', 'Y', 90);

        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "ip2location_lookup_cache (
                ip_address   varchar(45)  NOT NULL,
                country_code varchar(2)   NOT NULL DEFAULT '',
                lookup_date  datetime     NOT NULL,
                PRIMARY KEY (ip_address)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        parent::executeInstall();
        return true;
    }

    protected function executeUninstall()
    {
        zen_deregister_admin_pages(['ip2locationCountryBlocker']);

        $this->deleteConfigurationKeys([
            'IP2LOCATION_COUNTRY_BLOCKER_STATUS',
            'IP2LOCATION_COUNTRY_BLOCKER_DETECTION_MODE',
            'IP2LOCATION_COUNTRY_BLOCKER_IO_API_KEY',
            'IP2LOCATION_COUNTRY_BLOCKER_BLOCKED_COUNTRIES',
            'IP2LOCATION_COUNTRY_BLOCKER_ACTION',
            'IP2LOCATION_COUNTRY_BLOCKER_CUSTOM_PAGE',
            'IP2LOCATION_COUNTRY_BLOCKER_REDIRECT_URL',
            'IP2LOCATION_COUNTRY_BLOCKER_TRUST_XFF',
            'IP2LOCATION_COUNTRY_BLOCKER_DOWNLOAD_TOKEN',
            'IP2LOCATION_COUNTRY_BLOCKER_DATABASE_FILE',
            'IP2LOCATION_COUNTRY_BLOCKER_BIN_FILENAME',
            'IP2LOCATION_COUNTRY_BLOCKER_LAST_UPDATE',
        ]);

        $this->executeInstallerSql('DROP TABLE IF EXISTS ' . DB_PREFIX . 'ip2location_lookup_cache');

        $this->deleteConfigurationGroup('IP2Location Country Blocker');

        parent::executeUninstall();
        return true;
    }

    protected function executeUpgrade($oldVersion)
    {
        return true;
    }
}

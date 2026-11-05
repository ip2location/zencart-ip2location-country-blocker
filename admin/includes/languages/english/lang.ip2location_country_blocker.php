<?php
/**
 * IP2Location Country Blocker - admin page language file.
 */
$define = [
    'HEADING_TITLE' => 'IP2Location Country Blocker',

    'TEXT_SETTINGS_SAVED' => 'Settings saved.',
    'TEXT_SETTINGS_FAILED' => 'Settings could not be saved.',

    // Panel: Status & Lookup Mode
    'PANEL_STATUS' => 'Status &amp; Lookup Mode',
    'LABEL_STATUS' => 'Blocking enabled',
    'LABEL_DETECTION_MODE' => 'Lookup mode',
    'LABEL_IO_API_KEY' => 'IP2Location.io API key',
    'LABEL_TRUST_XFF' => 'Trust X-Forwarded-For',
    'HELP_STATUS' => 'When disabled, no visitors are blocked.',
    'HELP_DETECTION_MODE' => 'Look up the visitor country from a downloaded BIN database, or via the IP2Location.io web API (requires an API key from ip2location.io).',
    'HELP_IO_API_KEY' => 'Register free API key from <a href="https://www.ip2location.io/" target="_blank" rel="noopener">IP2Location.io</a>',
    'HELP_TRUST_XFF' => 'Honour the X-Forwarded-For header when determining the visitor IP. Only enable when the shop is behind a trusted reverse proxy; the header is otherwise spoofable.',

    // Panel: Blocked countries
    'PANEL_COUNTRIES' => 'Blocked Countries',
    'LABEL_BLOCKED_COUNTRIES' => 'Select the countries to block',
    'COUNTRY_FILTER_PLACEHOLDER' => 'Filter countries...',
    'BUTTON_SELECT_ALL' => 'Select all',
    'BUTTON_CLEAR' => 'Clear',
    'TEXT_SELECTED_COUNT' => 'selected',

    // Panel: Block action
    'PANEL_ACTION' => 'Block Action',
    'LABEL_BLOCK_ACTION' => 'What happens when a blocked visitor arrives',
    'LABEL_CUSTOM_PAGE' => 'Custom block page (HTML)',
    'LABEL_REDIRECT_URL' => 'Redirect URL',
    'HELP_CUSTOM_PAGE' => 'Full HTML shown with a 403 status. Leave empty to fall back to the default page.',
    'HELP_REDIRECT_URL' => 'Must start with http:// or https://. Used when the action is "Redirect to URL".',

    // Panel: Database download
    'PANEL_DATABASE' => 'Database Download / Update',
    'LABEL_DOWNLOAD_TOKEN' => 'Download token',
    'LABEL_DATABASE_FILE' => 'Database file',
    'BUTTON_UPDATE_DATABASE' => 'Download &amp; Update BIN Database',
    'TEXT_CURRENT_BIN' => 'Current BIN file',
    'TEXT_LAST_UPDATE' => 'Last update',
    'TEXT_BIN_NONE' => '(none downloaded yet)',
    'TEXT_LAST_UPDATE_NONE' => '(never)',
    'HELP_DOWNLOAD_TOKEN' => 'Register your free account from <a href="https://www.ip2location.com" target="_blank" rel="noopener">IP2Location.com</a> to obtain your download token.',
    'HELP_DATABASE_FILE' => 'DB1BINIPV6 = IP2Location IP Country Database (Commercial); DB1LITEBINIPV6 = IP2Location LITE IP Country Database.',

    // Panel: Diagnose
    'PANEL_DIAGNOSE' => 'Diagnose',
    'LABEL_DIAGNOSE_IP' => 'Current IP',
    'LABEL_DIAGNOSE_COUNTRY' => 'Detected country',
    'TEXT_DIAGNOSE_UNDETECTED' => 'not detected',
    'HELP_DIAGNOSE' => 'The IP the shop sees for your current session and the country the active lookup mode resolves it to.',

    // Option labels
    'VALUE_ENABLED' => 'Enabled',
    'VALUE_DISABLED' => 'Disabled',
    'VALUE_YES' => 'Yes',
    'VALUE_NO' => 'No',
    'MODE_BIN' => 'BIN database (IP2Location Database file)',
    'MODE_API' => 'IP2Location.io Web API',
    'ACTION_403' => '403 Forbidden (default page)',
    'ACTION_PAGE' => 'Custom page',
    'ACTION_REDIRECT' => 'Redirect to URL',

    'BUTTON_SAVE' => 'Save Settings',
];
return $define;

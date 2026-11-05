<?php
/**
 * IP2Location Country Blocker - admin settings page.
 *
 * Provides the configuration UI for the storefront country blocker:
 *   - lookup mode (BIN database / IP2Location.io API)
 *   - multi-select list of blocked countries
 *   - block action (403 / custom page / redirect)
 *   - BIN database download & update using the IP2Location download token
 */
require 'includes/application_top.php';
require dirname(__DIR__) . '/catalog/includes/ip2location/class.ip2location_country_blocker.php';

function ip2location_cb_h(string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

// -----
// POST actions. The hidden securityToken field on every form is validated by
// the admin init_sessions() bootstrap (any POST carrying 'action' must match).
// -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['save', 'download'], true)) {
    if ($action === 'save') {
        $fields = [];

        $fields['IP2LOCATION_COUNTRY_BLOCKER_STATUS'] = (($_POST['status'] ?? '') === '1') ? '1' : '0';
        $fields['IP2LOCATION_COUNTRY_BLOCKER_DETECTION_MODE'] =
            in_array(($_POST['detection_mode'] ?? ''), ['bin', 'api'], true) ? $_POST['detection_mode'] : 'bin';
        $fields['IP2LOCATION_COUNTRY_BLOCKER_IO_API_KEY'] = trim((string)($_POST['io_api_key'] ?? ''));

        $blocked = [];
        foreach ((array)($_POST['blocked_countries'] ?? []) as $code) {
            $code = strtoupper(trim((string)$code));
            if (preg_match('/^[A-Z]{2}$/', $code)) {
                $blocked[$code] = $code;
            }
        }
        $fields['IP2LOCATION_COUNTRY_BLOCKER_BLOCKED_COUNTRIES'] = implode(',', array_keys($blocked));

        $fields['IP2LOCATION_COUNTRY_BLOCKER_ACTION'] =
            in_array(($_POST['block_action'] ?? ''), ['403', 'page', 'redirect'], true) ? $_POST['block_action'] : '403';
        $fields['IP2LOCATION_COUNTRY_BLOCKER_CUSTOM_PAGE'] = (string)($_POST['custom_page'] ?? '');
        $redirectUrl = trim((string)($_POST['redirect_url'] ?? ''));
        $fields['IP2LOCATION_COUNTRY_BLOCKER_REDIRECT_URL'] =
            ($redirectUrl === '' || preg_match('#^https?://#i', $redirectUrl)) ? $redirectUrl : '';
        $fields['IP2LOCATION_COUNTRY_BLOCKER_TRUST_XFF'] = (($_POST['trust_xff'] ?? '') === '1') ? '1' : '0';

        $saveFailed = false;
        foreach ($fields as $key => $value) {
            $result = $db->Execute(
                "UPDATE " . TABLE_CONFIGURATION
                . " SET configuration_value = '" . $db->prepare_input($value) . "', last_modified = NOW()"
                . " WHERE configuration_key = '" . $key . "'"
            );
            if ((int)$db->affectedRows() === 0) {
                $saveFailed = true;
            }
        }
        $messageStack->add_session(($saveFailed ? TEXT_SETTINGS_FAILED : TEXT_SETTINGS_SAVED), $saveFailed ? 'error' : 'success');
    } elseif ($action === 'download') {
        $token = (string)($_POST['download_token'] ?? '');
        $fileCode = (string)($_POST['database_file'] ?? '');
        if (!isset(ip2location_country_blocker::downloadable_databases()[$fileCode])) {
            $fileCode = 'DB1LITEBINIPV6';
        }
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $db->prepare_input($token) . "' WHERE configuration_key = 'IP2LOCATION_COUNTRY_BLOCKER_DOWNLOAD_TOKEN'"
        );
        $db->Execute(
            "UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $db->prepare_input($fileCode) . "' WHERE configuration_key = 'IP2LOCATION_COUNTRY_BLOCKER_DATABASE_FILE'"
        );

        [$ok, $msg] = ip2location_country_blocker::download_bin_database($token, $fileCode);
        if ($ok) {
            $finalName = ip2location_country_blocker::downloadable_databases()[$fileCode]['file'];
            $db->Execute(
                "UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $db->prepare_input($finalName) . "' WHERE configuration_key = 'IP2LOCATION_COUNTRY_BLOCKER_BIN_FILENAME'"
            );
            $db->Execute(
                "UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . date('Y-m-d H:i:s') . "' WHERE configuration_key = 'IP2LOCATION_COUNTRY_BLOCKER_LAST_UPDATE'"
            );
        }
        $messageStack->add_session($msg, $ok ? 'success' : 'error');
    }

    zen_redirect(zen_href_link(FILENAME_IP2LOCATION_COUNTRY_BLOCKER));
    exit(0);
}

// -----
// Load current settings (with defaults for rows the installer may not have run yet).
// -----
$config = [];
$result = $db->Execute(
    "SELECT configuration_key, configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'IP2LOCATION_COUNTRY_BLOCKER_%'"
);
foreach ($result as $row) {
    $config[$row['configuration_key']] = $row['configuration_value'];
}
$defaults = [
    'IP2LOCATION_COUNTRY_BLOCKER_STATUS' => '0',
    'IP2LOCATION_COUNTRY_BLOCKER_DETECTION_MODE' => 'bin',
    'IP2LOCATION_COUNTRY_BLOCKER_IO_API_KEY' => '',
    'IP2LOCATION_COUNTRY_BLOCKER_BLOCKED_COUNTRIES' => '',
    'IP2LOCATION_COUNTRY_BLOCKER_ACTION' => '403',
    'IP2LOCATION_COUNTRY_BLOCKER_CUSTOM_PAGE' => '',
    'IP2LOCATION_COUNTRY_BLOCKER_REDIRECT_URL' => '',
    'IP2LOCATION_COUNTRY_BLOCKER_TRUST_XFF' => '0',
    'IP2LOCATION_COUNTRY_BLOCKER_DOWNLOAD_TOKEN' => '',
    'IP2LOCATION_COUNTRY_BLOCKER_DATABASE_FILE' => 'DB1LITEBINIPV6',
    'IP2LOCATION_COUNTRY_BLOCKER_BIN_FILENAME' => '',
    'IP2LOCATION_COUNTRY_BLOCKER_LAST_UPDATE' => '',
];
foreach ($defaults as $key => $value) {
    if (!isset($config[$key])) {
        $config[$key] = $value;
    }
}

// -----
// Country list (ISO-2 codes + names) for the multi-select.
// -----
$countries = [];
$result = $db->Execute(
    "SELECT countries_iso_code_2, countries_name FROM " . TABLE_COUNTRIES . " WHERE status = 1 ORDER BY countries_name"
);
foreach ($result as $row) {
    $code = strtoupper(trim($row['countries_iso_code_2']));
    if (preg_match('/^[A-Z]{2}$/', $code)) {
        $countries[$code] = $row['countries_name'];
    }
}
$blockedMap = array_flip(ip2location_country_blocker::get_blocked_countries());

$databases = ip2location_country_blocker::downloadable_databases();
$currentBin = ($config['IP2LOCATION_COUNTRY_BLOCKER_BIN_FILENAME'] !== '') ? $config['IP2LOCATION_COUNTRY_BLOCKER_BIN_FILENAME'] : TEXT_BIN_NONE;
$lastUpdate = ($config['IP2LOCATION_COUNTRY_BLOCKER_LAST_UPDATE'] !== '') ? $config['IP2LOCATION_COUNTRY_BLOCKER_LAST_UPDATE'] : TEXT_LAST_UPDATE_NONE;
$securityToken = isset($_SESSION['securityToken']) ? $_SESSION['securityToken'] : '';

// -----
// Diagnose: the IP the shop sees for this session and the country the active
// lookup mode resolves it to.
// -----
$diagnoseIp = ip2location_country_blocker::client_ip();
$diagnoseCountry = ($diagnoseIp !== false) ? ip2location_country_blocker::detect_country((string)$diagnoseIp) : false;
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>

<div class="container-fluid">
    <h1><?php echo HEADING_TITLE; ?></h1>

    <!-- Settings -->
    <form method="post" action="<?php echo zen_href_link(FILENAME_IP2LOCATION_COUNTRY_BLOCKER); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="securityToken" value="<?php echo ip2location_cb_h($securityToken); ?>">

        <div class="panel panel-default">
            <div class="panel-heading"><strong><?php echo PANEL_STATUS; ?></strong></div>
            <div class="panel-body">
                <div class="form-group">
                    <label class="control-label" for="cb_status"><?php echo LABEL_STATUS; ?></label>
                    <select name="status" id="cb_status" class="form-control">
                        <option value="1" <?php echo ($config['IP2LOCATION_COUNTRY_BLOCKER_STATUS'] === '1') ? 'selected' : ''; ?>><?php echo VALUE_ENABLED; ?></option>
                        <option value="0" <?php echo ($config['IP2LOCATION_COUNTRY_BLOCKER_STATUS'] !== '1') ? 'selected' : ''; ?>><?php echo VALUE_DISABLED; ?></option>
                    </select>
                    <p class="help-block"><?php echo HELP_STATUS; ?></p>
                </div>
                <div class="form-group">
                    <label class="control-label" for="cb_mode"><?php echo LABEL_DETECTION_MODE; ?></label>
                    <select name="detection_mode" id="cb_mode" class="form-control">
                        <option value="bin" <?php echo ($config['IP2LOCATION_COUNTRY_BLOCKER_DETECTION_MODE'] === 'api') ? '' : 'selected'; ?>><?php echo MODE_BIN; ?></option>
                        <option value="api" <?php echo ($config['IP2LOCATION_COUNTRY_BLOCKER_DETECTION_MODE'] === 'api') ? 'selected' : ''; ?>><?php echo MODE_API; ?></option>
                    </select>
                    <p class="help-block"><?php echo HELP_DETECTION_MODE; ?></p>
                </div>
                <div class="form-group">
                    <label class="control-label" for="cb_io_api_key"><?php echo LABEL_IO_API_KEY; ?></label>
                    <div class="input-group">
                        <input type="text" name="io_api_key" id="cb_io_api_key" class="form-control" value="<?php echo ip2location_cb_h($config['IP2LOCATION_COUNTRY_BLOCKER_IO_API_KEY']); ?>" autocomplete="off">
                        <span class="input-group-btn"><button type="button" class="btn btn-default" onclick="cbToggle('cb_io_api_key', this)">&#128065;</button></span>
                    </div>
                    <p class="help-block"><?php echo HELP_IO_API_KEY; ?></p>
                </div>
                <div class="form-group">
                    <label class="control-label" for="cb_trust_xff"><?php echo LABEL_TRUST_XFF; ?></label>
                    <select name="trust_xff" id="cb_trust_xff" class="form-control">
                        <option value="0" <?php echo ($config['IP2LOCATION_COUNTRY_BLOCKER_TRUST_XFF'] === '1') ? '' : 'selected'; ?>><?php echo VALUE_NO; ?></option>
                        <option value="1" <?php echo ($config['IP2LOCATION_COUNTRY_BLOCKER_TRUST_XFF'] === '1') ? 'selected' : ''; ?>><?php echo VALUE_YES; ?></option>
                    </select>
                    <p class="help-block"><?php echo HELP_TRUST_XFF; ?></p>
                </div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong><?php echo PANEL_COUNTRIES; ?></strong></div>
            <div class="panel-body">
                <label class="control-label"><?php echo LABEL_BLOCKED_COUNTRIES; ?></label>
                <div class="row">
                    <div class="col-sm-6">
                        <input type="text" id="cb_country_filter" class="form-control" placeholder="<?php echo COUNTRY_FILTER_PLACEHOLDER; ?>" onkeyup="cbFilterCountries()">
                    </div>
                    <div class="col-sm-6 text-right">
                        <button type="button" class="btn btn-default btn-sm" onclick="cbSelectAll()"><?php echo BUTTON_SELECT_ALL; ?></button>
                        <button type="button" class="btn btn-default btn-sm" onclick="cbClearAll()"><?php echo BUTTON_CLEAR; ?></button>
                        <span class="label label-info" id="cb_count_selected">0 <?php echo TEXT_SELECTED_COUNT; ?></span>
                    </div>
                </div>
                <div style="max-height:320px;overflow:auto;border:1px solid #ddd;border-radius:4px;padding:8px;margin-top:10px;">
                    <?php foreach ($countries as $code => $name) { ?>
                        <label class="checkbox cb-country-label" data-code="<?php echo ip2location_cb_h($code); ?>" data-name="<?php echo ip2location_cb_h(strtolower($name)); ?>" style="display:block;font-weight:normal;margin-bottom:4px;">
                            <input type="checkbox" name="blocked_countries[]" value="<?php echo ip2location_cb_h($code); ?>" class="cb-country-box" <?php echo isset($blockedMap[$code]) ? 'checked' : ''; ?>>
                            <?php echo ip2location_cb_h($name); ?> <small>(<?php echo ip2location_cb_h($code); ?>)</small>
                        </label>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong><?php echo PANEL_ACTION; ?></strong></div>
            <div class="panel-body">
                <div class="form-group">
                    <label class="control-label" for="cb_block_action"><?php echo LABEL_BLOCK_ACTION; ?></label>
                    <select name="block_action" id="cb_block_action" class="form-control">
                        <option value="403" <?php echo ($config['IP2LOCATION_COUNTRY_BLOCKER_ACTION'] === '403') ? 'selected' : ''; ?>><?php echo ACTION_403; ?></option>
                        <option value="page" <?php echo ($config['IP2LOCATION_COUNTRY_BLOCKER_ACTION'] === 'page') ? 'selected' : ''; ?>><?php echo ACTION_PAGE; ?></option>
                        <option value="redirect" <?php echo ($config['IP2LOCATION_COUNTRY_BLOCKER_ACTION'] === 'redirect') ? 'selected' : ''; ?>><?php echo ACTION_REDIRECT; ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="control-label" for="cb_custom_page"><?php echo LABEL_CUSTOM_PAGE; ?></label>
                    <textarea name="custom_page" id="cb_custom_page" class="form-control" rows="8"><?php echo ip2location_cb_h($config['IP2LOCATION_COUNTRY_BLOCKER_CUSTOM_PAGE']); ?></textarea>
                    <p class="help-block"><?php echo HELP_CUSTOM_PAGE; ?></p>
                </div>
                <div class="form-group">
                    <label class="control-label" for="cb_redirect_url"><?php echo LABEL_REDIRECT_URL; ?></label>
                    <input type="text" name="redirect_url" id="cb_redirect_url" class="form-control" value="<?php echo ip2location_cb_h($config['IP2LOCATION_COUNTRY_BLOCKER_REDIRECT_URL']); ?>">
                    <p class="help-block"><?php echo HELP_REDIRECT_URL; ?></p>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-bottom: 20px;"><?php echo BUTTON_SAVE; ?></button>
    </form>

    <!-- Diagnose -->
    <div class="panel panel-default" style="margin-top:24px;">
        <div class="panel-heading"><strong><?php echo PANEL_DIAGNOSE; ?></strong></div>
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-6">
                    <strong><?php echo LABEL_DIAGNOSE_IP; ?></strong><br>
                    <?php echo ($diagnoseIp !== false) ? ip2location_cb_h((string)$diagnoseIp) : TEXT_DIAGNOSE_UNDETECTED; ?>
                </div>
                <div class="col-sm-6">
                    <strong><?php echo LABEL_DIAGNOSE_COUNTRY; ?></strong><br>
                    <?php echo ($diagnoseCountry !== false) ? ip2location_cb_h($diagnoseCountry) : TEXT_DIAGNOSE_UNDETECTED; ?>
                </div>
            </div>
            <p class="help-block" style="margin-top:10px;"><?php echo HELP_DIAGNOSE; ?></p>
        </div>
    </div>

    <!-- Database download -->
    <form method="post" action="<?php echo zen_href_link(FILENAME_IP2LOCATION_COUNTRY_BLOCKER); ?>" class="form-inline" style="margin-top:24px;">
        <input type="hidden" name="action" value="download">
        <input type="hidden" name="securityToken" value="<?php echo ip2location_cb_h($securityToken); ?>">
        <div class="panel panel-default">
            <div class="panel-heading"><strong><?php echo PANEL_DATABASE; ?></strong></div>
            <div class="panel-body">
                <div class="form-group" style="margin-right:16px;">
                    <label class="control-label" for="cb_database_file"><?php echo LABEL_DATABASE_FILE; ?></label>
                    <select name="database_file" id="cb_database_file" class="form-control">
                        <?php foreach ($databases as $code => $meta) { ?>
                            <option value="<?php echo ip2location_cb_h($code); ?>" <?php echo ($config['IP2LOCATION_COUNTRY_BLOCKER_DATABASE_FILE'] === $code) ? 'selected' : ''; ?>><?php echo ip2location_cb_h($meta['label']); ?> (<?php echo ip2location_cb_h($code); ?>)</option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group" style="margin-right:16px;">
                    <label class="control-label" for="cb_download_token"><?php echo LABEL_DOWNLOAD_TOKEN; ?></label>
                    <div class="input-group">
                        <input type="password" name="download_token" id="cb_download_token" class="form-control" value="<?php echo ip2location_cb_h($config['IP2LOCATION_COUNTRY_BLOCKER_DOWNLOAD_TOKEN']); ?>" autocomplete="off">
                        <span class="input-group-btn"><button type="button" class="btn btn-default" onclick="cbToggle('cb_download_token', this)">&#128065;</button></span>
                    </div>
                </div>
                <p>
                    <button type="submit" class="btn btn-warning" style="margin-top:24px;"><?php echo BUTTON_UPDATE_DATABASE; ?></button>
                </p>
                <p class="help-block" style="margin-top:10px;">
                    <?php echo HELP_DOWNLOAD_TOKEN; ?><br>
                    <?php echo HELP_DATABASE_FILE; ?>
                </p>
                <p class="help-block">
                    <?php echo TEXT_CURRENT_BIN; ?>: <strong><?php echo ip2location_cb_h($currentBin); ?></strong><br>
                    <?php echo TEXT_LAST_UPDATE; ?>: <strong><?php echo ip2location_cb_h($lastUpdate); ?></strong>
                </p>
            </div>
        </div>
    </form>

</div>

<script>
function cbFilterCountries() {
    var q = document.getElementById('cb_country_filter').value.toLowerCase();
    var labels = document.querySelectorAll('.cb-country-label');
    for (var i = 0; i < labels.length; i++) {
        var code = labels[i].getAttribute('data-code').toLowerCase();
        var name = labels[i].getAttribute('data-name');
        labels[i].style.display = (code.indexOf(q) !== -1 || name.indexOf(q) !== -1) ? 'block' : 'none';
    }
}
function cbSelectAll() {
    var boxes = document.querySelectorAll('.cb-country-box');
    for (var i = 0; i < boxes.length; i++) { boxes[i].checked = true; }
    cbCountSelected();
}
function cbClearAll() {
    var boxes = document.querySelectorAll('.cb-country-box');
    for (var i = 0; i < boxes.length; i++) { boxes[i].checked = false; }
    cbCountSelected();
}
function cbCountSelected() {
    var n = 0;
    var boxes = document.querySelectorAll('.cb-country-box');
    for (var i = 0; i < boxes.length; i++) { if (boxes[i].checked) n++; }
    var el = document.getElementById('cb_count_selected');
    if (el) { el.textContent = n + ' <?php echo TEXT_SELECTED_COUNT; ?>'; }
}
function cbToggle(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    el.type = (el.type === 'password') ? 'text' : 'password';
}
(function () {
    var boxes = document.querySelectorAll('.cb-country-box');
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].addEventListener('change', cbCountSelected);
    }
    cbCountSelected();
})();
</script>

<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>

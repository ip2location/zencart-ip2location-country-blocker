<?php
/**
 * IP2Location Country Blocker - shared helper class.
 *
 * Static utility class used by both the storefront blocker (init script) and the
 * admin settings page. All failures are fail-open: if a lookup cannot be performed
 * the visitor is never blocked.
 *
 * @package     ip2location_country_blocker
 * @version     1.0.0
 * @license     MIT
 */
class ip2location_country_blocker
{
    /**
     * Read a module setting. Returns the constant value when defined (Zen Cart
     * define()s every `configuration` table key), otherwise the supplied default,
     * so the module degrades gracefully before/without the installer having run.
     */
    public static function cfg(string $key, string $default = ''): string
    {
        return defined($key) ? (string)constant($key) : $default;
    }

    /**
     * Absolute path of the module's BIN data directory (trailing slash).
     * Resolved relative to this file (__DIR__) so the class works equally from
     * the legacy includes/ip2location/ location or inside a zc_plugins container.
     */
    public static function data_dir(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
    }

    /**
     * Absolute path of the BIN database file to use, or false when none is present.
     * Prefers the configured filename; falls back to the first non-SAMPLE BIN found.
     */
    public static function bin_path(): string|false
    {
        $dir = self::data_dir();
        $configured = self::cfg('IP2LOCATION_COUNTRY_BLOCKER_BIN_FILENAME');
        if ($configured !== '' && is_file($dir . $configured)) {
            return $dir . $configured;
        }
        $matches = glob($dir . 'IP2LOCATION-*.BIN');
        if (is_array($matches)) {
            foreach ($matches as $match) {
                if (stripos(basename($match), 'SAMPLE') === false) {
                    return $match;
                }
            }
        }
        return false;
    }

    /**
     * The list of country ISO-3166 alpha-2 codes currently blocked (uppercased).
     */
    public static function get_blocked_countries(): array
    {
        $raw = self::cfg('IP2LOCATION_COUNTRY_BLOCKER_BLOCKED_COUNTRIES');
        $list = [];
        foreach (explode(',', $raw) as $code) {
            $code = strtoupper(trim($code));
            if ($code !== '' && preg_match('/^[A-Z]{2}$/', $code)) {
                $list[$code] = $code;
            }
        }
        return array_values($list);
    }

    /**
     * Best-effort client IP address (validated IPv4/IPv6), or false when unusable.
     * X-Forwarded-For is only honoured when explicitly enabled, because it is
     * trivially spoofable when the server sits behind no trusted proxy.
     */
    public static function client_ip(): string|false
    {
        $ip = '';
        $trustXff = (self::cfg('IP2LOCATION_COUNTRY_BLOCKER_TRUST_XFF') === '1');
        if ($trustXff && function_exists('zen_get_ip_address')) {
            try {
                $ip = zen_get_ip_address();
            } catch (\Throwable $e) {
                $ip = '';
            }
        }
        if (trim($ip) === '' && isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $ip = trim((string)$ip);
        if ($ip === '' || $ip === '.') {
            return false;
        }
        $valid = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
        return $valid === false ? false : $valid;
    }

    /**
     * Detect the country (ISO-3166 alpha-2, uppercased) for an IP using the
     * configured lookup mode, falling back to the other mode on failure.
     * Returns false when undetectable.
     */
    public static function detect_country(string $ip): string|false
    {
        $mode = strtolower(self::cfg('IP2LOCATION_COUNTRY_BLOCKER_DETECTION_MODE', 'bin'));
        if ($mode === 'api') {
            $cc = self::country_from_api($ip);
            if ($cc !== false) {
                return $cc;
            }
            return self::country_from_bin($ip);
        }
        $cc = self::country_from_bin($ip);
        if ($cc !== false) {
            return $cc;
        }
        return self::country_from_api($ip);
    }

    /**
     * Country lookup via the IP2Location BIN database (IP2Location-PHP-Module).
     */
    public static function country_from_bin(string $ip): string|false
    {
        $bin = self::bin_path();
        if ($bin === false) {
            return false;
        }
        if (!class_exists('IP2Location\Database', false)) {
            $lib = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'Database.php';
            if (!is_file($lib)) {
                return false;
            }
            require_once $lib;
        }
        try {
            $db = new \IP2Location\Database($bin, \IP2Location\Database::FILE_IO);
            $result = $db->lookup($ip, [\IP2Location\Database::COUNTRY_CODE]);
            if (is_array($result) && isset($result['countryCode']) && $result['countryCode'] !== '' && $result['countryCode'] !== '-') {
                return strtoupper(trim($result['countryCode']));
            }
        } catch (\Throwable $e) {
            // fall through - fail open
        }
        return false;
    }

    /**
     * Country lookup via the IP2Location.io web API (ip2location-io-php library),
     * cached per-IP for 24 hours to avoid hitting the API on every page view.
     */
    public static function country_from_api(string $ip): string|false
    {
        $key = trim(self::cfg('IP2LOCATION_COUNTRY_BLOCKER_IO_API_KEY'));
        if ($key === '' || !preg_match('/^[A-Z0-9]{32}$/', $key)) {
            return false;
        }

        $cached = self::api_cache_get($ip);
        if ($cached !== null) {
            return ($cached === '-' || $cached === '') ? false : $cached;
        }

        $base = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'ip2location-io' . DIRECTORY_SEPARATOR;
        if (!class_exists('IP2LocationIO\Configuration', false)) {
            require_once $base . 'Configuration.php';
        }
        if (!class_exists('IP2LocationIO\Http', false)) {
            require_once $base . 'Http.php';
        }
        if (!class_exists('IP2LocationIO\IPGeolocation', false)) {
            require_once $base . 'IPGeolocation.php';
        }

        $country = false;
        try {
            $config = new \IP2LocationIO\Configuration($key);
            $iploc = new \IP2LocationIO\IPGeolocation($config);
            $response = $iploc->lookup($ip);
            if (isset($response->country_code) && $response->country_code !== '' && $response->country_code !== '-') {
                $country = strtoupper(trim($response->country_code));
            }
        } catch (\Throwable $e) {
            $country = false;
        }

        self::api_cache_set($ip, ($country === false) ? '-' : $country);
        return $country;
    }

    /**
     * Whether the given country code is on the block list (case-insensitive).
     */
    public static function is_blocked(string $country): bool
    {
        $country = strtoupper(trim($country));
        if ($country === '') {
            return false;
        }
        return in_array($country, self::get_blocked_countries(), true);
    }

    /**
     * Storefront entry point. Called from the early init script.
     * Fails open at every step so a misconfiguration never takes the shop down.
     */
    public static function block_access(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (self::cfg('IP2LOCATION_COUNTRY_BLOCKER_STATUS') !== '1') {
            return;
        }
        if (empty(self::get_blocked_countries())) {
            return;
        }
        $ip = self::client_ip();
        if ($ip === false) {
            return;
        }
        $country = self::detect_country($ip);
        if ($country === false) {
            return;
        }
        if (self::is_blocked($country)) {
            self::apply_block();
        }
    }

    /**
     * Apply the configured block action: 302 redirect, custom HTML page, or the
     * default 403 "Access Denied" page.
     */
    public static function apply_block(): void
    {
        $action = self::cfg('IP2LOCATION_COUNTRY_BLOCKER_ACTION', '403');

        if ($action === 'redirect') {
            $url = trim(self::cfg('IP2LOCATION_COUNTRY_BLOCKER_REDIRECT_URL'));
            if ($url !== '' && preg_match('#^https?://#i', $url) && !headers_sent()) {
                header('Location: ' . $url, true, 302);
                exit(0);
            }
            $action = '403';
        }

        if ($action === 'page') {
            $html = self::cfg('IP2LOCATION_COUNTRY_BLOCKER_CUSTOM_PAGE');
            if (trim($html) !== '') {
                http_response_code(403);
                echo $html;
                exit(0);
            }
            $action = '403';
        }

        $storeName = defined('STORE_NAME') ? STORE_NAME : 'This store';
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>' . "\n"
            . '<html lang="en"><head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="robots" content="noindex,nofollow">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<title>403 Forbidden</title>' . "\n"
            . '<style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;'
            . 'background:#f5f5f5;color:#333;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;}'
            . '.card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:40px;max-width:480px;text-align:center;'
            . 'box-shadow:0 2px 8px rgba(0,0,0,.08);}'
            . 'h1{font-size:72px;margin:0;color:#c0392b;font-weight:300;}'
            . 'h2{margin:12px 0 8px;font-size:22px;}'
            . 'p{color:#666;line-height:1.5;margin:0;}'
            . '</style>' . "\n"
            . '</head><body><div class="card">' . "\n"
            . '<h1>403</h1>' . "\n"
            . '<h2>Access Denied</h2>' . "\n"
            . '<p>Access to this website is restricted based on your location. '
            . 'If you believe this is an error, please contact the administrator.</p>' . "\n"
            . '</div></body></html>' . "\n";
        exit(0);
    }

    /**
     * The downloadable database packages offered in the admin UI.
     */
    public static function downloadable_databases(): array
    {
        return [
            'DB1LITEBINIPV6' => [
                'label' => 'IP2Location LITE IP Country Database',
                'file'  => 'IP2LOCATION-LITE-DB1-IPV6.BIN',
            ],
            'DB1BINIPV6' => [
                'label' => 'IP2Location IP Country Database (Commercial)',
                'file'  => 'IP2LOCATION-DB1.IPV6.BIN',
            ],
        ];
    }

    /**
     * Download and unpack a BIN database from the IP2Location download endpoint.
     *
     * @return array [bool $ok, string $message]
     */
    public static function download_bin_database(string $token, string $file_code): array
    {
        $token = trim($token);
        if ($token === '') {
            return [false, 'Please enter your IP2Location download token.'];
        }

        $databases = self::downloadable_databases();
        if (!isset($databases[$file_code])) {
            return [false, 'Unknown database file code: ' . $file_code];
        }
        $label = $databases[$file_code]['label'];
        $finalName = $databases[$file_code]['file'];

        $post = http_build_query(['token' => $token, 'file' => $file_code]);
        $url = 'https://www.ip2location.com/download';

        $zipData = false;
        $curlError = '';
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_USERAGENT, 'IP2Location Country Blocker');
            $zipData = curl_exec($ch);
            if ($zipData === false) {
                $curlError = (string)curl_error($ch);
            }
            curl_close($ch);
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content'       => $post,
                    'timeout'       => 180,
                    'ignore_errors' => true,
                ],
            ]);
            $zipData = @file_get_contents($url, false, $ctx);
            if ($zipData === false) {
                $curlError = 'file_get_contents failed';
            }
        }

        if ($zipData === false || $zipData === '') {
            return [false, 'Download failed' . ($curlError !== '' ? ': ' . $curlError : '.')];
        }

        // The endpoint answers with a ZIP archive when the token is valid; any
        // other response is a plain-text error (e.g. "NO PERMISSION").
        if (substr($zipData, 0, 2) !== 'PK') {
            $text = trim(strip_tags((string)$zipData));
            $text = preg_replace('/\s+/', ' ', $text);
            $firstLine = strtok($text, "\n");
            if ($firstLine === false || trim($firstLine) === '') {
                $firstLine = 'Download failed (invalid token or insufficient permission).';
            }
            return [false, trim($firstLine)];
        }

        if (!class_exists('ZipArchive')) {
            return [false, 'The PHP zip extension is not available; the BIN database cannot be extracted.'];
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'ip2l_') . '.zip';
        @file_put_contents($tmpFile, $zipData);

        $binSource = false;
        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strtoupper(substr($name, -4)) === '.BIN' && stripos($name, 'SAMPLE') === false) {
                    $binSource = $zip->getFromIndex($i);
                    break;
                }
            }
            $zip->close();
        }
        @unlink($tmpFile);

        if ($binSource === false) {
            return [false, 'No BIN database file found inside the downloaded archive.'];
        }

        $dir = self::data_dir();
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true)) {
                return [false, 'Could not create the data directory: ' . $dir];
            }
        }
        if (@file_put_contents($dir . $finalName, $binSource) === false) {
            return [false, 'Could not write the database file to ' . $dir . $finalName . ' (check directory permissions).'];
        }

        return [true, 'Database updated: ' . $finalName . ' (' . $label . ').'];
    }

    /**
     * Name of the per-IP lookup cache table (honours DB_PREFIX if set).
     */
    private static function cache_table(): string
    {
        return (defined('DB_PREFIX') ? DB_PREFIX : '') . 'ip2location_lookup_cache';
    }

    /**
     * Read a cached API lookup for an IP (within the last 24h). Returns null on
     * no cache / cache unavailable so the caller performs a live lookup.
     */
    private static function api_cache_get(string $ip): ?string
    {
        global $db;
        if (!isset($db) || !is_object($db)) {
            return null;
        }
        try {
            $safeIp = $db->prepare_input($ip);
            $result = $db->Execute(
                'SELECT country_code FROM ' . self::cache_table()
                . " WHERE ip_address = '" . $safeIp . "' AND lookup_date > (NOW() - INTERVAL 1 DAY) LIMIT 1"
            );
            if (!$result->EOF) {
                return $result->fields['country_code'];
            }
        } catch (\Throwable $e) {
            // cache unavailable - fail open
        }
        return null;
    }

    /**
     * Store an API lookup result. An unresolvable IP is stored as '-' so the API
     * is not re-queried for it on every page view.
     */
    private static function api_cache_set(string $ip, string $code): void
    {
        global $db;
        if (!isset($db) || !is_object($db)) {
            return;
        }
        try {
            $safeIp = $db->prepare_input($ip);
            $safeCode = $db->prepare_input($code);
            $db->Execute(
                'INSERT INTO ' . self::cache_table() . ' (ip_address, country_code, lookup_date)'
                . " VALUES ('" . $safeIp . "', '" . $safeCode . "', NOW())"
                . " ON DUPLICATE KEY UPDATE country_code = '" . $safeCode . "', lookup_date = NOW()"
            );
        } catch (\Throwable $e) {
            // ignore cache write failures
        }
    }
}

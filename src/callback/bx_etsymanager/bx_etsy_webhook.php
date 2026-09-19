<?php
/** --------------------------------------------------------------
 * BX Etsy Manager - Webhook Callback Endpoint
 *
 * This endpoint validates Etsy webhook signatures and writes
 * incoming events to a local log for further processing.
 * --------------------------------------------------------------
 */

chdir('../../');
require_once('includes/application_top_callback.php');

// Helper-Funktionen laden (Token, API-Request, Receipt-Upsert)
require_once(DIR_FS_CATALOG . 'admin/includes/extra/functions/bx_etsy_oauth.php');
require_once(DIR_FS_CATALOG . 'admin/includes/extra/functions/bx_etsy_general.php');
require_once(DIR_FS_CATALOG . 'api/scheduled_tasks/modules/bx_etsy_orders_sync.php');

// Im Callback-Kontext definiert application_top.php die MODULE_BX_ETSY_MANAGER_*
// Konstanten NICHT automatisch (anders als im Admin-Kontext). bx_etsy_get_valid_token()
// und bx_etsy_refresh_token() greifen aber direkt darauf zu - also hier analog zur
// bestehenden bx_etsy_callback_get_config()-Logik im OAuth-Callback nachladen.
if (!defined('MODULE_BX_ETSY_MANAGER_KEYSTRING') || !defined('MODULE_BX_ETSY_MANAGER_SHARED_SECRET')) {
    $bx_webhook_table_configuration = defined('TABLE_CONFIGURATION') ? TABLE_CONFIGURATION : 'configuration';
    $bx_webhook_cfg_query = xtc_db_query(
        "SELECT configuration_key, configuration_value FROM " . $bx_webhook_table_configuration . "
          WHERE configuration_key IN ('MODULE_BX_ETSY_MANAGER_KEYSTRING', 'MODULE_BX_ETSY_MANAGER_SHARED_SECRET')"
    );
    while ($bx_webhook_cfg_row = xtc_db_fetch_array($bx_webhook_cfg_query)) {
        if (!defined($bx_webhook_cfg_row['configuration_key'])) {
            define($bx_webhook_cfg_row['configuration_key'], (string)$bx_webhook_cfg_row['configuration_value']);
        }
    }
    unset($bx_webhook_table_configuration, $bx_webhook_cfg_query, $bx_webhook_cfg_row);
}

/**
 * Extrahiert Shop-ID und Receipt-ID aus der von Etsy gelieferten resource_url,
 * z.B. https://api.etsy.com/v3/application/shops/12345/receipts/67890
 *
 * @param string $resource_url
 * @return array{shop_id:string, receipt_id:string, path:string}
 */
function bx_etsy_webhook_parse_resource_url($resource_url) {
    $result = array('shop_id' => '', 'receipt_id' => '', 'path' => '');

    $path = (string)parse_url((string)$resource_url, PHP_URL_PATH);
    if ($path === '') {
        return $result;
    }

    // Alles ab "/v3" als relativer API-Pfad übernehmen (so wie bx_etsy_api_request() ihn erwartet).
    $v3_pos = strpos($path, '/v3');
    if ($v3_pos !== false) {
        $result['path'] = substr($path, $v3_pos + 3); // +3 = Länge von "/v3"
    }

    if (preg_match('#/shops/(\d+)/receipts/(\d+)#', $path, $matches)) {
        $result['shop_id']    = $matches[1];
        $result['receipt_id'] = $matches[2];
    }

    return $result;
}

/**
 * Sends a JSON response and terminates script execution.
 *
 * @param int $status
 * @param array $payload
 * @return void
 */
function bx_etsy_webhook_respond($status, array $payload) {
    if (!headers_sent()) {
        http_response_code((int)$status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Writes webhook log lines to shop log folder.
 *
 * @param string $level
 * @param string $message
 * @return void
 */
function bx_etsy_webhook_log($level, $message) {
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper((string)$level) . '] ' . (string)$message;

    if (defined('DIR_FS_LOG') && DIR_FS_LOG !== '') {
        $log_dir = DIR_FS_LOG;
    } elseif (defined('DIR_FS_CATALOG') && DIR_FS_CATALOG !== '') {
        $log_dir = rtrim(DIR_FS_CATALOG, '/\\') . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR;
    } else {
        $log_dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR;
    }

    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0775, true);
    }

    $log_file = $log_dir . 'bx_etsy_webhook.log';
    @error_log($line . PHP_EOL, 3, $log_file);
}

/**
 * Returns the first non-empty HTTP header value by key list.
 *
 * @param array $header_keys
 * @return string
 */
function bx_etsy_webhook_get_header(array $header_keys) {
    foreach ($header_keys as $header_key) {
        if (!is_string($header_key) || $header_key === '') {
            continue;
        }

        $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $header_key));
        if (isset($_SERVER[$server_key])) {
            $value = trim((string)$_SERVER[$server_key]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

/**
 * Loads webhook secret from configuration.
 * Priority:
 * 1) MODULE_BX_ETSY_MANAGER_WEBHOOK_SECRET
 * 2) MODULE_BX_ETSY_MANAGER_SHARED_SECRET (fallback)
 *
 * @return string
 */
function bx_etsy_webhook_get_secret() {
    $secret = '';
        $table_configuration = defined('TABLE_CONFIGURATION') ? TABLE_CONFIGURATION : 'configuration';

    $query = xtc_db_query("SELECT configuration_key, configuration_value
                                                         FROM " . $table_configuration . "
                            WHERE configuration_key IN (
                              'MODULE_BX_ETSY_MANAGER_WEBHOOK_SECRET',
                              'MODULE_BX_ETSY_MANAGER_SHARED_SECRET'
                            )");

    $values = array();
    while ($row = xtc_db_fetch_array($query)) {
        $key = (string)($row['configuration_key'] ?? '');
        $val = trim((string)($row['configuration_value'] ?? ''));
        if ($key !== '' && $val !== '') {
            $values[$key] = $val;
        }
    }

    if (!empty($values['MODULE_BX_ETSY_MANAGER_WEBHOOK_SECRET'])) {
        $secret = (string)$values['MODULE_BX_ETSY_MANAGER_WEBHOOK_SECRET'];
    } elseif (!empty($values['MODULE_BX_ETSY_MANAGER_SHARED_SECRET'])) {
        $secret = (string)$values['MODULE_BX_ETSY_MANAGER_SHARED_SECRET'];
    }

    return $secret;
}

/**
 * Decodes Etsy webhook signing secret into raw key bytes.
 * Etsy portal secret is expected as whsec_<base64>.
 *
 * @param string $secret
 * @return string
 */
function bx_etsy_webhook_decode_secret($secret) {
    $secret = trim((string)$secret);
    if ($secret === '') {
        return '';
    }

    if (strpos($secret, 'whsec_') === 0) {
        $encoded = substr($secret, 6);
        $decoded = base64_decode($encoded, true);
        return ($decoded !== false) ? $decoded : '';
    }

    $decoded = base64_decode($secret, true);
    if ($decoded !== false) {
        return $decoded;
    }

    return $secret;
}

/**
 * Verifies Etsy webhook-signature against Etsy signed content format.
 *
 * @param string $webhook_id
 * @param string $webhook_timestamp
 * @param string $raw_body
 * @param string $received_signature
 * @param string $secret
 * @return bool
 */
function bx_etsy_webhook_signature_valid($webhook_id, $webhook_timestamp, $raw_body, $received_signature, $secret) {
    $webhook_id = trim((string)$webhook_id);
    $webhook_timestamp = trim((string)$webhook_timestamp);
    $received_signature = trim((string)$received_signature);

    if ($webhook_id === '' || $webhook_timestamp === '' || $received_signature === '' || $secret === '') {
        return false;
    }

    $secret_bytes = bx_etsy_webhook_decode_secret($secret);
    if ($secret_bytes === '') {
        return false;
    }

    $signed_content = $webhook_id . '.' . $webhook_timestamp . '.' . $raw_body;
    $expected_signature = base64_encode(hash_hmac('sha256', $signed_content, $secret_bytes, true));

    $signature_candidates = preg_split('/\s*,\s*/', $received_signature);
    if (!is_array($signature_candidates)) {
        $signature_candidates = array($received_signature);
    }

    foreach ($signature_candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate !== '' && hash_equals($expected_signature, $candidate)) {
            return true;
        }
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bx_etsy_webhook_respond(405, array(
        'success' => false,
        'error' => 'Method not allowed',
    ));
}

$raw_body = file_get_contents('php://input');
if (!is_string($raw_body) || $raw_body === '') {
    bx_etsy_webhook_log('warn', 'Empty request body.');
    bx_etsy_webhook_respond(400, array(
        'success' => false,
        'error' => 'Empty payload',
    ));
}

$webhook_id = bx_etsy_webhook_get_header(array(
    'webhook-id',
));
$webhook_timestamp = bx_etsy_webhook_get_header(array(
    'webhook-timestamp',
));
$webhook_signature = bx_etsy_webhook_get_header(array(
    'webhook-signature',
));

if ($webhook_id === '' || $webhook_timestamp === '' || $webhook_signature === '') {
    bx_etsy_webhook_log('warn', 'Missing webhook headers.');
    bx_etsy_webhook_respond(400, array(
        'success' => false,
        'error' => 'Missing webhook headers',
    ));
}

if (!ctype_digit((string)$webhook_timestamp)) {
    bx_etsy_webhook_log('warn', 'Invalid webhook timestamp header.');
    bx_etsy_webhook_respond(400, array(
        'success' => false,
        'error' => 'Invalid webhook timestamp',
    ));
}

$timestamp_tolerance_seconds = 300;
$now_ts = time();
$event_ts = (int)$webhook_timestamp;
if (abs($now_ts - $event_ts) > $timestamp_tolerance_seconds) {
    bx_etsy_webhook_log('warn', 'Stale webhook timestamp.');
    bx_etsy_webhook_respond(401, array(
        'success' => false,
        'error' => 'Stale webhook timestamp',
    ));
}

$webhook_secret = bx_etsy_webhook_get_secret();
if ($webhook_secret === '') {
    bx_etsy_webhook_log('error', 'Webhook secret missing in configuration.');
    bx_etsy_webhook_respond(500, array(
        'success' => false,
        'error' => 'Webhook secret not configured',
    ));
}

if (!bx_etsy_webhook_signature_valid($webhook_id, $webhook_timestamp, $raw_body, $webhook_signature, $webhook_secret)) {
    bx_etsy_webhook_log('warn', 'Signature validation failed.');
    bx_etsy_webhook_respond(401, array(
        'success' => false,
        'error' => 'Invalid signature',
    ));
}

$event = json_decode($raw_body, true);
if (!is_array($event)) {
    bx_etsy_webhook_log('warn', 'Invalid JSON payload received.');
    bx_etsy_webhook_respond(400, array(
        'success' => false,
        'error' => 'Invalid JSON',
    ));
}

$event_type   = trim((string)($event['event_type'] ?? $event['type'] ?? 'unknown'));
$event_id     = trim((string)($event['event_id'] ?? $event['id'] ?? ''));
$resource_url = trim((string)($event['resource_url'] ?? ''));

$resource = bx_etsy_webhook_parse_resource_url($resource_url);
$shop_id     = $resource['shop_id'] !== '' ? $resource['shop_id'] : trim((string)($event['shop_id'] ?? ''));
$receipt_id  = $resource['receipt_id'] !== '' ? $resource['receipt_id'] : trim((string)($event['receipt_id'] ?? ''));

bx_etsy_webhook_log('info', 'Accepted webhook event. type=' . $event_type . ' event_id=' . ($event_id !== '' ? $event_id : 'n/a')
    . ' shop_id=' . ($shop_id !== '' ? $shop_id : 'n/a') . ' receipt_id=' . ($receipt_id !== '' ? $receipt_id : 'n/a'));

// Nur order.* Events verarbeiten wir hier (paid/canceled/shipped/delivered) - alles andere nur bestätigen.
$relevant_events = array('order.paid', 'order.canceled', 'order.shipped', 'order.delivered');

if (!in_array($event_type, $relevant_events, true) || $shop_id === '' || $receipt_id === '' || $resource['path'] === '') {
    bx_etsy_webhook_log('info', 'Event ignoriert (kein relevanter order-Typ oder unvollständige resource_url).');
    bx_etsy_webhook_respond(200, array('success' => true, 'status' => 'ignored'));
}

if (!ctype_digit($shop_id) || !function_exists('bx_etsy_get_valid_token')) {
    bx_etsy_webhook_log('error', 'ABBRUCH: ungueltige shop_id oder Helper-Funktionen fehlen.');
    // 200 statt 4xx/5xx: Etsy soll bei einem lokalen Konfigurationsfehler nicht endlos retryen.
    bx_etsy_webhook_respond(200, array('success' => false, 'status' => 'skipped_config_error'));
}

$token_data = bx_etsy_get_valid_token($shop_id);
if (!$token_data || empty($token_data['access_token'])) {
    bx_etsy_webhook_log('error', 'ABBRUCH: kein gueltiges Access Token fuer shop_id=' . $shop_id);
    bx_etsy_webhook_respond(200, array('success' => false, 'status' => 'skipped_no_token'));
}

$client_id     = trim((string)MODULE_BX_ETSY_MANAGER_KEYSTRING);
$shared_secret = trim((string)MODULE_BX_ETSY_MANAGER_SHARED_SECRET);

// Einzelnen Receipt gezielt nachladen (statt auf den naechsten Cron-Lauf zu warten).
$receipt_response = bx_etsy_api_request(
    'GET',
    $resource['path'],
    (string)$token_data['access_token'],
    $client_id,
    $shared_secret,
    null,
    15
);

if (empty($receipt_response['success']) || !isset($receipt_response['data']) || !is_array($receipt_response['data'])) {
    bx_etsy_webhook_log('error', 'Receipt-Abruf fehlgeschlagen fuer receipt_id=' . $receipt_id . ': ' . (string)($receipt_response['error'] ?? 'unbekannter Fehler'));
    // 500, damit Etsy den Webhook gemaess seinem Retry-Schema erneut zustellt (transientes API-Problem).
    bx_etsy_webhook_respond(500, array('success' => false, 'status' => 'receipt_fetch_failed'));
}

$receipt = $receipt_response['data'];

$upsert_status = function_exists('bx_etsy_orders_sync_upsert_receipt')
    ? bx_etsy_orders_sync_upsert_receipt($shop_id, $receipt)
    : 'error';

bx_etsy_webhook_log('info', 'Verarbeitung abgeschlossen. event_type=' . $event_type . ' receipt_id=' . $receipt_id . ' status=' . $upsert_status);

if ($upsert_status === 'error') {
    bx_etsy_webhook_respond(500, array('success' => false, 'status' => 'upsert_failed'));
}

bx_etsy_webhook_respond(200, array(
    'success' => true,
    'status'  => $upsert_status, // 'synced' oder 'skipped_unchanged'
));

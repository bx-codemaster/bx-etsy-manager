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

$event_type = trim((string)($event['event_type'] ?? $event['type'] ?? 'unknown'));
$event_id = trim((string)($event['event_id'] ?? $event['id'] ?? ''));
$receipt_id = trim((string)($event['receipt_id'] ?? $event['resource_id'] ?? ''));

bx_etsy_webhook_log('info', 'Accepted webhook event. type=' . $event_type . ' event_id=' . ($event_id !== '' ? $event_id : 'n/a') . ' receipt_id=' . ($receipt_id !== '' ? $receipt_id : 'n/a'));

// Initial scaffold: acknowledge quickly. Next step is queueing + receipt sync by receipt_id.
bx_etsy_webhook_respond(200, array(
    'success' => true,
    'status' => 'accepted',
));

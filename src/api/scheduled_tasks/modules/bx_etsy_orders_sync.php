<?php
/* -----------------------------------------------------------------------------------------
   BX Etsy Manager - Scheduled Task: Orders Sync

   Läuft über api/scheduled_tasks/cronjob.php und befüllt die lokale Tabelle
   bx_etsy_orders mit Etsy-Bestellungen.
   ----------------------------------------------------------------------------------------- */
defined('BX_LOGGER') || define('BX_LOGGER', false);

if (!function_exists('cron_bx_etsy_orders_sync')) {
  function cron_bx_etsy_orders_sync(): bool {

    // --- Logging-Hilfsfunktion ---
    $bx_log = function(string $level, string $msg): void {
      if (defined('BX_LOGGER') && BX_LOGGER === true ) {
        $log_dir  = defined('DIR_FS_LOG') ? DIR_FS_LOG : (defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG . 'log/' : '');
        if ($log_dir === '') {
          return;
        }
        $log_file = $log_dir . 'bx_etsy_orders_sync.log';
        $line     = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $msg . PHP_EOL;
        file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
      }
    };

    $bx_log('info', '=== cron_bx_etsy_orders_sync gestartet ===');

    if (!defined('MODULE_BX_ETSY_MANAGER_STATUS')
      || (string)constant('MODULE_BX_ETSY_MANAGER_STATUS') !== 'True'
      || !defined('MODULE_BX_ETSY_MANAGER_SCHEDULED_TASKS')
      || (string)constant('MODULE_BX_ETSY_MANAGER_SCHEDULED_TASKS') !== 'True') {
      $bx_log('warn', 'ABBRUCH: Modul/Scheduled-Tasks nicht aktiv. STATUS=' .
        (defined('MODULE_BX_ETSY_MANAGER_STATUS') ? constant('MODULE_BX_ETSY_MANAGER_STATUS') : 'NICHT_DEFINIERT') .
        ' SCHEDULED_TASKS=' .
        (defined('MODULE_BX_ETSY_MANAGER_SCHEDULED_TASKS') ? constant('MODULE_BX_ETSY_MANAGER_SCHEDULED_TASKS') : 'NICHT_DEFINIERT'));
      return true;
    }

    $helper_files = array(
      DIR_FS_CATALOG. 'admin/includes/extra/functions/bx_etsy_oauth.php',
      DIR_FS_CATALOG. 'admin/includes/extra/functions/bx_etsy_general.php',
    );

    foreach ($helper_files as $helper_file) {
      if (is_file($helper_file)) {
        require_once($helper_file);
      }
    }

    $mock_file = DIR_FS_CATALOG. 'admin/includes/extra/functions/bx_etsy_mock.php';
    if (is_file($mock_file)) {
      require_once($mock_file);
    }

    if (!function_exists('bx_etsy_get_valid_token')) {
      $bx_log('error', 'ABBRUCH: Helper-Funktion bx_etsy_get_valid_token fehlt.');
      return true;
    }

    if (!function_exists('bx_etsy_api_request') || !function_exists('bx_etsy_extract_receipt_total_amount')) {
      $bx_log('error', 'ABBRUCH: Helper-Funktion bx_etsy_api_request oder bx_etsy_extract_receipt_total_amount fehlt.');
      return true;
    }

    $shop_id       = trim((string)MODULE_BX_ETSY_MANAGER_SHOP_ID);
    $client_id     = trim((string)MODULE_BX_ETSY_MANAGER_KEYSTRING);
    $shared_secret = trim((string)MODULE_BX_ETSY_MANAGER_SHARED_SECRET);

    if ($shop_id === '' || $client_id === '' || $shared_secret === '') {
      $bx_log('error', 'ABBRUCH: Konfiguration unvollständig. shop_id=' . ($shop_id ?: 'LEER') .
        ' client_id=' . ($client_id !== '' ? 'gesetzt' : 'LEER') .
        ' shared_secret=' . ($shared_secret !== '' ? 'gesetzt' : 'LEER'));
      return true;
    }

    if (!ctype_digit($shop_id)) {
      $bx_log('error', 'ABBRUCH: shop_id ist keine Zahl: ' . $shop_id);
      return true;
    }

    if (!defined('TABLE_SCHEDULED_TASKS')) {
      $bx_log('error', 'ABBRUCH: Konstante TABLE_SCHEDULED_TASKS ist nicht definiert.');
      return true;
    }

    $bx_log('info', 'Konfiguration OK. shop_id=' . $shop_id);

    $token_data = bx_etsy_get_valid_token($shop_id);
    if (!$token_data || empty($token_data['access_token'])) {
      $bx_log('error', 'ABBRUCH: Kein gültiger OAuth-Token für shop_id=' . $shop_id . '. Token-Daten: ' . json_encode($token_data));
      return true;
    }
    $bx_log('info', 'OAuth-Token OK.');

    $lock_name  = 'bx_etsy_orders_sync_' . $shop_id;
    $lock_query = xtc_db_query("SELECT GET_LOCK('" . xtc_db_input($lock_name) . "', 0) AS lock_result");
    $lock_row   = xtc_db_fetch_array($lock_query);

    if ((string)($lock_row['lock_result'] ?? '0') !== '1') {
      $bx_log('warn', 'ABBRUCH: DB-Lock nicht erhältlich (parallele Ausführung?). Lock=' . ($lock_row['lock_result'] ?? 'NULL'));
      return true;
    }
    $bx_log('info', 'DB-Lock erhalten.');

    try {
      $table_name = 'bx_etsy_orders';
      $now        = date('Y-m-d H:i:s');

      $last_run_query = xtc_db_query("SELECT MAX(synced_at) AS last_synced_at
                                        FROM " . $table_name . "
                                       WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
      
      $last_run_row   = xtc_db_fetch_array($last_run_query);
      $last_synced_at = trim((string)($last_run_row['last_synced_at'] ?? ''));

      $count_query = xtc_db_query("SELECT COUNT(*) AS total_count
                                     FROM " . $table_name . "
                                    WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
      
      $count_row    = xtc_db_fetch_array($count_query);
      $initial_sync = ((int)($count_row['total_count'] ?? 0) === 0);

      // Delta-Fenster bewusst klein halten, um Folge-Syncs schneller zu machen.
      $delta_overlap_seconds = 15 * 60;
      $sync_started_at = $initial_sync
        ? strtotime('-90 days')
        : ($last_synced_at !== '' ? strtotime($last_synced_at) - $delta_overlap_seconds : (time() - $delta_overlap_seconds));

      if ($sync_started_at === false || $sync_started_at < 0) {
        $sync_started_at = strtotime('-2 hours');
      }

      $bx_log('info', 'Sync-Modus: ' . ($initial_sync ? 'Erstsync (letzte 90 Tage)' : 'Delta-Sync ab ' . date('Y-m-d H:i:s', $sync_started_at)) .
        ' | last_synced_at=' . ($last_synced_at ?: 'LEER'));

      $last_synced_at_ts = ($last_synced_at !== '' ? strtotime($last_synced_at) : false);
      if ($last_synced_at_ts === false) {
        $last_synced_at_ts = 0;
      }

      $access_token            = (string)$token_data['access_token'];
      $page_limit              = 100;
      $offset                  = 0;
      $synced_count            = 0;
      $processed_count         = 0;
      $skipped_unchanged_count = 0;
      $last_error              = '';
      $max_iterations          = 200; // z.B. 20.000 Datensätze als harte Obergrenze ($max_iterations 200 x $page_limit 100 = 20.000)
      $iteration               = 0;

      while (true) {
        if (++$iteration > $max_iterations) {
            $bx_log('error', 'ABBRUCH: Maximale Iterationszahl erreicht - möglicher Endlos-Loop.');
            break;
        }
        $api_path = '/application/shops/' . (int)$shop_id . '/receipts?limit=' . (int)$page_limit . '&offset=' . (int)$offset . '&was_paid=true&sort_on=created&sort_order=desc';
        $bx_log('info', 'API-Request: GET ' . $api_path);

        $response = bx_etsy_api_request(
          'GET',
          $api_path,
          $access_token,
          $client_id,
          $shared_secret,
          null,
          20
        );

        if (empty($response['success']) || !isset($response['data']) || !is_array($response['data'])) {
          $last_error = (string)($response['error'] ?? 'Unbekannter Etsy-API-Fehler');
          $bx_log('error', 'API-Fehler bei offset=' . $offset . ': ' . $last_error);
          break;
        }

        $bx_log('info', 'API-Response OK. count=' . ($response['data']['count'] ?? '?') .
          ' results=' . (isset($response['data']['results']) ? count($response['data']['results']) : '0') .
          ' offset=' . $offset);

        $payload  = $response['data'];
        $receipts = array();

        if (isset($payload['results']) && is_array($payload['results'])) {
          $receipts = $payload['results'];
        } elseif (isset($payload['receipts']) && is_array($payload['receipts'])) {
          $receipts = $payload['receipts'];
        }

        if (empty($receipts)) {
          break;
        }

        $page_receipt_ids = array();
        foreach ($receipts as $receipt) {
          if (!is_array($receipt)) {
            continue;
          }

          $page_receipt_id = isset($receipt['receipt_id']) ? trim((string)$receipt['receipt_id']) : '';
          if ($page_receipt_id !== '') {
            $page_receipt_ids[$page_receipt_id] = $page_receipt_id;
          }
        }

        $existing_receipts_map = bx_etsy_orders_sync_load_existing_receipts($table_name, $shop_id, array_values($page_receipt_ids));

        foreach ($receipts as $receipt) {
          if (!is_array($receipt)) {
            continue;
          }

          $processed_count++;

          $created_ts = bx_etsy_extract_receipt_created_ts($receipt);
          if ($created_ts > 0 && $created_ts < $sync_started_at) {
            $bx_log('info', 'Stopp: Receipt zu alt (created=' . date('Y-m-d H:i:s', $created_ts) . ' < sync_started_at=' . date('Y-m-d H:i:s', $sync_started_at) . ')');
            break 2;
          }

          $receipt_id = isset($receipt['receipt_id']) ? trim((string)$receipt['receipt_id']) : '';
          if ($receipt_id === '') {
            continue;
          }

          $existing_row  = $existing_receipts_map[$receipt_id] ?? null;
          $upsert_status = bx_etsy_orders_sync_upsert_receipt($shop_id, $receipt, $existing_row, $now);

          switch ($upsert_status) {
            case 'skipped_unchanged':
              $skipped_unchanged_count++;
              break;
            case 'error':
              $bx_log('error', 'UPSERT FEHLGESCHLAGEN: receipt_id=' . $receipt_id);
              break;
            case 'skipped_no_id':
              // bereits oben abgefangen, kommt hier praktisch nicht vor
              break;
            default: // 'synced'
              $synced_count++;
              break;
          }
        }

        if (count($receipts) < $page_limit) {
          break;
        }

        $offset += $page_limit;
      }

      $bx_log('info', '=== Sync abgeschlossen: verarbeitet=' . $processed_count . ' gespeichert=' . $synced_count . ' unveraendert_uebersprungen=' . $skipped_unchanged_count .
        ($last_error !== '' ? ' | FEHLER: ' . $last_error : '') . ' ===');

      return true;
    } catch (\Throwable $e) {
      $bx_log('error', get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
      return true;
    } finally {
      xtc_db_query("DO RELEASE_LOCK('" . xtc_db_input($lock_name) . "')");
    }
  }
}

if (!function_exists('bx_etsy_orders_sync_upsert_receipt')) {
  /**
   * Mappt einen einzelnen Etsy-Receipt auf die lokale Tabelle bx_etsy_orders
   * und schreibt ihn per UPSERT. Wird sowohl vom Cron-Delta-Sync (bulk, mit
   * vorab geladener $existing_row zur Unchanged-Erkennung) als auch vom
   * Webhook-Handler (Einzel-Receipt, $existing_row optional) genutzt, damit
   * beide Aufrufer garantiert dieselbe Mapping-/Upsert-Logik verwenden.
   *
   * @param string $shop_id
   * @param array $receipt Rohes Receipt-Objekt aus der Etsy-API (als Array)
   * @param array|null $existing_row Vorab geladene bestehende Zeile (receipt_id, payload_json, etsy_updated_at) oder null
   * @param string|null $now Zeitstempel für synced_at/created_at/updated_at (Standard: jetzt)
   * @return string 'synced' | 'skipped_unchanged' | 'skipped_no_id' | 'error'
   */
  function bx_etsy_orders_sync_upsert_receipt(string $shop_id, array $receipt, ?array $existing_row = null, ?string $now = null): string
  {
    $table_name = 'bx_etsy_orders';
    $now        = $now ?? date('Y-m-d H:i:s');

    $receipt_id = isset($receipt['receipt_id']) ? trim((string)$receipt['receipt_id']) : '';
    if ($receipt_id === '') {
      return 'skipped_no_id';
    }

    $created_ts       = bx_etsy_extract_receipt_created_ts($receipt);
    $order_created_at = $created_ts > 0 ? date('Y-m-d H:i:s', $created_ts) : $now;
    $paid_at_ts       = bx_etsy_orders_sync_pick_timestamp($receipt, array('create_timestamp', 'created_timestamp'));
    $paid_at          = $paid_at_ts > 0 ? date('Y-m-d H:i:s', $paid_at_ts) : null;

    $buyer_name = isset($receipt['name']) ? trim((string)$receipt['name']) : '';
    if ($buyer_name === '') {
      $buyer_name = 'Etsy Buyer #' . $receipt_id;
    }

    $buyer_email = isset($receipt['buyer_email']) ? trim((string)$receipt['buyer_email']) : '';

    $currency_code = '';
    if (isset($receipt['grandtotal']) && is_array($receipt['grandtotal']) && isset($receipt['grandtotal']['currency_code'])) {
      $currency_code = strtoupper(trim((string)$receipt['grandtotal']['currency_code']));
    }

    if (strlen($currency_code) !== 3) {
      $currency_code = 'EUR';
    }

    $grand_total    = (float)bx_etsy_extract_receipt_total_amount($receipt);
    $payment_status = isset($receipt['status']) ? trim((string)$receipt['status']) : '';
    if ($payment_status === '') {
      $payment_status = 'paid';
    }

    $order_status = $payment_status;
    $is_shipped   = !empty($receipt['is_shipped']) ? 1 : 0;

    $etsy_updated_at_ts = bx_etsy_orders_sync_pick_timestamp($receipt, array('updated_timestamp', 'updated_at', 'last_modified_timestamp'));
    $etsy_updated_at = $etsy_updated_at_ts > 0 ? date('Y-m-d H:i:s', $etsy_updated_at_ts) : null;

    $payload_wrapper = array(
      'receipt' => $receipt,
      'guest_order_context' => function_exists('bx_etsy_build_guest_order_context')
        ? bx_etsy_build_guest_order_context($receipt, $shop_id)
        : array(),
    );

    $payload_json = json_encode($payload_wrapper, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload_json === false) {
      $payload_json = '{}';
    }

    if (is_array($existing_row)) {
      $existing_payload_json = (string)($existing_row['payload_json'] ?? '');
      $existing_etsy_updated_at = trim((string)($existing_row['etsy_updated_at'] ?? ''));

      $same_payload    = ($existing_payload_json !== '' && $existing_payload_json === $payload_json);
      $same_updated_at = ($etsy_updated_at !== null
                       && $existing_etsy_updated_at !== ''
                       && $existing_etsy_updated_at === $etsy_updated_at);

      if ($same_payload || $same_updated_at) {
        return 'skipped_unchanged';
      }
    }

    $sync_data = array(
      'shop_id'              => $shop_id,
      'etsy_order_id'        => $receipt_id,
      'receipt_id'           => $receipt_id,
      'order_created_at'     => $order_created_at,
      'paid_at'              => $paid_at,
      'buyer_name'           => $buyer_name,
      'buyer_email'          => ($buyer_email !== '' ? $buyer_email : null),
      'currency_code'        => $currency_code,
      'grand_total_gross'    => number_format($grand_total, 4, '.', ''),
      'payment_status'       => $payment_status,
      'order_status'         => $order_status,
      'is_shipped'           => $is_shipped,
      'invoice_number'       => '',
      'order_reference_shop' => (string)$shop_id,
      'etsy_updated_at'      => $etsy_updated_at,
      'synced_at'            => $now,
      'sync_state'           => 'ok',
      'sync_error_message'   => null,
      'payload_json'         => $payload_json,
      'created_at'           => $now,
      'updated_at'           => $now,
    );

    $insert_sql = "INSERT INTO " . $table_name . " (
                      shop_id,
                      etsy_order_id,
                      receipt_id,
                      order_created_at,
                      paid_at,
                      buyer_name,
                      buyer_email,
                      currency_code,
                      grand_total_gross,
                      payment_status,
                      order_status,
                      is_shipped,
                      invoice_number,
                      order_reference_shop,
                      etsy_updated_at,
                      synced_at,
                      sync_state,
                      sync_error_message,
                      payload_json,
                      created_at,
                      updated_at
                    ) VALUES (
                      '" . xtc_db_input($sync_data['shop_id']) . "',
                      '" . xtc_db_input($sync_data['etsy_order_id']) . "',
                      '" . xtc_db_input($sync_data['receipt_id']) . "',
                      '" . xtc_db_input($sync_data['order_created_at']) . "',
                      " . ($sync_data['paid_at'] !== null ? "'" . xtc_db_input($sync_data['paid_at']) . "'" : 'NULL') . ",
                      '" . xtc_db_input($sync_data['buyer_name']) . "',
                      " . ($sync_data['buyer_email'] !== null ? "'" . xtc_db_input($sync_data['buyer_email']) . "'" : 'NULL') . ",
                      '" . xtc_db_input($sync_data['currency_code']) . "',
                      '" . xtc_db_input($sync_data['grand_total_gross']) . "',
                      '" . xtc_db_input($sync_data['payment_status']) . "',
                      '" . xtc_db_input($sync_data['order_status']) . "',
                      " . (int)$sync_data['is_shipped'] . ",
                      " . ($sync_data['invoice_number'] !== '' ? "'" . xtc_db_input($sync_data['invoice_number']) . "'" : 'NULL') . ",
                      '" . xtc_db_input($sync_data['order_reference_shop']) . "',
                      " . ($sync_data['etsy_updated_at'] !== null ? "'" . xtc_db_input($sync_data['etsy_updated_at']) . "'" : 'NULL') . ",
                      '" . xtc_db_input($sync_data['synced_at']) . "',
                      '" . xtc_db_input($sync_data['sync_state']) . "',
                      '" . xtc_db_input($sync_data['sync_error_message']) . "',
                      '" . xtc_db_input($sync_data['payload_json']) . "',
                      '" . xtc_db_input($sync_data['created_at']) . "',
                      '" . xtc_db_input($sync_data['updated_at']) . "'
                    ) ON DUPLICATE KEY UPDATE
                      receipt_id = VALUES(receipt_id),
                      order_created_at = VALUES(order_created_at),
                      paid_at = VALUES(paid_at),
                      buyer_name = VALUES(buyer_name),
                      buyer_email = VALUES(buyer_email),
                      currency_code = VALUES(currency_code),
                      grand_total_gross = VALUES(grand_total_gross),
                      payment_status = VALUES(payment_status),
                      order_status = VALUES(order_status),
                      is_shipped = VALUES(is_shipped),
                      invoice_number = VALUES(invoice_number),
                      order_reference_shop = VALUES(order_reference_shop),
                      etsy_updated_at = VALUES(etsy_updated_at),
                      synced_at = VALUES(synced_at),
                      sync_state = VALUES(sync_state),
                      sync_error_message = VALUES(sync_error_message),
                      payload_json = VALUES(payload_json),
                      updated_at = VALUES(updated_at)";

    return xtc_db_query($insert_sql) ? 'synced' : 'error';
  }
}

if (!function_exists('bx_etsy_orders_sync_load_existing_receipts')) {
  /**
   * Lädt bestehende Receipts in einem Query, um unveränderte Datensätze
   * ohne UPSERT überspringen zu können.
   *
   * @param string $table_name
   * @param string $shop_id
   * @param array $receipt_ids
   * @return array
   */
  function bx_etsy_orders_sync_load_existing_receipts(string $table_name, string $shop_id, array $receipt_ids): array {
    $map = array();

    if (empty($receipt_ids)) {
      return $map;
    }

    $in_values = array();
    foreach ($receipt_ids as $receipt_id) {
      $receipt_id = trim((string)$receipt_id);
      if ($receipt_id === '') {
        continue;
      }

      $in_values[] = "'" . xtc_db_input($receipt_id) . "'";
    }

    if (empty($in_values)) {
      return $map;
    }

    $sql = "SELECT receipt_id, payload_json, etsy_updated_at\n"
         . "  FROM " . $table_name . "\n"
         . " WHERE shop_id = '" . xtc_db_input($shop_id) . "'\n"
         . "   AND receipt_id IN (" . implode(', ', $in_values) . ")";

    $query = xtc_db_query($sql);
    while ($row = xtc_db_fetch_array($query)) {
      $row_receipt_id = trim((string)($row['receipt_id'] ?? ''));
      if ($row_receipt_id === '') {
        continue;
      }

      $map[$row_receipt_id] = $row;
    }

    return $map;
  }
}

if (!function_exists('bx_etsy_orders_sync_pick_timestamp')) {
  function bx_etsy_orders_sync_pick_timestamp(array $source, array $keys): int {
    foreach ($keys as $key) {
      if (!isset($source[$key])) {
        continue;
      }

      $value = $source[$key];
      if (is_numeric($value)) {
        return (int)$value;
      }

      if (is_string($value)) {
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
          return (int)$timestamp;
        }
      }
    }

    return 0;
  }
}
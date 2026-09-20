<?php
/* ----------------------------------------------------------------------------------------------
   $Id: admin/includes/extra/functions/bx_etsy_general.php 1000 2026-02-03 13:00:00Z benax $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   ----------------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ----------------------------------------------------------------------------------------------*/

/**
 * Wandelt API-Responses (Objekt/Resource) in ein Array um.
 *
 * @return int
 * Legt selbst KEINEN Datenbankeintrag an (das übernimmt nur bx_etsy_save_orders_per_page_from_request()).
 *
 * @return int
 */
function bx_etsy_get_orders_per_page(): int
{
    $default = defined('MAX_DISPLAY_LIST_ETSY') && (int)constant('MAX_DISPLAY_LIST_ETSY') > 0
        ? (int)constant('MAX_DISPLAY_LIST_ETSY')
        : 10;

    $result = xtc_db_query(
        "SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MAX_DISPLAY_LIST_ETSY'"
    );

    if ($result && xtc_db_num_rows($result) > 0) {
        $row = xtc_db_fetch_array($result);
        $value = (int)($row['configuration_value'] ?? 0);
        if ($value > 0) {
            return $value;
        }
    }

    return $default;
}

/**
 * Speichert einen per POST übergebenen "Anzeige pro Seite"-Wert (falls vorhanden und gültig)
 * in der configuration-Tabelle und gibt den nun gültigen Wert zurück.
 *
 * @return int
 * @return int
 */
function bx_etsy_save_orders_per_page_from_request(): int
{
    if (!isset($_REQUEST['MAX_DISPLAY_LIST_ETSY'])) {
        return bx_etsy_get_orders_per_page();
    }

    $configuration_value = preg_replace('/[^0-9-]/', '', (string)$_REQUEST['MAX_DISPLAY_LIST_ETSY']);
    $configuration_value = (int)$configuration_value;

    if ($configuration_value < 1) {
        return bx_etsy_get_orders_per_page();
    }
    if ($configuration_value > 200) {
        $configuration_value = 200; // sinnvolle Obergrenze
    }

    $existing = xtc_db_query(
        "SELECT configuration_key FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MAX_DISPLAY_LIST_ETSY'"
    );

    if ($existing && xtc_db_num_rows($existing) > 0) {
        xtc_db_query(
            "UPDATE " . TABLE_CONFIGURATION . "
                SET configuration_value = '" . xtc_db_input((string)$configuration_value) . "',
                    last_modified = NOW()
              WHERE configuration_key = 'MAX_DISPLAY_LIST_ETSY'"
        );
    } elseif (function_exists('xtc_db_perform')) {
        xtc_db_perform(TABLE_CONFIGURATION, array(
            'configuration_key'      => 'MAX_DISPLAY_LIST_ETSY',
            'configuration_value'    => $configuration_value,
            'configuration_group_id' => '1000',
            'sort_order'             => '-1',
            'last_modified'          => 'now()',
            'date_added'             => 'now()',
        ));
    } else {
        xtc_db_query(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_key, configuration_value, configuration_group_id, sort_order, date_added, last_modified, use_function, set_function)
             VALUES
                ('MAX_DISPLAY_LIST_ETSY', '" . xtc_db_input((string)$configuration_value) . "', '1000', '-1', NOW(), NOW(), '', '')"
        );
    }

    return $configuration_value;
}

/**
 * Wandelt API-Responses (Objekt/Resource) in ein Array um.
 *
 * @param mixed $response
 * @return array
 */
function bx_etsy_normalize_response_to_array($response) {
    if (is_object($response) && method_exists($response, 'toArray')) {
        return $response->toArray();
    }

    if (is_array($response)) {
        return $response;
    }

    if (is_object($response)) {
        $encoded = json_encode($response);
        if ($encoded !== false) {
            $decoded = json_decode($encoded, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }

    return array();
}

/**
 * Direkter Etsy API Request als Fallback, falls bestimmte SDK-Resources fehlen.
 *
 * @param string $method HTTP-Methode (GET/POST/...)
 * @param string $path API-Pfad ab /v3
 * @param string $access_token OAuth Access Token
 * @param string $client_id Etsy Keystring
 * @param string $shared_secret Etsy Shared Secret
 * @param array|null $payload Optionales JSON-Payload
 * @param int $timeout_seconds Request-Timeout in Sekunden
 * @return array ['success'=>bool, 'data'=>array, 'error'=>string]
 */
function bx_etsy_api_request($method, $path, $access_token, $client_id, $shared_secret, $payload = null, $timeout_seconds = 30) {
    if (!function_exists('curl_init')) {
        return array(
            'success' => false,
            'data'    => array(),
            'error'   => 'cURL ist auf dem Server nicht verfügbar.'
        );
    }
    
    // MOCK MODE ABFANGEN
    if (function_exists('bx_etsy_mock_enabled') && bx_etsy_mock_enabled()) {
        return bx_etsy_mock_response($method, $path, $payload);
    }

    $url = 'https://api.etsy.com/v3' . $path;
    $method = strtoupper((string)$method);
    $timeout_seconds = (int)$timeout_seconds;

    if ($timeout_seconds <= 0) {
      $timeout_seconds = 30;
    }

    $connect_timeout_seconds = (int)min(5, max(1, $timeout_seconds));

    $headers = array(
      'Accept: application/json',
      'Authorization: Bearer ' . $access_token,
      'x-api-key: ' . $client_id . ':' . $shared_secret
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout_seconds);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout_seconds);

    if ($payload !== null) {
        $json_payload = json_encode($payload);
        if ($json_payload === false) {
            return array(
                'success' => false,
                'data' => array(),
                'error' => 'Payload konnte nicht als JSON codiert werden.'
            );
        }

        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw = curl_exec($ch);
    if ($raw === false) {
      $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);

      if (defined('CURLE_OPERATION_TIMEDOUT') && $curl_errno === CURLE_OPERATION_TIMEDOUT) {
        $curl_error = 'Timeout nach ' . $timeout_seconds . ' Sekunden';
      }

      return array(
        'success' => false,
        'data'    => array(),
        'error'   => 'cURL-Fehler: ' . $curl_error
      );
    }

    $http_code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $decoded = array();
    }

    if ($http_code >= 200 && $http_code < 300) {
        return array(
            'success' => true,
            'data'    => $decoded,
            'error'   => ''
        );
    }

    $error_message = '';
    if (isset($decoded['error']) && is_string($decoded['error'])) {
        $error_message = $decoded['error'];
    }

    if ($error_message === '') {
        $error_message = 'HTTP ' . $http_code . ' von Etsy API';
    }

    return array(
        'success' => false,
        'data'    => $decoded,
        'error'   => $error_message
    );
}

/**
 * Liest die Personalisierung eines Listings.
 * Nutzt SDK-Resource, wenn vorhanden, sonst direkten API-Fallback.
 *
 * @param int $shop_id
 * @param int $listing_id
 * @param string $access_token
 * @param string $client_id
 * @param string $shared_secret
 * @return array ['success'=>bool, 'data'=>array, 'error'=>string]
 */
function bx_etsy_get_listing_personalization($shop_id, $listing_id, $access_token, $client_id, $shared_secret) {
    try {
        if (class_exists('Etsy\\Resources\\ListingPersonalization') && class_exists('Etsy\\Etsy') && (!function_exists('bx_etsy_mock_enabled') || !bx_etsy_mock_enabled())) {
            new \Etsy\Etsy($client_id, $shared_secret, $access_token);
            $result = \Etsy\Resources\ListingPersonalization::get((int)$shop_id, (int)$listing_id);

            return array(
                'success' => true,
                'data'    => bx_etsy_normalize_response_to_array($result),
                'error'   => ''
            );
        }
    } catch (Exception $e) {
        error_log('BX Etsy: SDK-Personalisierung GET fehlgeschlagen, wechsle auf API-Fallback: ' . $e->getMessage());
    }

    return bx_etsy_api_request(
        'GET',
        '/application/listings/' . (int)$listing_id . '/personalization',
        $access_token,
        $client_id,
        $shared_secret
    );
}

/**
 * Aktualisiert die Personalisierung eines Listings.
 * Nutzt SDK-Resource, wenn vorhanden, sonst direkten API-Fallback.
 *
 * @param int $shop_id
 * @param int $listing_id
 * @param array $payload
 * @param string $access_token
 * @param string $client_id
 * @param string $shared_secret
 * @return array ['success'=>bool, 'data'=>array, 'error'=>string]
 */
function bx_etsy_update_listing_personalization($shop_id, $listing_id, array $payload, $access_token, $client_id, $shared_secret) {
    try {
        if (class_exists('Etsy\\Resources\\ListingPersonalization') && class_exists('Etsy\\Etsy') && (!function_exists('bx_etsy_mock_enabled') || !bx_etsy_mock_enabled())) {
            new \Etsy\Etsy($client_id, $shared_secret, $access_token);
            $result = \Etsy\Resources\ListingPersonalization::update((int)$shop_id, (int)$listing_id, $payload, true);

            return array(
                'success' => true,
                'data'    => bx_etsy_normalize_response_to_array($result),
                'error'   => ''
            );
        }
    } catch (Exception $e) {
        error_log('BX Etsy: SDK-Personalisierung UPDATE fehlgeschlagen, wechsle auf API-Fallback: ' . $e->getMessage());
    }

    return bx_etsy_api_request(
        'POST',
        '/application/shops/' . (int)$shop_id . '/listings/' . (int)$listing_id . '/personalization?supports_multiple_personalization_questions=true',
        $access_token,
        $client_id,
        $shared_secret,
        $payload
    );
}

/**
 * Extrahiert einen Dezimalbetrag aus unterschiedlichen Etsy-Response-Formaten.
 *
 * @param array $receipt
 * @return float
 */
if (!function_exists('bx_etsy_extract_receipt_total_amount')) {
  function bx_etsy_extract_receipt_total_amount(array $receipt): int|float
  {
    $money_candidates = array('grandtotal', 'total_price', 'total_amount');

    foreach ($money_candidates as $candidate_key) {
      if (!isset($receipt[$candidate_key])) {
        continue;
      }

      $value = $receipt[$candidate_key];

      if (is_array($value) && isset($value['amount'])) {
        $amount  = (float)$value['amount'];
        $divisor = isset($value['divisor']) ? (float)$value['divisor'] : 100.0;

        if ($divisor > 0) {
          return $amount / $divisor;
        }

        return $amount;
      }

      if (is_numeric($value)) {
        return (float)$value;
      }
    }

    return 0.0;
  }
}

/**
 * Extrahiert den Waehrungscode aus unterschiedlichen Etsy-Receipt-Formaten.
 *
 * @param array $receipt
 * @return string
 */
if (!function_exists('bx_etsy_receipt_currency_code')) {
  function bx_etsy_receipt_currency_code(array $receipt): string
  {
    $currency_code = isset($receipt['currency_code']) ? strtoupper(trim((string)$receipt['currency_code'])) : '';
    if ($currency_code !== '') {
      return $currency_code;
    }

    foreach (array('grandtotal', 'total_price', 'total_amount') as $candidate_key) {
      if (!isset($receipt[$candidate_key]) || !is_array($receipt[$candidate_key])) {
        continue;
      }

      $nested_currency = isset($receipt[$candidate_key]['currency_code'])
        ? strtoupper(trim((string)$receipt[$candidate_key]['currency_code']))
        : '';
      if ($nested_currency !== '') {
        return $nested_currency;
      }
    }

    return '';
  }
}

/**
 * Extrahiert den Erstellzeitpunkt eines Receipts als Unix-Timestamp.
 *
 * @param array $receipt
 * @return int
 */
if (!function_exists('bx_etsy_extract_receipt_created_ts')) {
  function bx_etsy_extract_receipt_created_ts(array $receipt): int
  {
    if (isset($receipt['create_timestamp'])) {
      return (int)$receipt['create_timestamp'];
    }

    if (isset($receipt['created_timestamp'])) {
      return (int)$receipt['created_timestamp'];
    }

    if (isset($receipt['created'])) {
      return (int)$receipt['created'];
    }

    return 0;
  }
}

/**
 * Gibt die im Receipt enthaltenen Transaktionen zurück.
 *
 * @param array $receipt
 * @return array
 */
if (!function_exists('bx_etsy_extract_receipt_transactions')) {
  function bx_etsy_extract_receipt_transactions(array $receipt): array
  {
    if (isset($receipt['transactions']) && is_array($receipt['transactions'])) {
      return $receipt['transactions'];
    }

    if (isset($receipt['receipt_transactions']) && is_array($receipt['receipt_transactions'])) {
      return $receipt['receipt_transactions'];
    }

    return array();
  }
}

/**
 * Baut einen normalisierten Gastbestell-Kontext aus einem Etsy-Receipt.
 *
 * @param array $receipt
 * @param string $shop_id
 * @return array
 */
if (!function_exists('bx_etsy_build_guest_order_context')) {
  function bx_etsy_build_guest_order_context(array $receipt, string $shop_id = ''): array
  {
    $transactions = bx_etsy_extract_receipt_transactions($receipt);
    $items = array();
    $item_count = 0;
    $quantity_total = 0;

    foreach ($transactions as $transaction) {
      if (!is_array($transaction)) {
        continue;
      }

      $title = bx_etsy_extract_transaction_title($transaction);
      $quantity_candidates = array('quantity', 'quantity_purchased', 'qty', 'count');
      $quantity = 1;

      foreach ($quantity_candidates as $candidate_key) {
        if (isset($transaction[$candidate_key]) && is_numeric($transaction[$candidate_key])) {
          $quantity = max(1, (int)$transaction[$candidate_key]);
          break;
        }
      }

      $items[] = array(
        'title'          => $title,
        'quantity'       => $quantity,
        'listing_id'     => isset($transaction['listing_id']) && is_numeric($transaction['listing_id']) ? (int)$transaction['listing_id'] : 0,
        'transaction_id' => isset($transaction['transaction_id']) && is_numeric($transaction['transaction_id']) ? (int)$transaction['transaction_id'] : 0,
        'sku'            => isset($transaction['sku']) ? trim((string)$transaction['sku']) : '',
        'price'          => bx_etsy_extract_transaction_amount($transaction),
        'shipping_cost'  => (isset($transaction['shipping_cost']) && is_array($transaction['shipping_cost']) && isset($transaction['shipping_cost']['amount']))
          ? (float)bx_etsy_extract_transaction_amount(array('price' => $transaction['shipping_cost']))
          : 0.0,
      );

      $item_count++;
      $quantity_total += $quantity;
    }

    $contact_data = array(
      'name' => isset($receipt['name']) ? trim((string)$receipt['name']) : '',
      'first_line' => isset($receipt['first_line']) ? trim((string)$receipt['first_line']) : '',
      'second_line' => isset($receipt['second_line']) ? trim((string)$receipt['second_line']) : '',
      'city' => isset($receipt['city']) ? trim((string)$receipt['city']) : '',
      'state' => isset($receipt['state']) ? trim((string)$receipt['state']) : '',
      'zip' => isset($receipt['zip']) ? trim((string)$receipt['zip']) : '',
      'country_iso' => isset($receipt['country_iso']) ? strtoupper(trim((string)$receipt['country_iso'])) : '',
      'formatted_address' => isset($receipt['formatted_address']) ? trim((string)$receipt['formatted_address']) : '',
    );

    if ($contact_data['formatted_address'] === '') {
      $address_parts = array();

      if ($contact_data['name'] !== '') {
        $address_parts[] = $contact_data['name'];
      }

      if ($contact_data['first_line'] !== '') {
        $address_parts[] = $contact_data['first_line'];
      }

      if ($contact_data['second_line'] !== '') {
        $address_parts[] = $contact_data['second_line'];
      }

      $city_line = trim($contact_data['zip'] . ' ' . $contact_data['city']);
      if ($city_line !== '') {
        $address_parts[] = $city_line;
      }

      $region_line = trim($contact_data['state'] . ' ' . $contact_data['country_iso']);
      if ($region_line !== '') {
        $address_parts[] = $region_line;
      }

      $contact_data['formatted_address'] = implode(', ', $address_parts);
    }

    $shipments = array();
    if (isset($receipt['shipments']) && is_array($receipt['shipments'])) {
      foreach ($receipt['shipments'] as $shipment) {
        if (!is_array($shipment)) {
          continue;
        }

        $shipments[] = array(
          'tracking_code' => isset($shipment['tracking_code']) ? trim((string)$shipment['tracking_code']) : '',
          'carrier_name' => isset($shipment['carrier_name']) ? trim((string)$shipment['carrier_name']) : '',
          'mail_class' => isset($shipment['mail_class']) ? trim((string)$shipment['mail_class']) : '',
          'ship_date' => isset($shipment['ship_date']) ? trim((string)$shipment['ship_date']) : '',
          'is_purchased' => !empty($shipment['is_purchased']),
        );
      }
    }

    return array(
      'source'        => 'etsy',
      'shop_id'       => ($shop_id !== '' && ctype_digit($shop_id)) ? (int)$shop_id : null,
      'receipt_id'    => isset($receipt['receipt_id']) ? (string)$receipt['receipt_id'] : '',
      'etsy_order_id' => isset($receipt['receipt_id']) ? (string)$receipt['receipt_id'] : '',
      'buyer_user_id' => (isset($receipt['buyer_user_id']) && is_numeric($receipt['buyer_user_id'])) ? (int)$receipt['buyer_user_id'] : 0,
      'buyer_name'    => isset($receipt['name']) ? trim((string)$receipt['name']) : '',
      'buyer_email'   => isset($receipt['buyer_email']) ? trim((string)$receipt['buyer_email']) : '',
      'contact'       => $contact_data,
      'payment'       => array(
        'status'        => isset($receipt['status']) ? trim((string)$receipt['status']) : '',
        'method'        => isset($receipt['payment_method']) ? trim((string)$receipt['payment_method']) : '',
        'payment_email' => isset($receipt['payment_email']) ? trim((string)$receipt['payment_email']) : '',
      ),
      'gift' => array(
        'is_gift'       => !empty($receipt['is_gift']),
        'gift_message'  => isset($receipt['gift_message']) ? trim((string)$receipt['gift_message']) : '',
        'gift_sender'   => isset($receipt['gift_sender']) ? trim((string)$receipt['gift_sender']) : '',
      ),
      'messages' => array(
        'from_buyer'    => isset($receipt['message_from_buyer']) ? trim((string)$receipt['message_from_buyer']) : '',
        'from_seller'   => isset($receipt['message_from_seller']) ? trim((string)$receipt['message_from_seller']) : '',
        'from_payment'  => isset($receipt['message_from_payment']) ? trim((string)$receipt['message_from_payment']) : '',
      ),
      'shipping' => array(
        'is_paid'        => !empty($receipt['is_paid']),
        'is_shipped'     => !empty($receipt['is_shipped']),
        'is_delivered'   => !empty($receipt['is_delivered']),
        'shipments'      => $shipments,
        'shipment_count' => count($shipments),
      ),
      'totals' => array(
        'grand_total'   => (float)bx_etsy_extract_receipt_total_amount($receipt),
        'currency_code' => bx_etsy_receipt_currency_code($receipt),
      ),
      'items'          => $items,
      'item_count'     => $item_count,
      'quantity_total' => $quantity_total,
      'address'        => $contact_data,
      'raw_receipt'    => $receipt,
    );
  }
}
 
/**
 * Ermittelt einen Listing-Titel aus einer Etsy-Transaktion.
 *
 * @param array $transaction
 * @return string
 */
if (!function_exists('bx_etsy_extract_transaction_title')) {
  function bx_etsy_extract_transaction_title(array $transaction): string
  {
    if (isset($transaction['title']) && is_string($transaction['title'])) {
      $title = trim($transaction['title']);
      if ($title !== '') {
        return $title;
      }
    }

    if (isset($transaction['listing_id'])) {
      return 'Listing #' . (int)$transaction['listing_id'];
    }

    return 'Unbekanntes Listing';
  }
}

/**
 * Ermittelt den Transaktionsbetrag als Dezimalwert.
 *
 * @param array $transaction
 * @return float
 */
if (!function_exists('bx_etsy_extract_transaction_amount')) {
  function bx_etsy_extract_transaction_amount(array $transaction): float
  {
    $money_candidates = array('price');

    foreach ($money_candidates as $candidate_key) {
      if (!isset($transaction[$candidate_key])) {
        continue;
      }

      $value = $transaction[$candidate_key];

      if (is_array($value) && isset($value['amount'])) {
        $amount = (float)$value['amount'];
        $divisor = isset($value['divisor']) ? (float)$value['divisor'] : 100.0;

        if ($divisor > 0) {
          return $amount / $divisor;
        }

        return $amount;
      }

      if (is_numeric($value)) {
        return (float)$value;
      }
    }

    return 0.0;
  }
}

/**
 * Liefert die Dashboard-KPI-Daten aus der lokalen Tabelle bx_etsy_orders,
 * die per Webhook (Echtzeit) und Cron-Delta-Sync (Sicherheitsnetz) aktuell
 * gehalten wird - kein Live-Scan über die Etsy-API mehr.
 *
 * Einzige Ausnahme: "Neue Bewertungen heute" gibt es aktuell nicht lokal
 * synchronisiert (keine Reviews-Tabelle/Sync), das kommt daher weiterhin
 * per einzelnem Live-Request von Etsy - leicht gecacht, da nicht zeitkritisch.
 *
 * Der Verbindungsstatus (connection_active) ist bewusst unabhängig von den
 * KPI-Werten: Auch wenn die Etsy-Verbindung gerade unterbrochen ist, bleiben
 * die bereits synchronisierten lokalen Zahlen sichtbar, statt das ganze
 * Dashboard leerzuräumen.
 *
 * @return array
 */
if (!function_exists('bx_etsy_get_dashboard_kpis')) {
  function bx_etsy_get_dashboard_kpis(): array
  {
    global $modified_cache;

    $result = array(
      'revenue_today'      => null,
      'revenue_week'       => null,
      'revenue_month'      => null,
      'new_orders_today'   => null,
      'unshipped_count'    => null,
      'new_reviews_today'  => null,
      'avg_order_today'    => null,
      'top_listings'       => array(),
      'low_performers'     => array(),
      'connection_active'  => false,
      'data_fetch_success' => false,
      'last_error'         => '',
      'last_synced_at'     => null,
    );

    $shop_id = trim((string)MODULE_BX_ETSY_MANAGER_SHOP_ID);

    if ($shop_id === '' || !ctype_digit($shop_id)) {
      return $result;
    }

    $is_mock_mode = function_exists('bx_etsy_mock_enabled') && bx_etsy_mock_enabled();

    // Verbindungsstatus ist ein eigenständiges Signal (siehe Docblock oben),
    // getrennt von den lokal berechneten KPI-Zahlen weiter unten.
    $result['connection_active'] = $is_mock_mode
      || (function_exists('bx_etsy_is_connected') && bx_etsy_is_connected($shop_id));

    $shop_id_sql   = xtc_db_input($shop_id);
    $today_start   = date('Y-m-d 00:00:00');
    $week_start_ts = strtotime('monday this week 00:00:00');
    $week_start    = ($week_start_ts !== false) ? date('Y-m-d H:i:s', $week_start_ts) : $today_start;
    $month_start   = date('Y-m-01 00:00:00');

    // --- Ein Aggregat-Query für Umsatz heute/Woche/Monat, Bestellungen heute,
    //     unversendete Bestellungen und den Zeitpunkt der letzten Synchronisation ---
    $agg_sql = "SELECT
                    COALESCE(SUM(CASE WHEN order_created_at >= '" . xtc_db_input($today_start) . "' THEN grand_total_gross ELSE 0 END), 0) AS revenue_today,
                    COALESCE(SUM(CASE WHEN order_created_at >= '" . xtc_db_input($today_start) . "' THEN 1 ELSE 0 END), 0) AS orders_today,
                    COALESCE(SUM(CASE WHEN order_created_at >= '" . xtc_db_input($week_start) . "' THEN grand_total_gross ELSE 0 END), 0) AS revenue_week,
                    COALESCE(SUM(CASE WHEN order_created_at >= '" . xtc_db_input($month_start) . "' THEN grand_total_gross ELSE 0 END), 0) AS revenue_month,
                    COALESCE(SUM(CASE WHEN is_shipped = 0 AND LOWER(payment_status) != 'canceled' THEN 1 ELSE 0 END), 0) AS unshipped_count,
                    MAX(synced_at) AS last_synced_at,
                    COUNT(*) AS total_rows
                 FROM bx_etsy_orders
                WHERE shop_id = '" . $shop_id_sql . "'";

    $agg_query = xtc_db_query($agg_sql);

    if ($agg_query === false) {
      $result['last_error'] = 'Lokale KPI-Abfrage fehlgeschlagen (SQL-Fehler). Details siehe Server-Errorlog.';
      return $result;
    }

    $agg_row = xtc_db_fetch_array($agg_query);

    $result['data_fetch_success'] = true;
    $result['revenue_today']      = (float)($agg_row['revenue_today'] ?? 0.0);
    $result['revenue_week']       = (float)($agg_row['revenue_week'] ?? 0.0);
    $result['revenue_month']      = (float)($agg_row['revenue_month'] ?? 0.0);
    $result['new_orders_today']   = (int)($agg_row['orders_today'] ?? 0);
    $result['unshipped_count']    = (int)($agg_row['unshipped_count'] ?? 0);
    $result['last_synced_at']     = trim((string)($agg_row['last_synced_at'] ?? '')) ?: null;
    $result['avg_order_today']    = ($result['new_orders_today'] > 0)
      ? ($result['revenue_today'] / $result['new_orders_today'])
      : 0.0;

    // --- Top-/Low-Performer-Listings: Transaktionen aus payload_json der
    //     Bestellungen des laufenden Monats auswerten (lokal, kein API-Call). ---
    $month_orders_query = xtc_db_query(
      "SELECT payload_json FROM bx_etsy_orders
        WHERE shop_id = '" . $shop_id_sql . "'
          AND order_created_at >= '" . xtc_db_input($month_start) . "'"
    );

    $listing_revenue_map = array();
    $listing_sales_map   = array();

    if ($month_orders_query !== false) {
      while ($order_row = xtc_db_fetch_array($month_orders_query)) {
        $decoded = json_decode((string)($order_row['payload_json'] ?? ''), true);
        $receipt = (is_array($decoded) && isset($decoded['receipt']) && is_array($decoded['receipt']))
          ? $decoded['receipt']
          : array();

        if (empty($receipt)) {
          continue;
        }

        $transactions = bx_etsy_extract_receipt_transactions($receipt);
        foreach ($transactions as $transaction) {
          if (!is_array($transaction)) {
            continue;
          }

          $listing_title = bx_etsy_extract_transaction_title($transaction);
          $transaction_amount = bx_etsy_extract_transaction_amount($transaction);

          $listing_revenue_map[$listing_title] = ($listing_revenue_map[$listing_title] ?? 0.0) + $transaction_amount;
          $listing_sales_map[$listing_title]   = ($listing_sales_map[$listing_title] ?? 0) + 1;
        }
      }
    }

    if (!empty($listing_revenue_map)) {
      arsort($listing_revenue_map, SORT_NUMERIC);
      $top_listings = array();
      $top_counter  = 0;

      foreach ($listing_revenue_map as $listing_title => $listing_revenue) {
        $top_listings[] = array('title' => (string)$listing_title, 'revenue' => (float)$listing_revenue);
        $top_counter++;
        if ($top_counter >= 5) {
          break;
        }
      }

      $result['top_listings'] = $top_listings;
    }

    if (!empty($listing_sales_map)) {
      asort($listing_sales_map, SORT_NUMERIC);
      $low_performers = array();
      $low_counter    = 0;

      foreach ($listing_sales_map as $listing_title => $sales_count) {
        $low_performers[] = array('title' => (string)$listing_title, 'value' => (int)$sales_count);
        $low_counter++;
        if ($low_counter >= 5) {
          break;
        }
      }

      $result['low_performers'] = $low_performers;
    }

    // --- Neue Bewertungen heute: einziger verbleibender Live-Call, da es
    //     dafür noch keine lokale Synchronisation gibt. Kurz gecacht, damit
    //     nicht jeder Dashboard-Aufruf einen Etsy-Request auslöst. ---
    $result['new_reviews_today'] = bx_etsy_get_new_reviews_today_cached($shop_id, $is_mock_mode, $result['connection_active']);

    return $result;
  }
}

/**
 * Holt die Anzahl neuer Etsy-Bewertungen heute (einziger verbleibender
 * Live-API-Call im Dashboard) und cached das Ergebnis kurz, um nicht bei
 * jedem Seitenaufruf einen Etsy-Request auszulösen.
 *
 * @param string $shop_id
 * @param bool $is_mock_mode
 * @param bool $connection_active
 * @return int|null
 */
if (!function_exists('bx_etsy_get_new_reviews_today_cached')) {
  function bx_etsy_get_new_reviews_today_cached(string $shop_id, bool $is_mock_mode, bool $connection_active): ?int
  {
    global $modified_cache;

    if (!$is_mock_mode && !$connection_active) {
      return null;
    }

    $cache_minutes = defined('MODULE_BX_ETSY_MANAGER_DASHBOARD_CACHE_MINUTES')
      ? (int)constant('MODULE_BX_ETSY_MANAGER_DASHBOARD_CACHE_MINUTES')
      : 15;
    if ($cache_minutes < 0) {
      $cache_minutes = 0;
    }
    $cache_ttl_seconds = $is_mock_mode ? 0 : ($cache_minutes * 60);

    $cache_key     = 'bx_etsy_dashboard_reviews_today_v1_' . $shop_id . '_' . ($is_mock_mode ? 'mock' : 'live');
    $cache_backend = null;

    if ($cache_ttl_seconds > 0 && defined('DB_CACHE') && DB_CACHE == 'true') {
      if (!is_object($modified_cache) && defined('DIR_FS_CATALOG')) {
        include_once(DIR_FS_CATALOG . 'includes/modified_cache.php');
      }

      if (is_object($modified_cache)) {
        $cache_backend = $modified_cache;
        $cache_backend->setId($cache_key);

        if ($cache_backend->isHit() !== false) {
          $cached_value = $cache_backend->get();
          if ($cached_value !== null) {
            return (int)$cached_value;
          }
        }
      }
    }

    $access_token = 'mock_access_token';

    if (!$is_mock_mode) {
      if (!function_exists('bx_etsy_get_valid_token')) {
        return null;
      }

      $token_data = bx_etsy_get_valid_token($shop_id);
      if (!$token_data || empty($token_data['access_token'])) {
        return null;
      }

      $access_token = (string)$token_data['access_token'];
    }

    $client_id      = (string)MODULE_BX_ETSY_MANAGER_KEYSTRING;
    $shared_secret  = (string)MODULE_BX_ETSY_MANAGER_SHARED_SECRET;
    $request_timeout = defined('MODULE_BX_ETSY_MANAGER_DASHBOARD_REQUEST_TIMEOUT')
      ? (int)constant('MODULE_BX_ETSY_MANAGER_DASHBOARD_REQUEST_TIMEOUT')
      : 8;
    if ($request_timeout < 3) {
      $request_timeout = 3;
    } elseif ($request_timeout > 30) {
      $request_timeout = 30;
    }

    $today_start_ts = strtotime(date('Y-m-d 00:00:00'));
    if ($today_start_ts === false) {
      $today_start_ts = time();
    }

    $reviews_response = bx_etsy_api_request(
      'GET',
      '/application/shops/' . (int)$shop_id . '/reviews?limit=100&min_created=' . (int)$today_start_ts,
      $access_token,
      $client_id,
      $shared_secret,
      null,
      $request_timeout
    );

    if (empty($reviews_response['success']) || !isset($reviews_response['data']) || !is_array($reviews_response['data'])) {
      return null;
    }

    $payload = $reviews_response['data'];
    $count   = null;

    if (isset($payload['count']) && is_numeric($payload['count'])) {
      $count = (int)$payload['count'];
    } elseif (isset($payload['results']) && is_array($payload['results'])) {
      $count = count($payload['results']);
    } elseif (isset($payload['reviews']) && is_array($payload['reviews'])) {
      $count = count($payload['reviews']);
    }

    if ($count !== null && $cache_ttl_seconds > 0 && is_object($cache_backend)) {
      $cache_backend->setId($cache_key);
      $cache_backend->set($count, (int)$cache_ttl_seconds);
      $cache_backend->setTags(array('bx_etsy_manager', 'bx_etsy_dashboard_reviews'));
    }

    return $count;
  }
}

/**
 * Baut die Dashboard-Daten für die Admin-Ansicht zusammen.
 *
 * KPIs kommen aus der lokalen Tabelle bx_etsy_orders (siehe
 * bx_etsy_get_dashboard_kpis()) und sind daher auch dann sichtbar, wenn die
 * Etsy-Verbindung gerade unterbrochen ist - der Verbindungsstatus wird
 * separat als Hinweistext ausgewiesen, statt das Dashboard leerzuräumen.
 *
 * @return array Assoziatives Array mit KPIs, Top Listings, Low Performers und Aktivitäten
 */
function bx_etsy_build_dashboard_data() {
  global $xtPrice;
  $kpis = bx_etsy_get_dashboard_kpis();

  $format_currency = static function ($amount) use (&$xtPrice) {
    if (is_object($xtPrice) && method_exists($xtPrice, 'xtcFormatCurrency')) {
      return (string)$xtPrice->xtcFormatCurrency((float)$amount);
    }

    return number_format((float)$amount, 2, ',', '.');
  };

  $connection_active = !empty($kpis['connection_active']);
  $last_error        = trim((string)($kpis['last_error'] ?? ''));
  $last_synced_at     = $kpis['last_synced_at'] ?? null;

  // Shop konfiguriert und lokale Abfrage erfolgreich? Dann gibt es echte Zahlen,
  // unabhaengig vom aktuellen Live-Verbindungsstatus (siehe Docblock).
  $shop_configured = ($kpis['revenue_today'] !== null);

  $connection_hint = '';
  if ($shop_configured) {
    $connection_hint = $connection_active
      ? ($last_synced_at !== null ? ' (zuletzt synchronisiert: ' . date('d.m.Y H:i', strtotime($last_synced_at)) . ')' : '')
      : ' (Etsy-Verbindung derzeit getrennt - zeigt zuletzt synchronisierte Werte' . ($last_synced_at !== null ? ' vom ' . date('d.m.Y H:i', strtotime($last_synced_at)) : '') . ')';
  }

  $not_configured_note = 'Kein Etsy-Shop konfiguriert bzw. lokale Daten nicht verfügbar.';

  // --- Umsatz heute ---
  $revenue_today_value = $shop_configured ? $format_currency((float)$kpis['revenue_today']) : '-';
  $orders_today        = (int)($kpis['new_orders_today'] ?? 0);
  $avg_order_today     = (float)($kpis['avg_order_today'] ?? 0.0);

  if (!$shop_configured) {
    $revenue_today_note = $not_configured_note;
  } elseif ($orders_today > 0) {
    $revenue_today_note = $orders_today . ' Bestellungen, durchschnittlich ' . $format_currency($avg_order_today) . $connection_hint;
  } else {
    $revenue_today_note = 'Heute bisher keine bezahlten Etsy-Bestellungen.' . $connection_hint;
  }

  // --- Neue Bestellungen heute ---
  $new_orders_today_value = $shop_configured ? (string)$orders_today : '-';
  if (!$shop_configured) {
    $new_orders_today_note = $not_configured_note;
  } elseif ($orders_today > 0) {
    $new_orders_today_note = 'Aus lokaler Synchronisation (heute)' . $connection_hint;
  } else {
    $new_orders_today_note = 'Heute bisher keine neuen Etsy-Bestellungen.' . $connection_hint;
  }

  // --- Unversendete Bestellungen ---
  $unshipped_count = (int)($kpis['unshipped_count'] ?? 0);
  $unshipped_count_value = $shop_configured ? (string)$unshipped_count : '-';
  if (!$shop_configured) {
    $unshipped_count_note = $not_configured_note;
  } elseif ($unshipped_count > 0) {
    $unshipped_count_note = 'Offene, bezahlte Etsy-Bestellungen' . $connection_hint;
  } else {
    $unshipped_count_note = 'Aktuell keine offenen, bezahlten Sendungen.' . $connection_hint;
  }

  // --- Neue Bewertungen heute (einziger noch live abgefragter Wert) ---
  $new_reviews_value = $shop_configured ? '0' : '-';
  $new_reviews_note  = $shop_configured
    ? ($connection_active ? 'Heute bisher keine neuen Etsy-Bewertungen.' : 'Etsy-Verbindung derzeit getrennt.')
    : $not_configured_note;

  if ($kpis['new_reviews_today'] !== null) {
    $new_reviews_value = (string)(int)$kpis['new_reviews_today'];
    $new_reviews_note  = ((int)$kpis['new_reviews_today'] > 0)
      ? 'Live aus Etsy-Reviews (heute)'
      : 'Heute bisher keine neuen Etsy-Bewertungen.';
  }

  // --- Top-/Low-Performer-Listings + Aktivitäten-Feed ---
  $top_listings   = array();
  $low_performers = array();
  $activities     = array();

  if ($shop_configured) {
    if (!empty($kpis['top_listings'])) {
      foreach ($kpis['top_listings'] as $top_listing) {
        $top_listings[] = array(
          'title' => (string)$top_listing['title'],
          'value' => $format_currency((float)$top_listing['revenue']),
        );
      }
    } else {
      $top_listings[] = array('title' => 'Im aktuellen Monat noch keine umsatzstarken Listings', 'value' => '-');
    }

    if (!empty($kpis['low_performers']) && is_array($kpis['low_performers'])) {
      foreach ($kpis['low_performers'] as $low_performer) {
        $low_performers[] = array(
          'title' => (string)($low_performer['title'] ?? 'Unbekanntes Listing'),
          'value' => (string)((int)($low_performer['value'] ?? 0)),
        );
      }
    } else {
      $low_performers[] = array('title' => 'Im aktuellen Monat liegen noch keine Listing-Verkäufe vor', 'value' => '0');
    }

    $top_count = !empty($kpis['top_listings']) ? (int)count($kpis['top_listings']) : 0;

    if ($orders_today === 0 && $unshipped_count === 0 && $top_count === 0) {
      $activities[] = array(
        'time' => date('d.m.Y H:i'),
        'type' => 'Sync',
        'text' => 'Lokale Daten aktuell. Aktuell liegen für heute keine Bestellungen/Umsätze vor.' . $connection_hint,
      );
    } else {
      $activities[] = array(
        'time' => date('d.m.Y H:i'),
        'type' => 'Sync',
        'text' => 'Lokale Daten aktuell: Heute ' . $orders_today . ' Bestellungen, ' . $unshipped_count . ' unversendet, ' . $top_count . ' Top-Listings mit Umsatz.' . $connection_hint,
      );
    }

    if (!$connection_active) {
      $activities[] = array(
        'time' => '-',
        'type' => 'Verbindung',
        'text' => 'Etsy-Verbindung derzeit getrennt. Neue Bestellungen können bis zur Wiederverbindung nicht mehr synchronisiert werden.',
      );
    }
  } elseif ($last_error !== '') {
    $top_listings[]   = array('title' => 'Lokale Daten nicht verfügbar', 'value' => '-');
    $low_performers[] = array('title' => 'Lokale Daten nicht verfügbar', 'value' => '-');
    $activities[]     = array('time' => '-', 'type' => 'Fehler', 'text' => $last_error);
  } else {
    $top_listings[]   = array('title' => 'Kein Etsy-Shop konfiguriert', 'value' => '-');
    $low_performers[] = array('title' => 'Kein Etsy-Shop konfiguriert', 'value' => '-');
    $activities[]     = array('time' => '-', 'type' => 'Konfiguration', 'text' => 'Bitte Shop-ID und Zugangsdaten im Modul konfigurieren.');
  }

  $revenue_week_value  = $shop_configured ? $format_currency((float)$kpis['revenue_week'])  : '-';
  $revenue_week_note   = $shop_configured ? ('Seit Wochenbeginn' . $connection_hint) : $not_configured_note;
  $revenue_month_value = $shop_configured ? $format_currency((float)$kpis['revenue_month']) : '-';
  $revenue_month_note  = $shop_configured ? ('Seit Monatsbeginn' . $connection_hint) : $not_configured_note;

  return array(
    'kpis' => array(
      array(
        'label' => 'Umsatz heute',
        'value' => $revenue_today_value,
        'note'  => $revenue_today_note,
      ),
      array(
        'label' => 'Umsatz Woche',
        'value' => $revenue_week_value,
        'note'  => $revenue_week_note,
      ),
      array(
        'label' => 'Umsatz Monat',
        'value' => $revenue_month_value,
        'note'  => $revenue_month_note,
      ),
      array(
        'label' => 'Neue Bestellungen',
        'value' => $new_orders_today_value,
        'note'  => $new_orders_today_note,
      ),
      array(
        'label' => 'Unversendet',
        'value' => $unshipped_count_value,
        'note'  => $unshipped_count_note,
      ),
      array(
        'label' => 'Neue Bewertungen',
        'value' => $new_reviews_value,
        'note'  => $new_reviews_note,
      ),
    ),
    'top_listings'   => $top_listings,
    'low_performers' => $low_performers,
    'activities'     => $activities,
  );
}

/**
 * Konfigurationseingabefeld für die Modulversion (read-only)
 */
if (!function_exists('bx_configuration_field_version')) {
  function bx_configuration_field_version(string $value, string $constant): string {
    return xtc_draw_input_field( 'configuration['.$constant.']', $value, 'readonly="true" style="opacity: 0.4;"');
  }
}

<?php
/* ----------------------------------------------------------------------------------------------
   $Id: admin/includes/extra/functions/bx_etsy_render.php 1000 2026-07-16 10:00:00Z benax $

   BX Etsy Manager - Tab-Rendering

   Zentrale Render-Funktionen fuer die vier Admin-Tabs (Dashboard/Bestellungen/Listings/Support).
   Jede Funktion ist bewusst selbststaendig (laedt Config/Daten selbst statt sich auf globale
   Variablen zu verlassen), damit sie sowohl beim normalen Seitenaufruf (admin/bx_etsymanager.php)
   als auch beim AJAX-Lazy-Load (extra/ajax/bx_etsymanager.php, method=load_tab) identisches HTML
   liefert, ohne Logik doppelt zu pflegen.

   modified eCommerce Shopsoftware
   http://www.modified-shop.org
   ----------------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ----------------------------------------------------------------------------------------------*/

/*
ETSY RESPONSE EXAMPLE (bx_etsy_decode_order_payload_json() erwartet ein receipt-Objekt oder direktes Etsy-Receipt):
{
  "count": 0,
  "results": [
    {
      "receipt_id": 1,
      "receipt_type": 0,
      "seller_user_id": 1,
      "seller_email": "user@example.com",
      "buyer_user_id": 1,
      "buyer_email": "string",
      "name": "string",
      "first_line": "string",
      "second_line": "string",
      "city": "string",
      "state": "string",
      "zip": "string",
      "status": "paid",
      "formatted_address": "string",
      "country_iso": "string",
      "payment_method": "string",
      "payment_email": "string",
      "message_from_seller": "string",
      "message_from_buyer": "string",
      "message_from_payment": "string",
      "is_paid": true,
      "is_shipped": true,
      "create_timestamp": 946684800,
      "created_timestamp": 946684800,
      "update_timestamp": 946684800,
      "updated_timestamp": 946684800,
      "is_gift": true,
      "gift_message": "string",
      "gift_sender": "string",
      "grandtotal": {
        "amount": 0,
        "divisor": 0,
        "currency_code": "string"
      },
      "subtotal": {
        "amount": 0,
        "divisor": 0,
        "currency_code": "string"
      },
      "total_price": {
        "amount": 0,
        "divisor": 0,
        "currency_code": "string"
      },
      "total_shipping_cost": {
        "amount": 0,
        "divisor": 0,
        "currency_code": "string"
      },
      "total_tax_cost": {
        "amount": 0,
        "divisor": 0,
        "currency_code": "string"
      },
      "total_vat_cost": {
        "amount": 0,
        "divisor": 0,
        "currency_code": "string"
      },
      "discount_amt": {
        "amount": 0,
        "divisor": 0,
        "currency_code": "string"
      },
      "gift_wrap_price": {
        "amount": 0,
        "divisor": 0,
        "currency_code": "string"
      },
      "shipments": [
        {
          "receipt_shipping_id": 1,
          "shipment_notification_timestamp": 946684800,
          "carrier_name": "string",
          "tracking_code": "string"
        }
      ],
      "transactions": [
        {
          "transaction_id": 1,
          "title": "string",
          "description": "string",
          "seller_user_id": 1,
          "buyer_user_id": 1,
          "create_timestamp": 946684800,
          "created_timestamp": 946684800,
          "paid_timestamp": 946684800,
          "shipped_timestamp": 946684800,
          "quantity": 0,
          "listing_image_id": 1,
          "receipt_id": 1,
          "is_digital": true,
          "file_data": "string",
          "listing_id": 0,
          "transaction_type": "string",
          "product_id": 1,
          "sku": "string",
          "price": {
            "amount": 0,
            "divisor": 0,
            "currency_code": "string"
          },
          "shipping_cost": {
            "amount": 0,
            "divisor": 0,
            "currency_code": "string"
          },
          "variations": [
            {
              "property_id": 0,
              "value_id": 0,
              "formatted_name": "string",
              "formatted_value": "string",
              "question_id": 0
            }
          ],
          "product_data": [
            {
              "property_id": 1,
              "property_name": "string",
              "scale_id": 1,
              "scale_name": "string",
              "value_ids": [
                1
              ],
              "values": [
                "string"
              ]
            }
          ]
          "shipping_profile_id": 1,
          "min_processing_days": 0,
          "max_processing_days": 0,
          "shipping_method": "string",
          "shipping_upgrade": "string",
          "expected_ship_date": 946684800,
          "buyer_coupon": 0,
          "shop_coupon": 0
        }
      ],
      "refunds": [
        {
          "amount": {
            "amount": 0,
            "divisor": 0,
            "currency_code": "string"
          },
          "created_timestamp": 946684800,
          "reason": "string",
          "note_from_issuer": "string",
          "status": "string"
        }
      ]
    }
  ]
}
*/

/*
{
  "receipt_id": 4117291123,
  "receipt_type": 0,
  "seller_user_id": 289703759,
  "seller_email": "axel.benkert@online-power.de",
  "buyer_user_id": 1264971458,
  "buyer_email": null,
  "name": "Rolf Hasemeyer",
  "first_line": "Helgoländer Str. 28",
  "second_line": "",
  "city": "Wuppertal",
  "state": "",
  "zip": "42287",
  "status": "Completed",
  "formatted_address": "Rolf Hasemeyer\nHelgoländer Str. 28\n42287 WUPPERTAL\nGermany",
  "country_iso": "DE",
  "payment_method": "cc",
  "payment_email": null,
  "message_from_payment": null,
  "message_from_seller": "",
  "message_from_buyer": null,
  "is_shipped": true,
  "is_paid": true,
  "create_timestamp": 1784082920,
  "created_timestamp": 1784082920,
  "update_timestamp": 1784113965,
  "updated_timestamp": 1784113965,
  "is_gift": false,
  "gift_message": "",
  "gift_sender": "",
  "grandtotal": {
    "amount": 1867,
    "divisor": 100,
    "currency_code": "EUR"
  },
  "subtotal": {
    "amount": 1867,
    "divisor": 100,
    "currency_code": "EUR"
  },
  "total_price": {
    "amount": 2075,
    "divisor": 100,
    "currency_code": "EUR"
  },
  "total_shipping_cost": {
    "amount": 0,
    "divisor": 100,
    "currency_code": "EUR"
  },
  "total_tax_cost": {
    "amount": 0,
    "divisor": 100,
    "currency_code": "EUR"
  },
  "total_vat_cost": {
    "amount": 0,
    "divisor": 100,
    "currency_code": "EUR"
  },
  "discount_amt": {
    "amount": 208,
    "divisor": 100,
    "currency_code": "EUR"
  },
  "gift_wrap_price": {
    "amount": 0,
    "divisor": 100,
    "currency_code": "EUR"
  },
  "shipments": [
    {
      "receipt_shipping_id": 1499891665490,
      "shipment_notification_timestamp": 1784113965,
      "carrier_name": "DHL Germany",
      "tracking_code": "00340434626839404910"
    }
  ],
  "transactions": [
    {
      "transaction_id": 5140980728,
      "title": "Motorbike with sidecar M18",
      "description": "Hand-welded model made of iron, galvanised with bronze.\n\nPlease note: Each model is handmade and therefore unique.\n\nDifferences may also occur within a model series.\n\nThe toy figure shown is not part of the offer. It only serves to give an impression of the model size.\n\nThe model measures approx. 14.0 x 9.0 x 11.0 cm and weighs approx. 370 grams.",
      "seller_user_id": 289703759,
      "buyer_user_id": 1264971458,
      "create_timestamp": 1784082920,
      "created_timestamp": 1784082920,
      "paid_timestamp": 1784082920,
      "shipped_timestamp": 1784113965,
      "quantity": 1,
      "listing_image_id": 1234567890,
      "receipt_id": 4117291123,
      "is_digital": false,
      "file_data": "",
      "listing_id": 9876543210,
      "transaction_type": "physical",
      "product_id": 1122334455,
      "sku": "M18",
      "price": {
        "amount": 1867,
        "divisor": 100,
        "currency_code": "EUR"
      },
      "shipping_cost": {
        "amount": 0,
        "divisor": 100,
        "currency_code": "EUR"
      },
      "variations": [],
      "product_data": [],
      "shipping_profile_id": 1,
      "min_processing_days": 0,
      "max_processing_days": 0,
      "shipping_method": "DHL",
      "shipping_upgrade": "",
      "expected_ship_date": 1784113965,
      "buyer_coupon": 0,
      "shop_coupon": 0
    }
  ]
}
*/


/**
 *
 * @return array [bool $etsy_connected, array|null $etsy_token_data]
 */
if (!function_exists('bx_etsy_get_connection_status')) {
  function bx_etsy_get_connection_status(): array
  {
    $etsy_connected  = false;
    $etsy_token_data = null;

    if (!defined('BX_ETSY_AVAILABLE') || !BX_ETSY_AVAILABLE) {
      return array($etsy_connected, $etsy_token_data);
    }

    $shop_id = trim((string)MODULE_BX_ETSY_MANAGER_SHOP_ID);

    if ($shop_id === '') {
      return array($etsy_connected, $etsy_token_data);
    }

    $token_query = xtc_db_query("SELECT * FROM bx_etsy_oauth_tokens WHERE shop_id = '" . xtc_db_input($shop_id) . "'");

    if (xtc_db_num_rows($token_query) > 0) {
      $etsy_connected  = true;
      $etsy_token_data = xtc_db_fetch_array($token_query);
    }

    return array($etsy_connected, $etsy_token_data);
  }
}

if (!function_exists('bx_etsy_admin_split_page_display_count')) {
  function bx_etsy_admin_split_page_display_count(int $query_numrows, int $max_rows_per_page, int $current_page_number, string $text_output): string
  {
    $to_num = ($max_rows_per_page * $current_page_number);
    if ($to_num > $query_numrows) {
      $to_num = $query_numrows;
    }

    $from_num = ($max_rows_per_page * ($current_page_number - 1));
    if ($to_num == 0) {
      $from_num = 0;
    } else {
      $from_num++;
    }

    return '<span style="line-height: 28px;">' . sprintf($text_output, $from_num, $to_num, $query_numrows) . '</span>';
  }
}

if (!function_exists('bx_etsy_admin_result_page_format')) {
  function bx_etsy_admin_result_page_format(): string
  {
    if (defined('TEXT_RESULT_PAGE') && strpos(TEXT_RESULT_PAGE, '%s') !== false) {
      return TEXT_RESULT_PAGE;
    }

    return 'Seite %s von %d';
  }
}

if (!function_exists('bx_etsy_admin_ajax_tab_url')) {
  function bx_etsy_admin_ajax_tab_url(string $tab): string
  {
    return xtc_href_link('ajax.php', 'ext=bx_etsymanager&method=load_tab&type=html&tab=' . rawurlencode($tab));
  }
}

if (!function_exists('bx_etsy_admin_split_page_display_links')) {
  function bx_etsy_admin_split_page_display_links(int $query_numrows, int $max_rows_per_page, int $max_page_links, int $current_page_number, string $parameters = '', string $page_name = 'page'): string
  {
    if (xtc_not_null($parameters) && (substr($parameters, -1) != '&')) {
      $parameters .= '&';
    }

    $num_pages = $max_rows_per_page > 0 ? (int)ceil($query_numrows / $max_rows_per_page) : 0;
    $pages_array = array();
    for ($i = 1; $i <= $num_pages; $i++) {
      $pages_array[] = array('id' => $i, 'text' => $i);
    }

    if ($num_pages <= 1) {
      return '<span style="line-height: 28px;">' . sprintf(bx_etsy_admin_result_page_format(), $num_pages, $num_pages) . '</span>';
    }

    $form_action = bx_etsy_admin_ajax_tab_url('orders');

    //$display_links = '<form name="pages" action="ajax.php" method="get">';
    $display_links = xtc_draw_form('pages', 'ajax.php', 'get');

    if ($current_page_number > 1) {
      $display_links .= '<a href="' . htmlspecialchars($form_action . '&' . $parameters . $page_name . '=' . ($current_page_number - 1)) . '" class="button">' . PREVNEXT_BUTTON_PREV . '</a>&nbsp;&nbsp;';
    }

    $display_links .= 'Seite ' . xtc_draw_pull_down_menu($page_name, $pages_array, $current_page_number, 'id="bx-etsy-page-select"');
    $display_links .= ' von ' . $num_pages;

    if (($current_page_number < $num_pages) && ($num_pages != 1)) {
      $display_links .= '&nbsp;&nbsp;<a href="' . htmlspecialchars($form_action . '&' . $parameters . $page_name . '=' . ($current_page_number + 1)) . '" class="button">' . PREVNEXT_BUTTON_NEXT . '</a>';
    }

    if ($parameters != '') {
      if (substr($parameters, -1) == '&') {
        $parameters = substr($parameters, 0, -1);
      }

      $pairs = explode('&', $parameters);
      foreach ($pairs as $pair) {
        $parts = explode('=', $pair, 2);
        if (count($parts) !== 2) {
          continue;
        }

        list($key, $value) = $parts;
        $display_links .= xtc_draw_hidden_field(rawurldecode($key), rawurldecode($value));
      }
    }
    $display_links .= xtc_draw_hidden_field('ext', 'bx_etsymanager');
    $display_links .= xtc_draw_hidden_field('method', 'load_tab');
    $display_links .= xtc_draw_hidden_field('type', 'html');
    $display_links .= xtc_draw_hidden_field('tab', rawurlencode('orders'));

    $display_links .= '</form>';

    return $display_links;
  }
}

if (!function_exists('bx_etsy_admin_split_page_count_rows')) {
  function bx_etsy_admin_split_page_count_rows(string $sql_query, string $count_key = '*'): int
  {
    $pos_to = strlen($sql_query);
    $pos_from = strpos(strtoupper($sql_query), ' FROM', 0);

    $pos_group_by = strpos(strtoupper($sql_query), ' GROUP BY', $pos_from);
    if (($pos_group_by < $pos_to) && ($pos_group_by !== false)) {
      $pos_to = $pos_group_by;
    }

    $pos_having = strpos(strtoupper($sql_query), ' HAVING', $pos_from);
    if (($pos_having < $pos_to) && ($pos_having !== false)) {
      $pos_to = $pos_having;
    }

    $pos_order_by = strpos(strtoupper($sql_query), ' ORDER BY', $pos_from);
    if (($pos_order_by < $pos_to) && ($pos_order_by !== false)) {
      $pos_to = $pos_order_by;
    }

    if (strpos(strtoupper($sql_query), 'DISTINCT') !== false || strpos(strtoupper($sql_query), 'GROUP BY') !== false) {
      $count_string = 'DISTINCT ' . xtc_db_input($count_key);
    } else {
      $count_string = xtc_db_input($count_key);
    }

    $count_query = xtc_db_query("SELECT count(" . $count_string . ") as total " . substr($sql_query, $pos_from, ($pos_to - $pos_from)));
    $count = xtc_db_fetch_array($count_query);

    return (int)($count['total'] ?? 0);
  }
}

if (!function_exists('bx_etsy_decode_order_payload_json')) {
  function bx_etsy_decode_order_payload_json(string $payload_json): array
  {
    $payload_json = trim($payload_json);

    if ($payload_json === '') {
      throw new RuntimeException('payload_json ist leer.');
    }

    $payload_data = json_decode($payload_json, true);
    if (!is_array($payload_data)) {
      throw new RuntimeException('payload_json enthält kein gültiges JSON.');
    }

    if (isset($payload_data['receipt']) && is_array($payload_data['receipt'])) {
      return $payload_data['receipt'];
    }

    $looks_like_receipt = false;

    if (isset($payload_data['receipt_id'])) {
      $looks_like_receipt = true;
    }

    if (isset($payload_data['transactions']) && is_array($payload_data['transactions'])) {
      $looks_like_receipt = true;
    }

    if (isset($payload_data['receipt_transactions']) && is_array($payload_data['receipt_transactions'])) {
      $looks_like_receipt = true;
    }

    if (isset($payload_data['grandtotal']) && is_array($payload_data['grandtotal'])) {
      $looks_like_receipt = true;
    }

    if ($looks_like_receipt) {
      return $payload_data;
    }

    throw new RuntimeException('payload_json enthält weder ein receipt-Objekt noch ein direktes Etsy-Receipt.');
  }
}

if (!function_exists('bx_etsy_render_order_details_html')) {
  function bx_etsy_render_order_details_html(array $bx_ord): string
  {
    global $xtPrice;

    if (!is_object($xtPrice) || !method_exists($xtPrice, 'xtcFormatCurrency')) {
      throw new RuntimeException('xtPrice steht für die Währungsformatierung nicht zur Verfügung.');
    }

    $receipt      = bx_etsy_decode_order_payload_json((string)($bx_ord['payload_json'] ?? ''));
    $contact_data = array(
      'name'              => isset($receipt['name']) ? trim((string)$receipt['name']) : '',
      'first_line'        => isset($receipt['first_line']) ? trim((string)$receipt['first_line']) : '',
      'second_line'       => isset($receipt['second_line']) ? trim((string)$receipt['second_line']) : '',
      'city'              => isset($receipt['city']) ? trim((string)$receipt['city']) : '',
      'state'             => isset($receipt['state']) ? trim((string)$receipt['state']) : '',
      'zip'               => isset($receipt['zip']) ? trim((string)$receipt['zip']) : '',
      'country_iso'       => isset($receipt['country_iso']) ? strtoupper(trim((string)$receipt['country_iso'])) : '',
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

        $shipments[] = $shipment;
      }
    }

    $buyer_user_id  = (isset($receipt['buyer_user_id']) && is_numeric($receipt['buyer_user_id'])) ? (int)$receipt['buyer_user_id'] : 0;
    $buyer_name     = isset($receipt['name']) ? trim((string)$receipt['name']) : '';
    $buyer_email    = isset($receipt['buyer_email']) ? trim((string)$receipt['buyer_email']) : '';
    $payment_method = isset($receipt['payment_method']) ? trim((string)$receipt['payment_method']) : '';
    $gift_message   = isset($receipt['gift_message']) ? trim((string)$receipt['gift_message']) : '';
    $buyer_message  = isset($receipt['message_from_buyer']) ? trim((string)$receipt['message_from_buyer']) : '';
    $currency_code  = function_exists('bx_etsy_receipt_currency_code')
      ? bx_etsy_receipt_currency_code($receipt)
      : (isset($receipt['currency_code']) ? strtoupper(trim((string)$receipt['currency_code'])) : '');

    if ($currency_code === '') {
      throw new RuntimeException('Im payload_json fehlt der currency_code.');
    }

    $receipt_total = 0.0;
    if (isset($receipt['grandtotal']) && is_array($receipt['grandtotal']) && isset($receipt['grandtotal']['amount'])) {
      $grandtotal_amount  = (float)$receipt['grandtotal']['amount'];
      $grandtotal_divisor = isset($receipt['grandtotal']['divisor']) ? (float)$receipt['grandtotal']['divisor'] : 100.0;
      $receipt_total      = $grandtotal_divisor > 0 ? ($grandtotal_amount / $grandtotal_divisor) : $grandtotal_amount;
    } elseif (isset($receipt['total_price']) && is_array($receipt['total_price']) && isset($receipt['total_price']['amount'])) {
      $total_price_amount  = (float)$receipt['total_price']['amount'];
      $total_price_divisor = isset($receipt['total_price']['divisor']) ? (float)$receipt['total_price']['divisor'] : 100.0;
      $receipt_total       = $total_price_divisor > 0 ? ($total_price_amount / $total_price_divisor) : $total_price_amount;
    }

    $fmt_total      = (string)$xtPrice->xtcFormatCurrency($receipt_total);
    $discount_amount = null;
    if (isset($receipt['discount_amt']) && is_array($receipt['discount_amt']) && isset($receipt['discount_amt']['amount'])) {
      $discount_amt_amount  = (float)$receipt['discount_amt']['amount'];
      $discount_amt_divisor = isset($receipt['discount_amt']['divisor']) ? (float)$receipt['discount_amt']['divisor'] : 100.0;
      $discount_amount      = $discount_amt_divisor > 0 ? ($discount_amt_amount / $discount_amt_divisor) : $discount_amt_amount;
    }
    $fmt_discount = $discount_amount !== null ? (string)$xtPrice->xtcFormatCurrency($discount_amount) : '';
    $fmt_order_date = (!empty($bx_ord['order_created_at']) && $bx_ord['order_created_at'] !== '0000-00-00 00:00:00')
      ? xtc_date_short($bx_ord['order_created_at'])
      : '-';
    $fmt_paid_date = (!empty($bx_ord['paid_at']) && $bx_ord['paid_at'] !== '0000-00-00 00:00:00')
      ? xtc_date_short($bx_ord['paid_at'])
      : '-';

    $payment_status_labels = array(
      'paid'       => 'Bezahlt',
      'open'       => 'Offen',
      'refunded'   => 'Erstattet',
      'processing' => 'In Verarbeitung',
      'authorized' => 'Autorisiert',
      'completed'  => 'Abgeschlossen',
    );
    $order_status_labels = array(
      'new'        => 'Neu',
      'shipped'    => 'Versandt',
      'canceled'   => 'Storniert',
      'processing' => 'In Bearbeitung',
      'open'       => 'Offen',
      'paid'       => 'Bezahlt',
      'completed'  => 'Abgeschlossen',
    );

    $payment_status_raw = trim((string)($bx_ord['payment_status'] ?? ''));
    $order_status_raw   = trim((string)($bx_ord['order_status'] ?? ''));
    $payment_status = $payment_status_raw !== ''
      ? ($payment_status_labels[strtolower($payment_status_raw)] ?? $payment_status_raw)
      : '-';
    $order_status = $order_status_raw !== ''
      ? ($order_status_labels[strtolower($order_status_raw)] ?? $order_status_raw)
      : '-';

    $transactions = bx_etsy_extract_receipt_transactions($receipt);
    if (empty($transactions)) {
      throw new RuntimeException('Im payload_json wurden keine Transaktionen gefunden.');
    }

    $valid_transaction_count = 0;

    ob_start();
    ?>
<div class="bx-etsy-order-items">
<?php
  foreach ($transactions as $transaction) {
    if (!is_array($transaction)) {
      continue;
    }

    $valid_transaction_count++;

    $title    = bx_etsy_extract_transaction_title($transaction);
    $sku      = isset($transaction['sku']) ? trim((string)$transaction['sku']) : '';
    $quantity = 1;

    foreach (array('quantity', 'quantity_purchased', 'qty', 'count') as $quantity_key) {
      if (isset($transaction[$quantity_key]) && is_numeric($transaction[$quantity_key])) {
        $quantity = max(1, (int)$transaction[$quantity_key]);
        break;
      }
    }

    $price_amount = bx_etsy_extract_transaction_amount($transaction);
    $price_text   = (string)$xtPrice->xtcFormatCurrency($price_amount);

    $transaction_id = isset($transaction['transaction_id']) && is_numeric($transaction['transaction_id']) ? (string)(int)$transaction['transaction_id'] : '';
    $listing_id     = isset($transaction['listing_id']) && is_numeric($transaction['listing_id']) ? (string)(int)$transaction['listing_id'] : '';
    $variations     = array();
    if (isset($transaction['variations']) && is_array($transaction['variations'])) {
      foreach ($transaction['variations'] as $variation) {
        if (!is_array($variation)) {
          continue;
        }

        $label = isset($variation['formatted_name']) ? trim((string)$variation['formatted_name']) : '';
        $value = isset($variation['formatted_value']) ? trim((string)$variation['formatted_value']) : '';

        if ($label !== '' && $value !== '') {
          $variations[] = $label . ': ' . $value;
          continue;
        }

        if ($value !== '') {
          $variations[] = $value;
        }
      }
    }

    $variations = array_values(array_unique($variations));
?>
<div style="padding: 6px 0; border-bottom: 1px solid #ddd;">
    <strong><?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false); ?></strong><br>
    Menge: <?php echo (int)$quantity; ?><br>
<?php if ($sku !== '') { ?>
    SKU: <?php echo htmlspecialchars($sku, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false); ?><br>
<?php } ?>
<?php if ($transaction_id !== '') { ?>
    Transaktion: <?php echo htmlspecialchars($transaction_id, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false); ?><br>
<?php } ?>
<?php if ($listing_id !== '') { ?>
    Listing: <?php echo htmlspecialchars($listing_id, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false); ?><br>
<?php } ?>
<?php if (!empty($variations)) { ?>
    <?php echo htmlspecialchars(implode(' | ', $variations), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false); ?><br>
<?php } ?>
    Einzelpreis: <?php echo $price_text; ?>
  </div>
<?php } ?>
</div>
    <?php
    $products_html = ob_get_clean();

    if ($valid_transaction_count < 1) {
      throw new RuntimeException('Die Transaktionsdaten im payload_json sind leer oder ungültig.');
    }

    ob_start();
    ?>
    <div class="bx-headboard bx-headboard--secondary">
      <strong>Bestelldetails</strong>
    </div>

    <dl class="bx-dl-horizontal">
      <div class="bx-dl-item">
        <dt>Etsy-Bestellung:</dt><dd><?php echo htmlspecialchars((string)($bx_ord['etsy_order_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Shop-Rechnung:</dt><dd><?php echo htmlspecialchars(trim((string)($bx_ord['invoice_number'] ?? '')) !== '' ? (string)$bx_ord['invoice_number'] : '-', ENT_QUOTES, 'UTF-8'); ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Bestelldatum:</dt><dd><?php echo htmlspecialchars((string)$fmt_order_date, ENT_QUOTES, 'UTF-8'); ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Zahldatum:</dt><dd><?php echo htmlspecialchars((string)$fmt_paid_date, ENT_QUOTES, 'UTF-8'); ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Kunde:</dt><dd><?php echo $buyer_name !== '' ? htmlspecialchars($buyer_name, ENT_QUOTES, 'UTF-8') : '-'; ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Etsy Buyer ID:</dt><dd><?php echo $buyer_user_id > 0 ? htmlspecialchars((string)$buyer_user_id, ENT_QUOTES, 'UTF-8') : '-'; ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>E-Mail:</dt><dd><?php echo $buyer_email !== '' ? htmlspecialchars($buyer_email, ENT_QUOTES, 'UTF-8') : '-'; ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Lieferadresse:</dt><dd><?php echo $contact_data['formatted_address'] !== '' ? htmlspecialchars((string)$contact_data['formatted_address'], ENT_QUOTES, 'UTF-8') : '-'; ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Land:</dt><dd><?php echo $contact_data['country_iso'] !== '' ? htmlspecialchars((string)$contact_data['country_iso'], ENT_QUOTES, 'UTF-8') : '-'; ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Zahlungsstatus:</dt><dd><?php echo htmlspecialchars((string)$payment_status, ENT_QUOTES, 'UTF-8'); ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Zahlungsart:</dt><dd><?php echo $payment_method !== '' ? htmlspecialchars($payment_method, ENT_QUOTES, 'UTF-8') : '-'; ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Bestellstatus:</dt><dd><?php echo htmlspecialchars((string)$order_status, ENT_QUOTES, 'UTF-8'); ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Summe:</dt><dd><?php echo $fmt_total; ?></dd>
      </div>
      <?php if ($discount_amount !== null) { ?>
      <div class="bx-dl-item">
        <dt>Rabatt:</dt><dd><?php echo $fmt_discount; ?></dd>
      </div>
      <?php } ?>
      <div class="bx-dl-item">
        <dt>Sendungen:</dt><dd><?php echo count($shipments); ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Geschenk:</dt><dd><?php echo !empty($receipt['is_gift']) ? 'Ja' : 'Nein'; ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Geschenknachricht:</dt><dd><?php echo $gift_message !== '' ? htmlspecialchars($gift_message, ENT_QUOTES, 'UTF-8') : '-'; ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Nachricht vom Käufer:</dt><dd><?php echo $buyer_message !== '' ? htmlspecialchars($buyer_message, ENT_QUOTES, 'UTF-8') : '-'; ?></dd>
      </div>
      <div class="bx-dl-item">
        <dt>Produkte:</dt><dd><?php echo $products_html; ?></dd>
      </div>
      </dl>
    <?php
    return ob_get_clean();
  }
}

if (!function_exists('bx_etsy_render_order_details_by_order_id')) {
  function bx_etsy_render_order_details_by_order_id(string $shop_id, string $etsy_order_id): string
  {
    $shop_id = trim($shop_id);
    $etsy_order_id = trim($etsy_order_id);

    if ($shop_id === '') {
      throw new RuntimeException('Shop-ID ist nicht konfiguriert.');
    }

    if ($etsy_order_id === '') {
      throw new RuntimeException('Es wurde keine Etsy-Bestellung übergeben.');
    }

    $order_query = xtc_db_query("SELECT etsy_order_id, invoice_number, order_created_at, paid_at, buyer_name, payment_status, order_status, payload_json 
                                        FROM bx_etsy_orders 
                                       WHERE shop_id = '" . xtc_db_input($shop_id) . "' 
                                         AND etsy_order_id = '" . xtc_db_input($etsy_order_id) . "' LIMIT 1");

    if (xtc_db_num_rows($order_query) === 0) {
      throw new RuntimeException('Die gewählte Etsy-Bestellung wurde nicht gefunden.');
    }

    $bx_ord = xtc_db_fetch_array($order_query);

    return bx_etsy_render_order_details_html($bx_ord);
  }
}

/**
 * Rendert den Dashboard-Tab (Content + rechte Sidebar).
 *
 * @return array ['content' => string, 'right' => string]
 */
if (!function_exists('bx_etsy_render_tab_dashboard')) {
  function bx_etsy_render_tab_dashboard(): array
  {
    $dashboard_data = function_exists('bx_etsy_build_dashboard_data') ? bx_etsy_build_dashboard_data() : array();

    $dashboard_kpis           = $dashboard_data['kpis'] ?? array();
    $dashboard_top_listings   = $dashboard_data['top_listings'] ?? array();
    $dashboard_low_performers = $dashboard_data['low_performers'] ?? array();
    $dashboard_activities     = $dashboard_data['activities'] ?? array();

    // Sicherstellen, dass immer 6 KPI-Slots existieren - verhindert Notices, falls
    // bx_etsy_build_dashboard_data() mal weniger als 6 Einträge liefert.
    for ($i = 0; $i < 6; $i++) {
      if (!isset($dashboard_kpis[$i]) || !is_array($dashboard_kpis[$i])) {
        $dashboard_kpis[$i] = array('label' => '-', 'value' => '-', 'note' => '');
      }
    }

    list($etsy_connected, $etsy_token_data) = bx_etsy_get_connection_status();

    ob_start();
    ?>
                  <div class="main dashboard-intro">
                    <strong>Dashboard</strong>
                    <p>Hier ist die zentrale Übersicht für Umsatz, Bestellungen, Renner, Penner und letzte Aktivitäten Ihres Etsy-Shops.</p>
                    <button id="bx-etsy-manual-sync-btn" class="button" style="font-size: 13px; padding: 8px 15px;">🔄 Jetzt synchronisieren</button>
                    <span id="bx-etsy-manual-sync-status" style="margin-left: 10px; font-size: 13px;"></span>
                  </div>

                  <table class="dashboard-table dashboard-kpi-table" border="0" width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                      <td width="33.33%" valign="top" class="dashboard-cell dashboard-cell-right">
                        <div class="dashboard-card dashboard-card-orange">
                          <div class="dashboard-kpi-label"><?php echo htmlspecialchars($dashboard_kpis[0]['label']); ?></div>
                          <div class="dashboard-kpi-value"><?php echo htmlspecialchars($dashboard_kpis[0]['value']); ?></div>
                          <div class="dashboard-kpi-note"><?php echo htmlspecialchars($dashboard_kpis[0]['note']); ?></div>
                        </div>
                      </td>
                      <td width="33.33%" valign="top" class="dashboard-cell dashboard-cell-right">
                        <div class="dashboard-card dashboard-card-blue">
                          <div class="dashboard-kpi-label"><?php echo htmlspecialchars($dashboard_kpis[1]['label']); ?></div>
                          <div class="dashboard-kpi-value"><?php echo htmlspecialchars($dashboard_kpis[1]['value']); ?></div>
                          <div class="dashboard-kpi-note"><?php echo htmlspecialchars($dashboard_kpis[1]['note']); ?></div>
                        </div>
                      </td>
                      <td width="33.33%" valign="top" class="dashboard-cell dashboard-cell-last-row">
                        <div class="dashboard-card dashboard-card-green">
                          <div class="dashboard-kpi-label"><?php echo htmlspecialchars($dashboard_kpis[2]['label']); ?></div>
                          <div class="dashboard-kpi-value"><?php echo htmlspecialchars($dashboard_kpis[2]['value']); ?></div>
                          <div class="dashboard-kpi-note"><?php echo htmlspecialchars($dashboard_kpis[2]['note']); ?></div>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td width="33.33%" valign="top" class="dashboard-cell dashboard-cell-right">
                        <div class="dashboard-card dashboard-card-violet">
                          <div class="dashboard-kpi-label"><?php echo htmlspecialchars($dashboard_kpis[3]['label']); ?></div>
                          <div class="dashboard-kpi-value"><?php echo htmlspecialchars($dashboard_kpis[3]['value']); ?></div>
                          <div class="dashboard-kpi-note"><?php echo htmlspecialchars($dashboard_kpis[3]['note']); ?></div>
                        </div>
                      </td>
                      <td width="33.33%" valign="top" class="dashboard-cell dashboard-cell-right">
                        <div class="dashboard-card dashboard-card-red">
                          <div class="dashboard-kpi-label"><?php echo htmlspecialchars($dashboard_kpis[4]['label']); ?></div>
                          <div class="dashboard-kpi-value"><?php echo htmlspecialchars($dashboard_kpis[4]['value']); ?></div>
                          <div class="dashboard-kpi-note"><?php echo htmlspecialchars($dashboard_kpis[4]['note']); ?></div>
                        </div>
                      </td>
                      <td width="33.33%" valign="top" class="dashboard-cell dashboard-cell-last-row">
                        <div class="dashboard-card dashboard-card-slate">
                          <div class="dashboard-kpi-label"><?php echo htmlspecialchars($dashboard_kpis[5]['label']); ?></div>
                          <div class="dashboard-kpi-value"><?php echo htmlspecialchars($dashboard_kpis[5]['value']); ?></div>
                          <div class="dashboard-kpi-note"><?php echo htmlspecialchars($dashboard_kpis[5]['note']); ?></div>
                        </div>
                      </td>
                    </tr>
                  </table>

                  <table class="dashboard-table dashboard-panel-table" border="0" width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                      <td width="50%" valign="top" class="dashboard-panel-cell dashboard-cell-right">
                        <div class="dashboard-panel-card">
                          <div class="main dashboard-panel-title"><strong>Renner: Top-5 Listings nach Umsatz</strong></div>
                          <table class="dashboard-list-table" border="0" width="100%" cellspacing="0" cellpadding="4">
                            <tr class="dashboard-list-head">
                              <td><strong>Listing</strong></td>
                              <td align="right"><strong>Umsatz</strong></td>
                            </tr>
                            <?php foreach ($dashboard_top_listings as $dashboard_listing) { ?>
                            <tr>
                              <td><?php echo htmlspecialchars($dashboard_listing['title']); ?></td>
                              <td align="right"><?php echo htmlspecialchars($dashboard_listing['value']); ?></td>
                            </tr>
                            <?php } ?>
                          </table>
                        </div>
                      </td>
                      <td width="50%" valign="top" class="dashboard-panel-cell dashboard-cell-last-row">
                        <div class="dashboard-panel-card">
                          <div class="main dashboard-panel-title"><strong>Schwächste Listings (Monat)</strong></div>
                          <table class="dashboard-list-table" border="0" width="100%" cellspacing="0" cellpadding="4">
                            <tr class="dashboard-list-head">
                              <td><strong>Listing</strong></td>
                              <td align="right"><strong>Verkäufe</strong></td>
                            </tr>
                            <?php foreach ($dashboard_low_performers as $dashboard_listing) { ?>
                            <tr>
                              <td><?php echo htmlspecialchars($dashboard_listing['title']); ?></td>
                              <td align="right"><?php echo htmlspecialchars($dashboard_listing['value']); ?></td>
                            </tr>
                            <?php } ?>
                          </table>
                        </div>
                      </td>
                    </tr>
                  </table>

                  <div class="dashboard-activity-card">
                    <div class="main dashboard-panel-title"><strong>Letzte Aktivitäten</strong></div>
                    <table class="dashboard-list-table" border="0" width="100%" cellspacing="0" cellpadding="4">
                      <tr class="dashboard-list-head">
                        <td width="20%"><strong>Zeitpunkt</strong></td>
                        <td width="20%"><strong>Typ</strong></td>
                        <td><strong>Beschreibung</strong></td>
                      </tr>
                      <?php foreach ($dashboard_activities as $dashboard_activity) { ?>
                      <tr>
                        <td><?php echo htmlspecialchars($dashboard_activity['time']); ?></td>
                        <td><?php echo htmlspecialchars($dashboard_activity['type']); ?></td>
                        <td><?php echo htmlspecialchars($dashboard_activity['text']); ?></td>
                      </tr>
                      <?php } ?>
                    </table>
                  </div>
    <?php

    $content = ob_get_clean();

      // Verbleibende Zeit
      // data-expires-timestamp statt Inline-<script>, damit der AJAX-Auto-Init (initTokenCountdownContainers)
      // greift - Inline-Scripts werden bei per innerHTML nachgeladenem HTML vom Browser nicht ausgefuehrt.
      // Der Countdown-Bereich ist zudem vom Button getrennt, da initTokenCountdown() den Container per
      // innerHTML ueberschreibt und sonst den "Trennen"-Button mit entfernen wuerde.
    $expires_timestamp = strtotime($etsy_token_data['expires_at']);   
    $remaining_time = '<article class="bx-panel">
                      <div id="bx-etsy-token-countdown" class="bx-etsy-token-countdown" data-expires-timestamp="' . (int)$expires_timestamp . '">
                        <p>Lade Timer...</p>
                      </div>' . xtc_button_link('⭕ Trennen', xtc_href_link(DIR_ADMIN.FILENAME_ETSY_MANAGER, 'action=disconnect')) . '</article>';

    ob_start();
    ?>

    <div class="bx-headboard bx-headboard--secondary">
      <strong>Etsy Verbindung</strong>
    </div>
    <?php
    if (!defined('BX_ETSY_AVAILABLE') || !BX_ETSY_AVAILABLE) {
      echo '<article class="warning_panel">⚠️ Etsy SDK nicht gefunden</article>';
    } elseif ($etsy_connected) {
      echo '
      <article class="bx-panel">
        <div style="background: linear-gradient(135deg, #00b894 0%, #00cec9 100%); border: 3px solid #00b894; border-radius: 8px; padding: 20px; margin: 5px 0; text-align: center; box-shadow: 0 4px 15px rgba(0,184,148,0.4); position: relative; overflow: hidden;">
          <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.2) 0%, transparent 70%);"></div>
          <span style="color: #ffffff; font-size: 48px; display: block; margin-bottom: 10px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); animation: pulse 2s ease-in-out infinite;">🎉</span>
          <strong style="color: #ffffff; display: block; font-size: 16px; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">✨ VERBUNDEN ✨</strong>
        </div>
        <dl class="bx-dl-horizontal">
          <div class="bx-dl-item">
            <dt>Status:</dt>
              <dd>Verbunden</dd>
          </div>
          <div class="bx-dl-item">
            <dt>Shop:</dt>
              <dd>' . htmlspecialchars($etsy_token_data['shop_id']) . '</dd>
          </div>
          <div class="bx-dl-item">
            <dt>User ID:</dt>
              <dd>' . htmlspecialchars($etsy_token_data['user_id']) . '</dd>
          </div>
          <div class="bx-dl-item">
            <dt>Token läuft ab:</dt>
              <dd>' . date('d.m.Y H:i', strtotime($etsy_token_data['expires_at'])) . ' Uhr</dd>
          </div>
        </dl>
      </article>
      ' . $remaining_time;
    } else {
      echo '
      <article class="bx-panel">
        <div style="background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%); border: 2px solid #e17055; border-radius: 6px; padding: 0px 20px 10px 20px; margin: 5px 0; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
          <span style="color: #d63031; font-size: 36px; display: block; margin-bottom: 10px;">❌</span>
          <strong style="color: #2d3436; display: block; margin-bottom: 5px;">Nicht verbunden</strong>
        </div>
        <div class="txta-c">
          <p>Verbinden Sie Ihren Shop mit Etsy</p>
          <div style="margin-top: 10px;">
            <a href="' . xtc_href_link(DIR_ADMIN.FILENAME_ETSY_MANAGER, 'action=connect') . '" class="button">🔗 Verbinden</a>
          </div>
          <p style="color: #666;">Sie werden zu Etsy weitergeleitet</p>
        </div>
      </article>';
    }

    if (!$etsy_connected) {
    echo '
      <article class="bx-panel">
        <div class="bx-panel-title">Funktionen</div>
        <ul class="bx-list">
          <li>Produkte hochladen</li>
          <li>Listings verwalten</li>
          <li>Bestellungen abrufen</li>
          <li>Lagerbestände sync</li>
          <li>Bilder hochladen</li>
        </ul>
      </article>';
    }

    echo '
    <article class="bx-panel">
      <div class="bx-panel-title">Hinweise</div>
      <p>Erste Schritte:</p>
      <ol class="bx-list">
        <li>Konfigurieren Sie das Modul mit Ihren Etsy API-Zugangsdaten</li>
        <li>Klicken Sie auf "Mit Etsy verbinden"</li>
        <li>Autorisieren Sie die Verbindung auf Etsy.com</li>
      </ol>
      <p>Sicherheit:</p>
      <p>Ihre Zugangsdaten werden verschlüsselt gespeichert und nur für die API-Kommunikation verwendet.</p>
      <p>Token-Verwaltung:</p>
      <p>Access Token läuft nach 1 Stunde ab und wird automatisch erneuert. Refresh Token ist 90 Tage gültig.</p>
    </article>
    <article class="bx-panel">
      <div class="bx-panel-title">Etsy API</div>
      <p><a href="https://www.etsy.com/developers/your-apps" target="_blank" class="button">🔗 Developer Portal</a></p>
      <p>Verwalten Sie Ihre App-Zugangsdaten</p>
    </article>';

    $right = ob_get_clean();

    return array('content' => $content, 'right' => $right);
  }
}

/**
 * Rendert den Bestellungen-Tab (Content + rechte Sidebar).
 *
 * @return array ['content' => string, 'right' => string]
 */
if (!function_exists('bx_etsy_render_tab_orders')) {
  function bx_etsy_render_tab_orders(): array
  {
    global $xtPrice;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

    $bx_selected_etsy_order_id = isset($_REQUEST['etsy_order_id']) ? trim((string)$_REQUEST['etsy_order_id']) : '';

    if (!is_object($xtPrice) || !method_exists($xtPrice, 'xtcFormatCurrency')) {
      throw new RuntimeException('xtPrice steht für die Währungsformatierung nicht zur Verfügung.');
    }

    $orders_page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : (isset($_GET['orders_page']) ? max(1, (int)$_GET['orders_page']) : 1);
    $orders_per_page = bx_etsy_save_orders_per_page_from_request();

    $allowed_sort_cols = array(
      'etsy_order'    => 'etsy_order_id',
      'invoice'       => 'invoice_number',
      'orderdate'     => 'order_created_at',
      'paymentdate'   => 'paid_at',
      'customer'      => 'buyer_name',
      'paymentstatus' => 'payment_status',
      'status'        => 'order_status',
      'total'         => 'grand_total_gross',
    );

    $sort_col_key = 'orderdate';
    $sort_dir     = 'DESC';

    if (isset($_GET['sorting']) && xtc_not_null($_GET['sorting'])) {
      $sorting = (string)$_GET['sorting'];

      if (substr($sorting, -5) === '-desc') {
        $sort_col_key = substr($sorting, 0, -5);
        $sort_dir     = 'DESC';
      } else {
        $sort_col_key = $sorting;
        $sort_dir     = 'ASC';
      }

    }

    if (!isset($_GET['sorting']) || !xtc_not_null($_GET['sorting'])) {
      if (isset($_GET['sort']) && isset($allowed_sort_cols[$_GET['sort']])) {
        $sort_col_key = (string)$_GET['sort'];
      }
      $sort_dir = (isset($_GET['order']) && strtolower((string)$_GET['order']) === 'asc') ? 'ASC' : 'DESC';
    }

    if (!isset($allowed_sort_cols[$sort_col_key])) {
      $sort_col_key = 'orderdate';
      $sort_dir     = 'DESC';
    }

    $sort_col = $allowed_sort_cols[$sort_col_key];

    $bx_orders_shop_id = trim((string)MODULE_BX_ETSY_MANAGER_SHOP_ID);

    $orders_sql = "SELECT etsy_order_id, 
                          invoice_number, 
                          order_created_at, 
                          paid_at,
                          buyer_name, 
                          payment_status, 
                          order_status, 
                          grand_total_gross, 
                          currency_code,
                          payload_json
                     FROM bx_etsy_orders"
                       . ($bx_orders_shop_id !== '' ? " WHERE shop_id = '" . xtc_db_input($bx_orders_shop_id) . "'" : '')
                       . " ORDER BY " . $sort_col . " " . $sort_dir;

    $orders_total = bx_etsy_admin_split_page_count_rows($orders_sql, '*');

    if ($orders_page < 1) {
      $orders_page = 1;
    }

    $orders_offset = $orders_per_page > 0 ? ($orders_per_page * ($orders_page - 1)) : 0;
    if ($orders_offset < 1) {
      $orders_offset = 0;
    }

    $orders_sql .= " LIMIT " . (int)$orders_offset . ", " . (int)$orders_per_page;

    $orders_query = xtc_db_query($orders_sql);

    $bx_orders_rows = array();
    while ($bx_order = xtc_db_fetch_array($orders_query)) {
      $bx_orders_rows[] = $bx_order;
    }

    $sorting_param = $sort_col_key . ($sort_dir === 'DESC' ? '-desc' : '');
    $orders_link_params = 'sorting=' . urlencode($sorting_param);

    $orders_display_count = bx_etsy_admin_split_page_display_count($orders_total, $orders_per_page, $orders_page, MODULE_BX_ETSY_MANAGER_ORDERS_DISPLAY);
    $orders_display_links = bx_etsy_admin_split_page_display_links($orders_total, $orders_per_page, 5, $orders_page, $orders_link_params, 'page');

    $payment_labels = array(
        'paid'       => 'Bezahlt',
        'open'       => 'Offen',
        'refunded'   => 'Erstattet',
        'processing' => 'In Verarbeitung',
        'authorized' => 'Autorisiert',
        'completed'  => 'Abgeschlossen',
    );

    $order_status_labels = array(
      'new'        => 'Neu',
      'shipped'    => 'Versandt',
      'canceled'   => 'Storniert',
      'processing' => 'In Bearbeitung',
      'open'       => 'Offen',
      'paid'       => 'Bezahlt',
      'completed'  => 'Abgeschlossen',
    );

    ob_start();
    ?>
                  <table class="bx-orders-table">
                    <tr>
                      <th>
                        <div class="container">
                          <span>Etsy-Bestellung</span>
                          <span><?php echo str_replace('<br />', '', xtc_sorting(FILENAME_ETSY_MANAGER,'etsy_order')); ?></span>                          
                        </div>
                      </th>
                      <th>
                        <div class="container">
                          <span>Etsy-Bestellung</span>
                          <span><?php echo str_replace('<br />', '', xtc_sorting(FILENAME_ETSY_MANAGER,'etsy_order')); ?></span>                          
                        </div>
                        <div class="container">
                          <span>Shop-Rechnung</span>
                          <span><?php echo str_replace('<br />', '', xtc_sorting(FILENAME_ETSY_MANAGER,'invoice')); ?></span>                          
                        </div>
                      </th>
                      <th>
                        <div class="container">
                          <span>Bestelldatum</span>
                          <span><?php echo str_replace('<br />', '', xtc_sorting(FILENAME_ETSY_MANAGER,'orderdate')); ?></span>
                        </div>
                      </th>
                      <th>
                        <div class="container">
                          <span>Zahldatum</span>
                          <span><?php echo str_replace('<br />', '', xtc_sorting(FILENAME_ETSY_MANAGER,'paymentdate')); ?></span>
                        </div>
                      </th>
                      <th>
                        <div class="container">
                          <span>Kunde</span>
                          <span><?php echo str_replace('<br />', '', xtc_sorting(FILENAME_ETSY_MANAGER,'customer')); ?></span>
                        </div>
                      </th>
                      <th>
                        <div class="container">
                          <span>Zahlung</span>
                          <span><?php echo str_replace('<br />', '', xtc_sorting(FILENAME_ETSY_MANAGER,'paymentstatus')); ?></span>
                        </div>
                      </th>
                      <th>
                        <div class="container">
                          <span>Status</span>
                          <span><?php echo str_replace('<br />', '', xtc_sorting(FILENAME_ETSY_MANAGER,'status')); ?></span>
                        </div>
                      </th>
                      <th>
                        <div class="container">
                          <span>Summe</span>
                          <span><?php echo str_replace('<br />', '', xtc_sorting(FILENAME_ETSY_MANAGER,'total')); ?></span>
                        </div>
                      </th>
                      <th>
                        <div class="container">
                          <span>Action</span>
                        </div>
                      </th>
                    </tr>
<?php if (empty($bx_orders_rows)) { ?>
                      <tr>
                        <td colspan="9" style="padding: 20px; color: #888;">
                          <?php echo MODULE_BX_ETSY_MANAGER_NO_ORDERS; ?>
                        </td>
                      </tr>
<?php } else { foreach ($bx_orders_rows as $bx_ord) {
      $fmt_order_date = ($bx_ord['order_created_at'] && $bx_ord['order_created_at'] !== '0000-00-00 00:00:00')
                          ? xtc_date_short($bx_ord['order_created_at'])
                          : '-';
      $fmt_paid_date  = ($bx_ord['paid_at'] && $bx_ord['paid_at'] !== '0000-00-00 00:00:00')
                          ? xtc_date_short($bx_ord['paid_at']) : '-';

      $fmt_invoice    = trim((string)($bx_ord['invoice_number'] ?? '')) !== '' ? htmlspecialchars($bx_ord['invoice_number']) : '-';

      $fmt_pay_status = $payment_labels[strtolower((string)($bx_ord['payment_status'] ?? ''))] ?? htmlspecialchars((string)($bx_ord['payment_status'] ?? '-'));

      $fmt_ord_status = $order_status_labels[strtolower((string)($bx_ord['order_status'] ?? ''))] ?? htmlspecialchars((string)($bx_ord['order_status'] ?? '-'));

      $fmt_total = (string)$xtPrice->xtcFormatCurrency((float)($bx_ord['grand_total_gross'] ?? 0));

?>
                      <tr class="dataTableRow bx-etsy-order-row" onmouseover="this.className=this.className.replace(/\bdataTableRow\b/g,'dataTableRowOver');this.style.cursor='pointer'" onmouseout="this.className=this.className.replace(/\bdataTableRowOver\b/g,'dataTableRow')" data-order-id="<?php echo htmlspecialchars((string)($bx_ord['etsy_order_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <td><?php echo htmlspecialchars((string)($bx_ord['etsy_order_id'] ?? '')); ?></td>
                        <td><?php echo $fmt_invoice; ?></td>
                        <td><?php echo $fmt_order_date; ?></td>
                        <td><?php echo $fmt_paid_date; ?></td>
                        <td><?php echo htmlspecialchars((string)($bx_ord['buyer_name'] ?? '')); ?></td>
                        <td><?php echo $fmt_pay_status; ?></td>
                        <td><?php echo $fmt_ord_status; ?></td>
                        <td><?php echo $fmt_total; ?></td>
                        <td>
                        <?php if ($bx_selected_etsy_order_id !== '' && $bx_selected_etsy_order_id === (string)($bx_ord['etsy_order_id'] ?? '')) {
                          echo xtc_image('admin/' . DIR_WS_IMAGES . 'icon_arrow_right.gif', defined('ICON_ARROW_RIGHT') ? ICON_ARROW_RIGHT : 'Details', '', '', 'class="bx-etsy-order-action-icon"');
                        } else {
                          echo xtc_image('admin/' . DIR_WS_IMAGES . 'icon_arrow_grey.gif', defined('ICON_ARROW_RIGHT') ? ICON_ARROW_RIGHT : 'Details', '', '', 'class="bx-etsy-order-action-icon"');
                        } ?>
                        </td>
                      </tr>
<?php } } ?>
                  </table>

                  <div class="bx-panel bx-panel--accent" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px; margin: 6px 0;">
                    <div class="smallText">
                      <?php echo $orders_display_count; ?>
                    </div>
                    <div class="smallText txta-r">
                      <?php echo $orders_display_links; ?>
                    </div>
                  </div>

                  <div class="bx-panel bx-panel--accent">
                    <div class="smallText">
                    <?php echo xtc_draw_form('bx_etsy_orders_per_page', bx_etsy_admin_ajax_tab_url('orders'), 'get', 'id="bx-etsy-orders-per-page-form"'); ?>
                    <?php echo xtc_draw_hidden_field('page', '1'); ?>
                    <?php echo DISPLAY_PER_PAGE . xtc_draw_input_field('MAX_DISPLAY_LIST_ETSY', (string)$orders_per_page, 'style="width: 40px"'); ?>
                    <input type="submit" class="button" onclick="this.blur();" title="<?php echo BUTTON_SAVE; ?>" value="<?php echo BUTTON_SAVE; ?>">
                    </form>
                    </div>
                  </div>

    <?php
    $content = ob_get_clean();

    ob_start();
    ?>
      <div class="bx-headboard bx-headboard--secondary">
        <strong>Bestelldetails</strong>
      </div>
      <article class="bx-panel">
        <div class="bx-etsy-tab-loading">
          <div class="ms-spinner"></div>
          <div>⏳ Lade Bestelldetails…</div>
        </div>
      </article> <!-- bx-panel -->
    <?php
    $right = ob_get_clean();

    return array('content' => $content, 'right' => $right);
  }
}

/**
 * Rendert den Listings-Tab (Content + rechte Sidebar). Aktuell Platzhalter.
 *
 * @return array ['content' => string, 'right' => string]
 */
if (!function_exists('bx_etsy_render_tab_listings')) {
  function bx_etsy_render_tab_listings(): array
  {

    ob_start();
    ?>
    <div class="bx-headboard bx-headboard--secondary">
      <strong>Listings</strong>
    </div>
    <article class="bx-panel"><p>Hier werden Sie die Listings sehen, die in Ihrem Etsy-Shop verfügbar sind.</p></article>

    <?php
    $right = ob_get_clean();
    $content = '';

    return array('content' => $content, 'right' => $right);
  }
}

/**
 * Rendert den Support-Tab (Content + rechte Sidebar). Aktuell Platzhalter.
 *
 * @return array ['content' => string, 'right' => string]
 */
if (!function_exists('bx_etsy_render_tab_support')) {
  function bx_etsy_render_tab_support(): array
  {
    ob_start();
    ?>
       Support-Aktionen
    <?php
    $content = ob_get_clean();

    ob_start();
    ?>
    <div class="bx-headboard bx-headboard--secondary">
      <strong>Support</strong>
    </div>
    <article class="bx-panel"><p>Hier finden Sie Support-Informationen und Aktionen.</p></article>

    <?php
    $right = ob_get_clean();

    return array('content' => $content, 'right' => $right);
  }
}

<?php
/**
 * BX Etsy Manager - AJAX Handler
 */
  defined('BX_LOGGER') || define('BX_LOGGER', false);
  
  // --- Logging-Hilfsfunktion ---
  $bx_log = function(string $level, string $msg): void {
    if (defined('BX_LOGGER') && BX_LOGGER === true ) {
      $log_dir  = defined('DIR_FS_LOG') ? DIR_FS_LOG : (defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG . 'log/' : '');
      if ($log_dir === '') {
        return;
      }
      $log_file = $log_dir . 'bx_etsy_ajax.log';
      $line     = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $msg . PHP_EOL;
      file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
    }
  };


$bx_etsy_oauth_file    = DIR_FS_CATALOG . 'admin/includes/extra/functions/bx_etsy_oauth.php';
$bx_etsy_general_file  = DIR_FS_CATALOG . 'admin/includes/extra/functions/bx_etsy_general.php';
$bx_etsy_mock_file     = DIR_FS_CATALOG . 'admin/includes/extra/functions/bx_etsy_mock.php';
$bx_etsy_render_file   = DIR_FS_CATALOG . 'admin/includes/extra/functions/bx_etsy_render.php';
$bx_etsy_filename_file = DIR_FS_CATALOG . 'admin/includes/extra/filenames/bx_etsymanager.php';
$bx_admin_dir = defined('DIR_FS_ADMIN')
  ? DIR_FS_ADMIN
  : DIR_FS_CATALOG . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/');

$bx_language = isset($_SESSION['language']) && $_SESSION['language'] !== ''
  ? (string)$_SESSION['language']
  : 'german';

$bx_etsy_language_files = array(
  DIR_FS_CATALOG . 'lang/' . $bx_language . '/extra/admin/bx_etsymanager.php',
  dirname(__DIR__, 3) . '/lang/' . $bx_language . '/extra/admin/bx_etsymanager.php',
);

if (!defined('_VALID_XTC')) {
  define('_VALID_XTC', true);
}

if (!defined('BX_ETSY_AVAILABLE')) {
  require_once(DIR_FS_CATALOG . 'includes/classes/bx_dependency_resolver.php');

  try {
    bx_dependency_resolver::require('modified_etsy');
    define('BX_ETSY_AVAILABLE', true);
  } catch (Exception $e) {
    define('BX_ETSY_AVAILABLE', false);
    error_log('BX Etsy nicht verfügbar (AJAX): ' . $e->getMessage());
  }
}

if (!class_exists('tableBlock') && is_file($bx_admin_dir . 'includes/classes/table_block.php')) {
  require_once($bx_admin_dir . 'includes/classes/table_block.php');
}

if (!class_exists('box') && is_file($bx_admin_dir . 'includes/classes/box.php')) {
  require_once($bx_admin_dir . 'includes/classes/box.php');
}

if (!defined('TEXT_SORT_ASC')) {
  define('TEXT_SORT_ASC', 'ascending');
}

if (!defined('TEXT_SORT_DESC')) {
  define('TEXT_SORT_DESC', 'descending');
}

if (!defined('FILENAME_ETSY_MANAGER')) {
  define('FILENAME_ETSY_MANAGER', 'bx_etsymanager.php');
}

if (!defined('DISPLAY_PER_PAGE')) {
  define('DISPLAY_PER_PAGE', 'Anzeige pro Seite: ');
}

if (!defined('BUTTON_SAVE')) {
  define('BUTTON_SAVE', 'Speichern');
}

if (!function_exists('xtc_button_link')) {
  function xtc_button_link(string $value, $href = 'javascript:void(null)', $parameter = '') {
    return '<a href="' . $href . '" class="button" onclick="this.blur()" ' . $parameter . ' >' . $value . '</a>';
  }
}

if (!function_exists('xtc_sorting')) {
  function xtc_sorting(string $page, string $sort) {
    $nav  = '<br /><a href="' . xtc_href_link($page, xtc_get_all_get_params(array('action', 'sorting')) . 'sorting=' . $sort) . '" title="' . TEXT_SORT_ASC . '">';
    $nav .= xtc_image(DIR_WS_ICONS . 'sort_up.gif', TEXT_SORT_ASC, '20', '20') . '</a>';
    $nav .= '<a href="' . xtc_href_link($page, xtc_get_all_get_params(array('action', 'sorting')) . 'sorting=' . $sort . '-desc') . '" title="' . TEXT_SORT_DESC . '">';
    $nav .= xtc_image(DIR_WS_ICONS . 'sort_down.gif', TEXT_SORT_DESC, '20', '20') . '</a>';
    return $nav;
  }
}

if (!function_exists('xtc_draw_form')) {
  function xtc_draw_form(string $name, string $action, string $method = 'post', string $parameters = '') {
    $form = '<form name="' . $name . '" action="' . $action . '" method="' . $method . '" ' . $parameters . '>';
    // secure form with a random token
    if (CSRF_TOKEN_SYSTEM == 'true' && isset($_SESSION['CSRFToken']) && isset($_SESSION['CSRFName']) && strtolower($method) == 'post') {
      $form .= '<input type="hidden" name="'.$_SESSION['CSRFName'].'" value="'.$_SESSION['CSRFToken'].'">';
    }
    return $form;
  }
}

if (!function_exists('xtc_draw_input_field')) {
  function xtc_draw_input_field(string $name, $value = '', string $parameters = '') {
    return '<input type="text" name="' . $name . '" value="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '" ' . $parameters . ' />';
  }
}

foreach ($bx_etsy_language_files as $bx_etsy_language_file) {
  if (is_file($bx_etsy_language_file)) {
    require_once($bx_etsy_language_file);
    break;
  }
}

if (file_exists($bx_etsy_oauth_file)) {
  require_once($bx_etsy_oauth_file);
}

if (file_exists($bx_etsy_general_file)) {
  require_once($bx_etsy_general_file);
}

// Mock-Modus muss auch im AJAX-Kontext greifen, sonst weicht das Lazy-geladene
// Tab-Ergebnis vom eager gerenderten Dashboard-Tab (voller Seitenaufruf) ab.
if (file_exists($bx_etsy_mock_file)) {
  require_once($bx_etsy_mock_file);
}

if (file_exists($bx_etsy_render_file)) {
  require_once($bx_etsy_render_file);
}

if (file_exists($bx_etsy_filename_file)) {
  require_once($bx_etsy_filename_file);
}

if (!function_exists('bx_etsy_ajax_normalize_utf8')) {
  function bx_etsy_ajax_normalize_utf8($value)
  {
    if (is_array($value)) {
      foreach ($value as $key => $item) {
        $value[$key] = bx_etsy_ajax_normalize_utf8($item);
      }
      return $value;
    }

    if (!is_string($value) || preg_match('//u', $value)) {
      return $value;
    }

    if (function_exists('mb_convert_encoding')) {
      return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    if (function_exists('iconv')) {
      $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
      if ($converted !== false) {
        return $converted;
      }
    }

    return utf8_encode($value);
  }
}

if (!function_exists('bx_etsy_ajax_encode_json')) {
  function bx_etsy_ajax_encode_json(array $payload): string
  {
    $payload = bx_etsy_ajax_normalize_utf8($payload);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
      return '{"success":false,"error":"AJAX-Antwort konnte nicht serialisiert werden."}';
    }

    return $json;
  }
}

class bx_etsymanager
{
  public function load_order_details()
  {
    if (!function_exists('bx_etsy_render_order_details_by_order_id')) {
      return bx_etsy_ajax_encode_json(array(
        'success' => false,
        'error' => 'BX Etsy Detailfunktionen sind nicht geladen.',
      ));
    }

    $shop_id = trim((string)MODULE_BX_ETSY_MANAGER_SHOP_ID);
    $order_id = isset($_REQUEST['order_id']) ? trim((string)$_REQUEST['order_id']) : '';

    try {
      $html = bx_etsy_render_order_details_by_order_id($shop_id, $order_id);
    } catch (Exception $e) {
      return bx_etsy_ajax_encode_json(array(
        'success' => false,
        'error' => $e->getMessage(),
      ));
    }

    return bx_etsy_ajax_encode_json(array(
      'success' => true,
      'html' => $html,
    ));
  }

  public function refresh_token()
  {
    if (!function_exists('bx_etsy_refresh_token')) {
      return array(
        'success' => false,
        'error' => 'BX Etsy Funktionen nicht geladen. Bitte Modul-Installation prüfen.',
      );
    }

    $shop_id = trim((string)MODULE_BX_ETSY_MANAGER_SHOP_ID);

    if ($shop_id === '') {
      return array(
        'success' => false,
        'error' => 'Shop-ID ist nicht konfiguriert.',
      );
    }

    $token_query = xtc_db_query(
      "SELECT refresh_token FROM bx_etsy_oauth_tokens WHERE shop_id = '" . xtc_db_input($shop_id) . "'"
    );

    if (xtc_db_num_rows($token_query) === 0) {
      return array(
        'success' => false,
        'error' => 'Keine Etsy-Verbindung gefunden.',
      );
    }

    $token_data    = xtc_db_fetch_array($token_query);
    $refresh_token = trim((string)($token_data['refresh_token'] ?? ''));

    if ($refresh_token === '') {
      return array(
        'success' => false,
        'error' => 'Kein Refresh-Token verfügbar. Bitte Verbindung neu aufbauen.',
      );
    }

    $refreshed_token = bx_etsy_refresh_token($shop_id, $refresh_token);

    if (!$refreshed_token || empty($refreshed_token['access_token'])) {
      return array(
        'success' => false,
        'error' => 'Token konnte nicht erneuert werden.',
      );
    }

    return array(
      'success'    => true,
      'expires_at' => (string)($refreshed_token['expires_at'] ?? ''),
      'message'    => 'Token erfolgreich erneuert.',
    );
  }

  /**
   * Stößt den Bestellungen-Sync manuell an - nutzt exakt dieselbe Funktion
   * wie der echte Cronjob. Gedacht für Testbetrieb ohne eingerichteten
   * System-Cronjob, und als "Jetzt aktualisieren"-Option im Admin.
   */
  public function trigger_manual_sync()
  {
    $sync_file = DIR_FS_CATALOG . 'api/scheduled_tasks/modules/bx_etsy_orders_sync.php';

    if (!is_file($sync_file)) {
      return bx_etsy_ajax_encode_json(array(
        'success' => false,
        'error'   => 'Sync-Modul (bx_etsy_orders_sync.php) nicht gefunden.',
      ));
    }

    require_once($sync_file);

    if (!function_exists('cron_bx_etsy_orders_sync')) {
      return bx_etsy_ajax_encode_json(array(
        'success' => false,
        'error'   => 'Sync-Funktion cron_bx_etsy_orders_sync() nicht verfügbar.',
      ));
    }

    $shop_id      = trim((string)MODULE_BX_ETSY_MANAGER_SHOP_ID);
    $count_before = 0;

    if ($shop_id !== '') {
      $before_query = xtc_db_query("SELECT COUNT(*) AS cnt FROM bx_etsy_orders WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
      $before_row   = ($before_query !== false) ? xtc_db_fetch_array($before_query) : null;
      $count_before = $before_row ? (int)$before_row['cnt'] : 0;
    }

    try {
      $sync_ok = cron_bx_etsy_orders_sync();
    } catch (\Throwable $e) {
      return bx_etsy_ajax_encode_json(array(
        'success' => false,
        'error'   => get_class($e) . ': ' . $e->getMessage(),
      ));
    }

    $count_after = $count_before;

    if ($shop_id !== '') {
      $after_query = xtc_db_query("SELECT COUNT(*) AS cnt FROM bx_etsy_orders WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
      $after_row   = ($after_query !== false) ? xtc_db_fetch_array($after_query) : null;
      $count_after = $after_row ? (int)$after_row['cnt'] : $count_before;
    }

    $new_orders = max(0, $count_after - $count_before);

    return bx_etsy_ajax_encode_json(array(
      'success'      => (bool)$sync_ok,
      'count_before' => $count_before,
      'count_after'  => $count_after,
      'new_orders'   => $new_orders,
      'message'      => $sync_ok
        ? ('Sync abgeschlossen. ' . $new_orders . ' neue Bestellung(en). Insgesamt ' . $count_after . ' in der lokalen Tabelle.')
        : 'Sync mit Fehlern beendet - Details siehe log/bx_etsy_orders_sync.log.',
    ));
  }

  /**
   * Rendert einen einzelnen Tab (Content + rechte Sidebar) für Lazy-Loading per AJAX.
   * Nutzt dieselben Render-Funktionen wie der volle Seitenaufruf (bx_etsy_render.php),
   * damit sich Inhalte nicht auseinanderentwickeln.
   */
  public function load_tab()
  {
    global $bx_log;
    $allowed_tabs = array('dashboard', 'orders', 'listings', 'support');
    $tab          = isset($_REQUEST['tab']) ? (string)$_REQUEST['tab'] : '';

    if (!in_array($tab, $allowed_tabs, true)) {
      return bx_etsy_ajax_encode_json(array(
        'success' => false,
        'error' => 'Unbekannter Tab.',
      ));
    }

    $render_function = 'bx_etsy_render_tab_' . $tab;

  $bx_log('info', '$render_function = ' . $render_function);

    if (!function_exists($render_function)) {
      return bx_etsy_ajax_encode_json(array(
        'success' => false,
        'error'   => 'Render-Funktion für Tab "' . $tab . '" nicht gefunden. Bitte bx_etsy_render.php prüfen.',
      ));
    }

    try {
      $rendered = $render_function();
    } catch (Exception $e) {
      return bx_etsy_ajax_encode_json(array(
        'success' => false,
        'error' => 'Fehler beim Rendern: ' . $e->getMessage(),
      ));
    }

    return bx_etsy_ajax_encode_json(array(
      'success' => true,
      'tab'     => $tab,
      'content' => (string)($rendered['content'] ?? ''),
      'right'   => (string)($rendered['right'] ?? ''),
    ));
  }
}
<?php
/**
 * BX Etsy Manager - AJAX Handler
 */

require_once(DIR_FS_CATALOG . 'admin/includes/extra/functions/bx_etsymanager.php');

class bx_etsymanager
{
  public function refresh_token()
  {
    $config = bx_etsy_get_config();
    $shop_id = trim((string)($config['MODULE_BX_ETSY_MANAGER_SHOP_ID'] ?? ''));

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

    $token_data = xtc_db_fetch_array($token_query);
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
      'success' => true,
      'expires_at' => (string)($refreshed_token['expires_at'] ?? ''),
      'message' => 'Token erfolgreich erneuert.',
    );
  }
}
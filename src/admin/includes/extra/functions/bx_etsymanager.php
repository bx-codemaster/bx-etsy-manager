<?php
/* ----------------------------------------------------------------------------------------------
   $Id: admin/includes/extra/functions/bx_etsymanager.php 1000 2026-02-03 13:00:00Z benax $
    _                           
   | |__   ___ _ __   __ ___  __
   | '_ \ / _ \ '_ \ / _ \ \/ /
   | |_) |  __/ | | | (_| |>  < 
   |_.__/ \___|_| |_|\__,_/_/\_\
   xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   ----------------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ----------------------------------------------------------------------------------------------*/


/**
 * Holt ein gültiges Etsy Access Token für eine Shop-ID
 * Erneuert das Token automatisch, falls es abgelaufen ist
 * 
 * @param string $shop_id Die Etsy Shop-ID
 * @return array|false Array mit Token-Daten oder false bei Fehler
 *                     ['access_token', 'refresh_token', 'expires_at', 'scopes', 'user_id']
 */
function bx_etsy_get_valid_token($shop_id) {
    // Token aus Datenbank laden
    $token_query = xtc_db_query("SELECT * FROM bx_etsy_oauth_tokens 
                                         WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
    
    if (xtc_db_num_rows($token_query) === 0) {
        error_log('BX Etsy: Kein Token für Shop-ID ' . $shop_id . ' gefunden');
        return false;
    }
    
    $token_data = xtc_db_fetch_array($token_query);
    
    // Prüfen ob Token noch gültig ist (5 Minuten Puffer)
    $expires_timestamp = strtotime($token_data['expires_at']);
    $now_timestamp     = time();
    $buffer            = 300; // 5 Minuten Puffer
    
    if ($expires_timestamp > ($now_timestamp + $buffer)) {
        // Token ist noch gültig
        return $token_data;
    }
    
    // Token ist abgelaufen oder läuft bald ab -> Refresh durchführen
    return bx_etsy_refresh_token($shop_id, $token_data['refresh_token']);
}

/**
 * Erneuert ein Etsy Access Token mittels Refresh Token
 * 
 * @param string $shop_id Die Etsy Shop-ID
 * @param string $refresh_token Das Refresh Token
 * @return array|false Array mit neuen Token-Daten oder false bei Fehler
 */
function bx_etsy_refresh_token($shop_id, $refresh_token) {
    // Konfiguration laden
    $config_query = xtc_db_query("SELECT configuration_key, 
                                                configuration_value 
                                          FROM " . TABLE_CONFIGURATION . " 
                                         WHERE configuration_key IN (
                                                'MODULE_BX_ETSY_MANAGER_KEYSTRING',
                                                'MODULE_BX_ETSY_MANAGER_SHARED_SECRET'
                                            )");
    
    $config = array();
    while ($row = xtc_db_fetch_array($config_query)) {
        $config[$row['configuration_key']] = $row['configuration_value'];
    }
    
    $client_id     = $config['MODULE_BX_ETSY_MANAGER_KEYSTRING'] ?? '';
    $shared_secret = $config['MODULE_BX_ETSY_MANAGER_SHARED_SECRET'] ?? '';
    
    if (empty($client_id) || empty($shared_secret)) {
        error_log('BX Etsy: Konfiguration unvollständig für Token-Refresh');
        return false;
    }
    
    try {
        // Dependency Resolver laden falls noch nicht geschehen
        if (!class_exists('bx_dependency_resolver')) {
            require_once(DIR_FS_CATALOG . 'includes/classes/bx_dependency_resolver.php');
        }
        
        // Modified Etsy laden
        bx_dependency_resolver::require('modified_etsy');
        
        // OAuth Client initialisieren
        $client = new Etsy\OAuth\Client($client_id, $shared_secret);
        
        // Token erneuern
        $token_response = $client->refreshAccessToken($refresh_token);
        
        $new_access_token  = $token_response['access_token'] ?? '';
        $new_refresh_token = $token_response['refresh_token'] ?? '';
        $expires_in        = $token_response['expires_in'] ?? 3600; // Fallback: 1 Stunde
        
        if (empty($new_access_token)) {
            error_log('BX Etsy: Token-Refresh fehlgeschlagen - kein Access Token erhalten');
            return false;
        }
        
        // User ID von Etsy API holen (offizieller Weg)
        $user_id = bx_etsy_get_user_id($new_access_token);
        
        if (!$user_id) {
            // Fallback: Versuche aus Token zu parsen
            if (strpos($new_access_token, '.') !== false) {
                $user_id = explode('.', $new_access_token)[0];
            } else {
                $user_id = '';
            }
        }
        
        // Neue Ablaufzeit berechnen (aus API-Response)
        $expires_at = date('Y-m-d H:i:s', time() + $expires_in);
        $now        = date('Y-m-d H:i:s');
        
        // In Datenbank speichern
        $update_data = array(
            'access_token'  => $new_access_token,
            'refresh_token' => $new_refresh_token,
            'expires_at'    => $expires_at,
            'user_id'       => $user_id,
            'updated_at'    => $now
        );
        
        xtc_db_perform('bx_etsy_oauth_tokens', $update_data, 'update', 
                       "shop_id = '" . xtc_db_input($shop_id) . "'");
        
        // Aktualisierte Token-Daten zurückgeben
        $token_query = xtc_db_query("SELECT * FROM bx_etsy_oauth_tokens 
                                            WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
        
        return xtc_db_fetch_array($token_query);
        
    } catch (Exception $e) {
        error_log('BX Etsy: Token-Refresh Fehler: ' . $e->getMessage());
        return false;
    }
}

/**
 * Initialisiert einen Etsy API Client mit gültigem Access Token
 * 
 * @param string $shop_id Die Etsy Shop-ID
 * @return Etsy\EtsyClient|false Etsy Client Instanz oder false bei Fehler
 */
function bx_etsy_get_api_client($shop_id) {
    // Gültiges Token holen (automatischer Refresh falls nötig)
    $token_data = bx_etsy_get_valid_token($shop_id);
    
    if (!$token_data) {
        return false;
    }
    
    try {
        // Dependency Resolver laden falls noch nicht geschehen
        if (!class_exists('bx_dependency_resolver')) {
            require_once(DIR_FS_CATALOG . 'includes/classes/bx_dependency_resolver.php');
        }
        
        // Modified Etsy laden
        bx_dependency_resolver::require('modified_etsy');
        
        // API Client initialisieren
        $client = new Etsy\EtsyClient($token_data['access_token']);
        
        return $client;
        
    } catch (Exception $e) {
        error_log('BX Etsy: Fehler beim Initialisieren des API Clients: ' . $e->getMessage());
        return false;
    }
}

/**
 * Prüft ob ein gültiger Token für eine Shop-ID existiert
 * 
 * @param string $shop_id Die Etsy Shop-ID
 * @return bool True wenn verbunden, false wenn nicht
 */
function bx_etsy_is_connected($shop_id) {
    $token_query = xtc_db_query("SELECT expires_at FROM bx_etsy_oauth_tokens 
                                        WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
    
    if (xtc_db_num_rows($token_query) === 0) {
        return false;
    }
    
    $token_data = xtc_db_fetch_array($token_query);
    
    // Prüfen ob Token gültig ist (auch wenn es abgelaufen ist, kann es noch refreshed werden)
    // Hier prüfen wir nur ob ein Token existiert
    return !empty($token_data['expires_at']);
}

/**
 * Holt die User-ID des authentifizierten Users von der Etsy API
 * Nutzt den offiziellen /v3/application/users/me Endpoint
 * 
 * @param string $access_token Das Access Token
 * @return string|false User-ID oder false bei Fehler
 */
function bx_etsy_get_user_id($access_token) {
    try {
        // Dependency Resolver laden falls noch nicht geschehen
        if (!class_exists('bx_dependency_resolver')) {
            require_once(DIR_FS_CATALOG . 'includes/classes/bx_dependency_resolver.php');
        }

        try {
          bx_dependency_resolver::require('modified_etsy');
        } catch (Exception $e) {
          error_log('BX Etsy Manager nicht verfügbar: ' . $e->getMessage());
        }
        
        // API Client initialisieren
        $config = bx_etsy_get_config();
        $client_id = $config['MODULE_BX_ETSY_MANAGER_KEYSTRING'] ?? '';
        $shared_secret = $config['MODULE_BX_ETSY_MANAGER_SHARED_SECRET'] ?? '';
        
        if (empty($client_id) || empty($shared_secret)) {
            error_log('BX Etsy: Konfiguration unvollständig für get_user_id');
            return false;
        }

        new \Etsy\Etsy($client_id, $shared_secret, $access_token);
        
        // User-Infos von API holen
        $user = \Etsy\Resources\User::me();
        
        // User-ID aus Response extrahieren (Array oder Objekt unterstützen)
        if (is_array($user) && isset($user['user_id'])) {
            return (string)$user['user_id'];
        } elseif (is_object($user) && isset($user->user_id)) {
            return (string)$user->user_id;
        }
        
        error_log('BX Etsy: user_id nicht in API-Response gefunden');
        return false;
        
    } catch (Exception $e) {
        error_log('BX Etsy: Fehler beim Abrufen der User-ID: ' . $e->getMessage());
        return false;
    }
}

/**
 * Lädt die Etsy API Konfiguration aus der Datenbank
 * 
 * @return array Assoziatives Array mit Konfigurationswerten
 */
function bx_etsy_get_config() {
    static $config = null;
    
    if ($config !== null) {
        return $config;
    }
    
    $config_query = xtc_db_query("SELECT configuration_key, 
                                        configuration_value 
                                   FROM " . TABLE_CONFIGURATION . " 
                                  WHERE configuration_key IN (
                                      'MODULE_BX_ETSY_MANAGER_KEYSTRING',
                                      'MODULE_BX_ETSY_MANAGER_SHARED_SECRET',
                                      'MODULE_BX_ETSY_MANAGER_SHOP_ID',
                                      'MODULE_BX_ETSY_MANAGER_REDIRECT_URI'
                                  )");
    
    $config = array();
    while ($row = xtc_db_fetch_array($config_query)) {
        $config[$row['configuration_key']] = $row['configuration_value'];
    }
    
    return $config;
}

<?php
/* ----------------------------------------------------------------------------------------------
   $Id: admin/includes/extra/functions/bx_etsymanager.php 1000 2026-02-03 13:00:00Z benax $

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
    $config = bx_etsy_get_config();
    
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
        $expires_in        = $token_response['expires_in'] ?? 3600;
        
        if (empty($new_access_token)) {
            error_log('BX Etsy: Token-Refresh fehlgeschlagen - kein Access Token erhalten');
            return false;
        }

        // Etsy kann bei Refresh-Rotation ggf. kein neues Refresh-Token liefern.
        // In dem Fall das bestehende Token weiterverwenden statt es zu leeren.
        if (empty($new_refresh_token)) {
            $new_refresh_token = $refresh_token;
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
 * @return Etsy\Etsy|false Etsy Client Instanz oder false bei Fehler
 */
function bx_etsy_get_api_client($shop_id) {
    // Gültiges Token holen (automatischer Refresh falls nötig)
    $token_data    = bx_etsy_get_valid_token($shop_id);
    $config        = bx_etsy_get_config();
    $client_id     = $config['MODULE_BX_ETSY_MANAGER_KEYSTRING'] ?? '';
    $shared_secret = $config['MODULE_BX_ETSY_MANAGER_SHARED_SECRET'] ?? '';
        
    if (empty($client_id) || empty($shared_secret)) {
        error_log('BX Etsy: Konfiguration unvollständig für bx_etsy_get_api_client');
        return false;
    }
    
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
        $client = new Etsy\Etsy($client_id, $shared_secret, $token_data['access_token']);
        
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
        $config        = bx_etsy_get_config();
        $client_id     = $config['MODULE_BX_ETSY_MANAGER_KEYSTRING'] ?? '';
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
 * @return array ['success'=>bool, 'data'=>array, 'error'=>string]
 */
function bx_etsy_api_request($method, $path, $access_token, $client_id, $shared_secret, $payload = null) {
    if (!function_exists('curl_init')) {
        return array(
            'success' => false,
            'data' => array(),
            'error' => 'cURL ist auf dem Server nicht verfügbar.'
        );
    }

    $url = 'https://api.etsy.com/v3' . $path;
    $method = strtoupper((string)$method);

    $headers = array(
        'Accept: application/json',
        'Authorization: Bearer ' . $access_token,
        'x-api-key: ' . $client_id . ':' . $shared_secret
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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
        $curl_error = curl_error($ch);
        curl_close($ch);
        return array(
            'success' => false,
            'data' => array(),
            'error' => 'cURL-Fehler: ' . $curl_error
        );
    }

    $http_code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $decoded = array();
    }

    if ($http_code >= 200 && $http_code < 300) {
        return array(
            'success' => true,
            'data' => $decoded,
            'error' => ''
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
        'data' => $decoded,
        'error' => $error_message
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
        if (class_exists('Etsy\\Resources\\ListingPersonalization') && class_exists('Etsy\\Etsy')) {
            new \Etsy\Etsy($client_id, $shared_secret, $access_token);
            $result = \Etsy\Resources\ListingPersonalization::get((int)$shop_id, (int)$listing_id);

            return array(
                'success' => true,
                'data' => bx_etsy_normalize_response_to_array($result),
                'error' => ''
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
        if (class_exists('Etsy\\Resources\\ListingPersonalization') && class_exists('Etsy\\Etsy')) {
            new \Etsy\Etsy($client_id, $shared_secret, $access_token);
            $result = \Etsy\Resources\ListingPersonalization::update((int)$shop_id, (int)$listing_id, $payload, true);

            return array(
                'success' => true,
                'data' => bx_etsy_normalize_response_to_array($result),
                'error' => ''
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

/**
 * Konfigurationseingabefeld für die Modulversion (read-only)
 */
if (!function_exists('bx_configuration_field_version')) {
  function bx_configuration_field_version(string $value, string $constant): string {
    return xtc_draw_input_field( 'configuration['.$constant.']', $value, 'readonly="true" style="opacity: 0.4;"');
  }
}
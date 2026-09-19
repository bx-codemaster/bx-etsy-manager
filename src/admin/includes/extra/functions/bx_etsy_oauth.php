<?php
/* ----------------------------------------------------------------------------------------------
   $Id: admin/includes/extra/functions/bx_etsy_oauth.php 1000 2026-07-14 14:00:00Z benax $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   ----------------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ----------------------------------------------------------------------------------------------*/


/**
 * Holt ein gueltiges Etsy Access Token fuer eine Shop-ID
 * Erneuert das Token automatisch, falls es abgelaufen ist
 *
 * @param string $shop_id Die Etsy Shop-ID
 * @return array|false Array mit Token-Daten oder false bei Fehler
 *                     ['access_token', 'refresh_token', 'expires_at', 'scopes', 'user_id']
 */
function bx_etsy_get_valid_token($shop_id) {
    // MOCK MODE ABFANGEN
    if (function_exists('bx_etsy_mock_enabled') && bx_etsy_mock_enabled()) {
        return array(
            'access_token'  => 'mock_token_123',
            'refresh_token' => 'mock_refresh_123',
            'expires_at'    => date('Y-m-d H:i:s', time() + 3600 * 24 * 365),
            'scopes'        => 'listings_r listings_w shops_r transactions_r transactions_w profile_r',
            'user_id'       => 'mock_user_123'
        );
    }

    // Token aus Datenbank laden
    $token_query = xtc_db_query("SELECT * FROM bx_etsy_oauth_tokens 
                                         WHERE shop_id = '" . xtc_db_input($shop_id) . "'");

    if (xtc_db_num_rows($token_query) === 0) {
        error_log('BX Etsy: Kein Token für Shop-ID ' . $shop_id . ' gefunden');
        return false;
    }

    $token_data = xtc_db_fetch_array($token_query);

    // Pruefen ob Token noch gueltig ist (5 Minuten Puffer)
    $expires_timestamp = strtotime($token_data['expires_at']);
    $now_timestamp     = time();
    $buffer            = 300; // 5 Minuten Puffer

    if ($expires_timestamp > ($now_timestamp + $buffer)) {
        // Token ist noch gueltig
        return $token_data;
    }

    // Token ist abgelaufen oder laeuft bald ab -> Refresh durchfuehren
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
    $client_id     = MODULE_BX_ETSY_MANAGER_KEYSTRING;
    $shared_secret = MODULE_BX_ETSY_MANAGER_SHARED_SECRET;

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

        // Aktualisierte Token-Daten zurueckgeben
        $token_query = xtc_db_query("SELECT * FROM bx_etsy_oauth_tokens 
                                            WHERE shop_id = '" . xtc_db_input($shop_id) . "'");

        return xtc_db_fetch_array($token_query);

    } catch (Exception $e) {
        error_log('BX Etsy: Token-Refresh Fehler: ' . $e->getMessage());
        return false;
    }
}

/**
 * Initialisiert einen Etsy API Client mit gueltigem Access Token
 *
 * @param string $shop_id Die Etsy Shop-ID
 * @return Etsy\Etsy|false Etsy Client Instanz oder false bei Fehler
 */
function bx_etsy_get_api_client($shop_id) {
    // Gueltiges Token holen (automatischer Refresh falls noetig)
    $token_data    = bx_etsy_get_valid_token($shop_id);
    $client_id     = MODULE_BX_ETSY_MANAGER_KEYSTRING;
    $shared_secret = MODULE_BX_ETSY_MANAGER_SHARED_SECRET;

    if (empty($client_id) || empty($shared_secret)) {
        error_log('BX Etsy: Konfiguration unvollstaendig fuer bx_etsy_get_api_client');
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
 * Prueft ob ein gueltiger Token fuer eine Shop-ID existiert
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

    // Pruefen ob Token gueltig ist (auch wenn es abgelaufen ist, kann es noch refreshed werden)
    // Hier pruefen wir nur ob ein Token existiert
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
          error_log('BX Etsy Manager nicht verfuegbar: ' . $e->getMessage());
        }

        // API Client initialisieren
        $client_id     = MODULE_BX_ETSY_MANAGER_KEYSTRING;
        $shared_secret = MODULE_BX_ETSY_MANAGER_SHARED_SECRET;

        if (empty($client_id) || empty($shared_secret)) {
            error_log('BX Etsy: Konfiguration unvollstaendig fuer get_user_id');
            return false;
        }

        new \Etsy\Etsy($client_id, $shared_secret, $access_token);

        // User-Infos von API holen
        $user = \Etsy\Resources\User::me();

        // User-ID aus Response extrahieren (Array oder Objekt unterstuetzen)
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

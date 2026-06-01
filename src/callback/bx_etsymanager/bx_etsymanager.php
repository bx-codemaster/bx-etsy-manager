<?php
/** --------------------------------------------------------------
 * $Id: callback/bx_etsymanager/bx_etsymanager.php 00000 2026-01-15 12:00:00Z benax $
 * modified eCommerce Shopsoftware
 * http://www.modified-shop.org
 * 
 * BX Etsy Manager - OAuth 2.0 Callback Handler
 * Empfängt Authorization Code von Etsy und tauscht ihn gegen Access Token
 * 
 * Copyright (c) 2026 [www.modified-shop.org]
 * --------------------------------------------------------------
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

// Modified Shop System laden
chdir('../../');
require_once('includes/application_top_callback.php');

// Etsy SDK laden
if (file_exists(DIR_FS_EXTERNAL . 'bx_composer_libs/modified_etsy/vendor/autoload.php')) {
    require_once(DIR_FS_EXTERNAL . 'bx_composer_libs/modified_etsy/vendor/autoload.php');
} else {
    die('Etsy SDK nicht gefunden. Bitte installieren Sie das SDK über Composer.');
}

/**
 * Liefert eine sichere Admin-URL fuer Redirects aus dem Callback-Kontext.
 *
 * @param string $query
 * @return string
 */
function bx_etsy_get_admin_redirect_url($query = '') {
    $admin_path = defined('DIR_WS_ADMIN') ? DIR_WS_ADMIN : '/admin/';
    if ($admin_path === '') {
        $admin_path = '/admin/';
    }
    if ($admin_path[0] !== '/') {
        $admin_path = '/' . $admin_path;
    }
    $admin_path = rtrim($admin_path, '/') . '/';

    $url = rtrim(HTTPS_SERVER, '/') . $admin_path . 'bx_etsymanager.php';
    if ($query !== '') {
        $url .= '?' . ltrim($query, '?');
    }

    return $url;
}

/**
 * OAuth 2.0 Callback Handler für Etsy
 */

// 1. Parameter von Etsy empfangen
$code  = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
$error = $_GET['error'] ?? null;
$error_description = $_GET['error_description'] ?? null;

/**
 * Schreibt Callback-Debug in eine feste Datei im Shop-Log-Verzeichnis.
 *
 * @param string $message
 * @return void
 */
function bx_etsy_callback_log($message) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . (string)$message;

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

    $log_file = $log_dir . 'bx_etsy_callback.log';
    @error_log($line . PHP_EOL, 3, $log_file);
}

bx_etsy_callback_log('Callback aufgerufen: code=' . ($code ? 'ja' : 'nein') . ', state=' . ($state ? 'ja' : 'nein') . ', error=' . ($error ?: ''));

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['bx_etsy_callback_last_hit'] = date('Y-m-d H:i:s');
}

/**
 * Holt Self-Infos über den offiziellen Etsy Endpoint /v3/application/users/me.
 *
 * @param Etsy\OAuth\Client $client
 * @param string $access_token
 * @return array ['shop_id' => string, 'user_id' => string]
 */
function bx_etsy_callback_get_self_info($client, $access_token) {
    $result = array(
        'shop_id' => '',
        'user_id' => ''
    );

    try {
        $client->setApiKey($access_token);
        $self = $client->get('/application/users/me');

        bx_etsy_callback_log('users/me raw response: ' . json_encode($self));

        if (is_object($self)) {
            if (isset($self->shop_id) && is_numeric($self->shop_id)) {
                $result['shop_id'] = (string)(int)$self->shop_id;
            }
            if (isset($self->user_id) && is_numeric($self->user_id)) {
                $result['user_id'] = (string)(int)$self->user_id;
            }

            // Laut Etsy API ist user_id auch als shop_id gueltig.
            if ($result['shop_id'] === '' && $result['user_id'] !== '') {
                $result['shop_id'] = $result['user_id'];
            }

            // Manche Responses sind verschachtelt (z.B. results[0]).
            if ($result['shop_id'] === '' || $result['user_id'] === '') {
                if (isset($self->results) && is_array($self->results) && isset($self->results[0]) && is_object($self->results[0])) {
                    if ($result['shop_id'] === '' && isset($self->results[0]->shop_id) && is_numeric($self->results[0]->shop_id)) {
                        $result['shop_id'] = (string)(int)$self->results[0]->shop_id;
                    }
                    if ($result['user_id'] === '' && isset($self->results[0]->user_id) && is_numeric($self->results[0]->user_id)) {
                        $result['user_id'] = (string)(int)$self->results[0]->user_id;
                    }
                }
            }

            // Fallback: shop_id über User-Shop-Endpoint ermitteln.
            if ($result['shop_id'] === '' && $result['user_id'] !== '') {
                try {
                    $shops = $client->get('/application/users/' . (int)$result['user_id'] . '/shops');
                    error_log('BX Etsy Callback: users/{user_id}/shops raw response: ' . json_encode($shops));
                    bx_etsy_callback_log('users/{user_id}/shops raw response: ' . json_encode($shops));

                    if (is_object($shops)) {
                        if (isset($shops->shop_id) && is_numeric($shops->shop_id)) {
                            $result['shop_id'] = (string)(int)$shops->shop_id;
                        } elseif (isset($shops->results) && is_array($shops->results) && isset($shops->results[0]) && is_object($shops->results[0])) {
                            if (isset($shops->results[0]->shop_id) && is_numeric($shops->results[0]->shop_id)) {
                                $result['shop_id'] = (string)(int)$shops->results[0]->shop_id;
                            }
                        }
                    }
                } catch (Exception $inner) {
                    bx_etsy_callback_log('users/{user_id}/shops Fehler: ' . $inner->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        bx_etsy_callback_log('users/me Fehler: ' . $e->getMessage());
    }

    return $result;
}

// 2. Fehlerbehandlung: User hat Zugriff verweigert oder anderer Fehler
if ($error) {
    $_SESSION['etsy_error'] = $error_description ?: 'Etsy Autorisierung fehlgeschlagen: ' . $error;
    header('Location: ' . bx_etsy_get_admin_redirect_url('error=auth_failed&cb_hit=1'));
    exit;
}

// 3. Validierung: Code und State müssen vorhanden sein
if (!$code || !$state) {
    $_SESSION['etsy_error'] = 'Ungültige Callback-Parameter: Code oder State fehlt';
    header('Location: ' . bx_etsy_get_admin_redirect_url('error=invalid_callback&cb_hit=1'));
    exit;
}

// 4. CSRF-Schutz: State und Code Verifier aus DB laden
$state_query = xtc_db_query("SELECT state, code_verifier, scopes, shop_id
                               FROM bx_etsy_oauth_state
                              WHERE state = '" . xtc_db_input($state) . "'
                                AND expires_at >= NOW()
                              LIMIT 1");

if (xtc_db_num_rows($state_query) === 0) {
    $_SESSION['etsy_error'] = 'CSRF-Validierung fehlgeschlagen: State ist ungültig oder abgelaufen';
    header('Location: ' . bx_etsy_get_admin_redirect_url('error=csrf_failed&cb_hit=1'));
    exit;
}

$state_row = xtc_db_fetch_array($state_query);
$code_verifier = (string)($state_row['code_verifier'] ?? '');
$state_scopes = (string)($state_row['scopes'] ?? '');
$state_shop_id = (string)($state_row['shop_id'] ?? '');

if ($code_verifier === '') {
    xtc_db_query("DELETE FROM bx_etsy_oauth_state WHERE state = '" . xtc_db_input($state) . "'");
    $_SESSION['etsy_error'] = 'Code Verifier fehlt für den OAuth-State.';
    header('Location: ' . bx_etsy_get_admin_redirect_url('error=verifier_missing&cb_hit=1'));
    exit;
}

// 6. Konfiguration aus Datenbank laden
$config_query = xtc_db_query("SELECT configuration_key, configuration_value 
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

$client_id     = $config['MODULE_BX_ETSY_MANAGER_KEYSTRING'] ?? '';
$shared_secret = $config['MODULE_BX_ETSY_MANAGER_SHARED_SECRET'] ?? '';
$shop_id       = $config['MODULE_BX_ETSY_MANAGER_SHOP_ID'] ?? '';
$redirect_uri  = $config['MODULE_BX_ETSY_MANAGER_REDIRECT_URI'] ?? '';

$request_uri_no_query = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?');
$runtime_redirect_uri = rtrim(HTTPS_SERVER, '/') . ($request_uri_no_query ?: '/callback/bx_etsymanager/bx_etsymanager.php');
if ($runtime_redirect_uri !== $redirect_uri) {
    bx_etsy_callback_log('Redirect URI abweichend. Config=' . $redirect_uri . ' Runtime=' . $runtime_redirect_uri);
    $redirect_uri = $runtime_redirect_uri;
}
bx_etsy_callback_log('Verwende Redirect URI=' . $redirect_uri);

if ($shop_id === '' && $state_shop_id !== '') {
    $shop_id = $state_shop_id;
}

// 7. Validierung: Konfiguration muss vollständig sein (shop_id kann anfangs leer sein)
if (empty($client_id) || empty($shared_secret) || empty($redirect_uri)) {
    $_SESSION['etsy_error'] = 'Etsy Konfiguration unvollständig. Bitte API-Zugangsdaten im Modul konfigurieren.';
    header('Location: ' . bx_etsy_get_admin_redirect_url('error=config_incomplete&cb_hit=1'));
    exit;
}

try {
    // 8. OAuth Client initialisieren
    $client = new Etsy\OAuth\Client($client_id, $shared_secret);
    
    // 9. Access Token anfordern (Code gegen Token tauschen)
    $token_response = $client->requestAccessToken(
        $redirect_uri,
        $code,
        $code_verifier
    );

    $access_token  = $token_response['access_token'] ?? '';
    $refresh_token = $token_response['refresh_token'] ?? '';

    if ($access_token === '' || $refresh_token === '') {
        throw new Exception('Ungültige Token-Antwort von Etsy erhalten.');
    }
    
    // 10. Shop-ID/User-ID über offiziellen Endpoint users/me ermitteln
    $self_info = bx_etsy_callback_get_self_info($client, $access_token);

    if ($self_info['shop_id'] === '' && $self_info['user_id'] !== '') {
        $self_info['shop_id'] = $self_info['user_id'];
    }

    if ($self_info['shop_id'] !== '') {
        $shop_id = $self_info['shop_id'];
    } elseif (ctype_digit((string)$state_shop_id)) {
        $shop_id = (string)(int)$state_shop_id;
    } elseif (ctype_digit((string)$shop_id)) {
        $shop_id = (string)(int)$shop_id;
    } else {
        throw new Exception('Shop-ID konnte nicht automatisch über users/me ermittelt werden.');
    }

    $user_id = $self_info['user_id'];
    if ($user_id === '' && strpos($access_token, '.') !== false) {
        $user_id = explode('.', $access_token)[0];
    }

    // 10b. Ermittelte Shop-ID in Modulkonfiguration persistieren
    xtc_db_query("UPDATE " . TABLE_CONFIGURATION . "
                     SET configuration_value = '" . xtc_db_input($shop_id) . "'
                   WHERE configuration_key = 'MODULE_BX_ETSY_MANAGER_SHOP_ID'");

    $shop_id_check_query = xtc_db_query("SELECT configuration_value
                                           FROM " . TABLE_CONFIGURATION . "
                                          WHERE configuration_key = 'MODULE_BX_ETSY_MANAGER_SHOP_ID'
                                          LIMIT 1");
    if (xtc_db_num_rows($shop_id_check_query) > 0) {
        $shop_id_check = xtc_db_fetch_array($shop_id_check_query);
        bx_etsy_callback_log('gespeicherte SHOP_ID in Konfiguration = ' . ($shop_id_check['configuration_value'] ?? ''));
    } else {
        bx_etsy_callback_log('MODULE_BX_ETSY_MANAGER_SHOP_ID Konfigurationseintrag nicht gefunden.');
    }
    
    // 11. Token-Ablaufzeit berechnen (Access Token: 1 Stunde)
    $expires_at = date('Y-m-d H:i:s', time() + 3600);
    
    // 12. Scopes aus OAuth-State übernehmen
    $scopes = $state_scopes;
    
    // 13. In Datenbank speichern (INSERT oder UPDATE bei vorhandenem shop_id)
    $now = date('Y-m-d H:i:s');
    
    // Prüfen ob bereits ein Token für diese Shop-ID existiert
    $check_query = xtc_db_query("SELECT id FROM bx_etsy_oauth_tokens WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
    
    // Daten-Array für xtc_db_perform vorbereiten
    $token_data = array(
        'access_token'  => $access_token,
        'refresh_token' => $refresh_token,
        'expires_at'    => $expires_at,
        'scopes'        => $scopes,
        'user_id'       => $user_id,
        'updated_at'    => $now
    );
    
    if (xtc_db_num_rows($check_query) > 0) {
        // UPDATE: Token aktualisieren
        xtc_db_perform('bx_etsy_oauth_tokens', $token_data, 'update', "shop_id = '" . xtc_db_input($shop_id) . "'");
    } else {
        // INSERT: Neuen Token erstellen (zusätzliche Felder für INSERT)
        $token_data['shop_id']     = $shop_id;
        $token_data['token_type']  = 'Bearer';
        $token_data['created_at']  = $now;
        
        xtc_db_perform('bx_etsy_oauth_tokens', $token_data, 'insert');
    }
    
    // 14. Verbrauchten OAuth-State entfernen
    xtc_db_query("DELETE FROM bx_etsy_oauth_state WHERE state = '" . xtc_db_input($state) . "'");
    
    // 15. Erfolg: Zurück zum Admin-Interface
    $_SESSION['etsy_success'] = 'Etsy Autorisierung erfolgreich! Shop ist jetzt verbunden.';
    header('Location: ' . bx_etsy_get_admin_redirect_url('success=connected&cb_hit=1'));
    exit;
    
} catch (Exception $e) {
    // 16. Fehlerbehandlung bei Token-Austausch
    $_SESSION['etsy_error'] = 'Fehler beim Token-Austausch: ' . $e->getMessage();
    
    // Verbrauchten OAuth-State entfernen
    xtc_db_query("DELETE FROM bx_etsy_oauth_state WHERE state = '" . xtc_db_input($state) . "'");
    
    header('Location: ' . bx_etsy_get_admin_redirect_url('error=token_exchange&cb_hit=1'));
    exit;
}

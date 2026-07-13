<?php
/** --------------------------------------------------------------
 * $Id: admin/bx_etsymanager.php 16358 2026-01-15 12:00:00Z benax $
 * modified eCommerce Shopsoftware
 * http://www.modified-shop.org
 * 
 * Copyright (c) 2009 - 2013 [www.modified-shop.org]
 * --------------------------------------------------------------
 * based on:
 * (c) 2000-2001 The Exchange Project  (earlier name of osCommerce)
 * (c) 2002-2003 osCommercecoding standards www.oscommerce.com
 * (c) 2003	nextcommerce www.nextcommerce.org
 * (c) 2003 XT-Commerce
 * 
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

require ('includes/application_top.php');

require_once (DIR_FS_CATALOG.DIR_WS_CLASSES."bx_dependency_resolver.php");

try {
  bx_dependency_resolver::require('modified_etsy');    
  define('BX_ETSY_AVAILABLE', true);
} catch (Exception $e) {
  define('BX_ETSY_AVAILABLE', false);
  error_log('BX Etsy Manager nicht verfügbar: ' . $e->getMessage());
}

// =============================================================================
// OAuth Flow Handler
// =============================================================================

$etsy_connected  = false;
$etsy_token_data = null;
$oauth_url       = '';
$personalization_response_json = '';
// Beispiel für Personalisierungsfragen (neues Etsy-Personalisierungsmodell mit mehreren Fragen)
$personalization_sample_json = "{\n  \"personalization_questions\": [\n    {\n      \"question_text\": \"Bitte Wunschtext angeben\",\n      \"question_type\": \"text_input\",\n      \"required\": true,\n      \"max_allowed_characters\": 100\n    }\n  ]\n}";

// Action: Mit Etsy verbinden
if (isset($_GET['action']) && $_GET['action'] == 'connect' && BX_ETSY_AVAILABLE) {
    
    // Konfiguration aus DB laden
  $config = bx_etsy_get_config();
    
    $client_id     = $config['MODULE_BX_ETSY_MANAGER_KEYSTRING'] ?? '';
    $shared_secret = $config['MODULE_BX_ETSY_MANAGER_SHARED_SECRET'] ?? '';
    $shop_id       = $config['MODULE_BX_ETSY_MANAGER_SHOP_ID'] ?? '';
    $redirect_uri  = $config['MODULE_BX_ETSY_MANAGER_REDIRECT_URI'] ?? '';

    $runtime_redirect_uri = rtrim(HTTPS_SERVER, '/') . '/callback/bx_etsymanager/bx_etsymanager.php';
    if ($runtime_redirect_uri !== $redirect_uri) {
      error_log('BX Etsy: Redirect URI aus Konfiguration abweichend. Config=' . $redirect_uri . ' Runtime=' . $runtime_redirect_uri);
      $redirect_uri = $runtime_redirect_uri;
    }
    error_log('BX Etsy: Verwende Redirect URI=' . $redirect_uri);
    
    // Validierung: Shop-ID kann optional leer sein und wird im Callback via users/me ermittelt.
    if (empty($client_id) || empty($shared_secret) || empty($redirect_uri)) {
        $messageStack->add_session('Bitte konfigurieren Sie zuerst die Etsy API-Zugangsdaten im Modul.', 'error');
        xtc_redirect(xtc_href_link(FILENAME_MODULE_EXPORT, 'set=system&module=bx_etsymanager'));
    } else {
        try {
            // OAuth Client initialisieren
            $client = new Etsy\OAuth\Client($client_id, $shared_secret);
            
            // PKCE Code Challenge generieren
            list($verifier, $code_challenge) = $client->generateChallengeCode();
            
            // State/Nonce generieren (CSRF-Schutz)
            $state = $client->createNonce();
            
            // Scopes definieren
            $scopes = [
                'listings_r',      // Listings lesen
                'listings_w',      // Listings schreiben
                'shops_r',         // Shop-Infos lesen
                'transactions_r',  // Transaktionen/Bestellungen lesen
                'transactions_w',  // Transaktionen aktualisieren
                'profile_r'        // Profil lesen
            ];
            
            // In Session speichern (wird im Callback benötigt)
            // Alte abgelaufene States löschen (älter als 10 Minuten)
            xtc_db_query("DELETE FROM bx_etsy_oauth_state WHERE expires_at < NOW()");
            
            // Neuen State speichern
            $now     = date('Y-m-d H:i:s');
            $expires = date('Y-m-d H:i:s', time() + 600); // 10 Minuten gültig
            
            // DEBUG: State-Daten loggen
            error_log('BX Etsy: Speichere State in DB');
            error_log('  State: ' . $state);
            error_log('  Shop-ID: ' . $shop_id);
            error_log('  Expires: ' . $expires);
            
            $insert_result = xtc_db_perform('bx_etsy_oauth_state', array(
                'state' => $state,
                'code_verifier' => $verifier,
                'scopes'        => implode(' ', $scopes),
                'shop_id'       => $shop_id,
                'created_at'    => $now,
                'expires_at'    => $expires
            ), 'insert');
            
            if (!$insert_result) {
                error_log('BX Etsy: FEHLER beim Speichern des States in DB!');
                error_log('  MySQL Error: ' . xtc_db_error());
            } else {
                error_log('BX Etsy: State erfolgreich gespeichert, ID: ' . xtc_db_insert_id());
            }
            
            // Authorization URL generieren
            $oauth_url = $client->getAuthorizationUrl(
                $redirect_uri,
                $scopes,
                $code_challenge,
                $state
            );
            
            // Redirect zu Etsy
            header('Location: ' . $oauth_url);
            exit;
            
        } catch (Exception $e) {
            $messageStack->add_session('Fehler beim Verbinden mit Etsy: ' . $e->getMessage(), 'error');
            xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
        }
    }
}

// Action: Verbindung trennen
if (isset($_GET['action']) && $_GET['action'] == 'disconnect') {
  $config  = bx_etsy_get_config();
  $shop_id = trim((string)($config['MODULE_BX_ETSY_MANAGER_SHOP_ID'] ?? ''));

  if ($shop_id !== '') {
        xtc_db_query("DELETE FROM bx_etsy_oauth_tokens WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
        $messageStack->add_session('✅ Etsy-Verbindung wurde getrennt.', 'success');
    }
    
    xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
}

  // Action: Personalisierung für ein Listing laden (neues Etsy-Personalisierungsmodell)
  if (isset($_GET['action']) && $_GET['action'] == 'personalization_get' && BX_ETSY_AVAILABLE) {
    $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

    if ($listing_id <= 0) {
      $messageStack->add_session('Bitte eine gültige Listing-ID angeben.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    if (!function_exists('bx_etsy_get_config') || !function_exists('bx_etsy_get_valid_token')) {
      $messageStack->add_session('BX Etsy Hilfsfunktionen sind nicht geladen.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    $config = bx_etsy_get_config();
    $shop_id = trim((string)($config['MODULE_BX_ETSY_MANAGER_SHOP_ID'] ?? ''));
    $client_id = trim((string)($config['MODULE_BX_ETSY_MANAGER_KEYSTRING'] ?? ''));
    $shared_secret = trim((string)($config['MODULE_BX_ETSY_MANAGER_SHARED_SECRET'] ?? ''));

    if ($shop_id === '' || $client_id === '' || $shared_secret === '') {
      $messageStack->add_session('Etsy-Konfiguration unvollständig. Bitte Shop-ID, Keystring und Shared Secret prüfen.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    if (!ctype_digit($shop_id)) {
      $messageStack->add_session('Die Etsy Shop-ID muss numerisch sein.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    $token_data = bx_etsy_get_valid_token($shop_id);
    if (!$token_data || empty($token_data['access_token'])) {
      $messageStack->add_session('Kein gültiges Etsy Access Token verfügbar. Bitte Verbindung erneuern.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    try {
      if (!function_exists('bx_etsy_get_listing_personalization')) {
        throw new Exception('Personalisierungs-Helper nicht verfügbar.');
      }

      $result = bx_etsy_get_listing_personalization((int)$shop_id, $listing_id, (string)$token_data['access_token'], $client_id, $shared_secret);

      if (!empty($result['success'])) {
        $response_data = is_array($result['data']) ? $result['data'] : array('message' => 'Keine Personalisierungsdaten gefunden.');
        $_SESSION['bx_etsy_personalization_response_json'] = json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      } else {
        $error_text = (string)($result['error'] ?? 'Keine Personalisierungsdaten gefunden.');
        $_SESSION['bx_etsy_personalization_response_json'] = json_encode(array('error' => $error_text), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      }
      $_SESSION['bx_etsy_personalization_listing_id'] = (string)$listing_id;
      $messageStack->add_session('✅ Personalisierung erfolgreich geladen.', 'success');
    } catch (Exception $e) {
      $messageStack->add_session('Fehler beim Laden der Personalisierung: ' . $e->getMessage(), 'error');
    }

    xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
  }

  // Action: Personalisierung für ein Listing speichern (neues Etsy-Personalisierungsmodell)
  if (isset($_GET['action']) && $_GET['action'] == 'personalization_update' && BX_ETSY_AVAILABLE) {
    $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
    $questions_json = isset($_POST['personalization_questions_json']) ? trim((string)$_POST['personalization_questions_json']) : '';

    if ($listing_id <= 0) {
      $messageStack->add_session('Bitte eine gültige Listing-ID angeben.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    if ($questions_json === '') {
      $messageStack->add_session('Bitte Personalisierungsfragen als JSON angeben.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    $decoded = json_decode($questions_json, true);
    if (!is_array($decoded)) {
      $messageStack->add_session('Ungültiges JSON für Personalisierungsfragen.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    $raw_questions = isset($decoded['personalization_questions']) ? $decoded['personalization_questions'] : $decoded;
    if (!is_array($raw_questions)) {
      $messageStack->add_session('Ungültiges JSON: personalization_questions muss ein Array sein.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    $normalized_questions = array();
    $allowed_question_types = array('text_input', 'dropdown', 'unlabeled_upload', 'labeled_upload');
    $type_map = array(
      'text' => 'text_input',
      'file_upload' => 'unlabeled_upload'
    );

    foreach ($raw_questions as $question) {
      if (!is_array($question)) {
        continue;
      }

      if (!isset($question['question_text']) && isset($question['question'])) {
        $question['question_text'] = $question['question'];
      }

      $question_type = trim((string)($question['question_type'] ?? ''));
      if (isset($type_map[$question_type])) {
        $question_type = $type_map[$question_type];
      }
      $question['question_type'] = $question_type;

      if (!isset($question['max_allowed_characters']) && isset($question['max_length'])) {
        $question['max_allowed_characters'] = (int)$question['max_length'];
      }

      unset($question['question']);
      unset($question['max_length']);

      if (trim((string)($question['question_text'] ?? '')) === '') {
        $messageStack->add_session('Jede Personalisierungsfrage braucht ein Feld question_text.', 'error');
        xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
      }

      if (!in_array($question_type, $allowed_question_types, true)) {
        $messageStack->add_session('Ungültiger question_type. Erlaubt: text_input, dropdown, unlabeled_upload, labeled_upload.', 'error');
        xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
      }

      $normalized_questions[] = $question;
    }

    if (count($normalized_questions) === 0) {
      $messageStack->add_session('Bitte mindestens eine gültige Personalisierungsfrage angeben.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    $payload = array('personalization_questions' => $normalized_questions);

    if (!function_exists('bx_etsy_get_config') || !function_exists('bx_etsy_get_valid_token')) {
      $messageStack->add_session('BX Etsy Hilfsfunktionen sind nicht geladen.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    $config        = bx_etsy_get_config();
    $shop_id       = trim((string)($config['MODULE_BX_ETSY_MANAGER_SHOP_ID'] ?? ''));
    $client_id     = trim((string)($config['MODULE_BX_ETSY_MANAGER_KEYSTRING'] ?? ''));
    $shared_secret = trim((string)($config['MODULE_BX_ETSY_MANAGER_SHARED_SECRET'] ?? ''));

    if ($shop_id === '' || $client_id === '' || $shared_secret === '') {
      $messageStack->add_session('Etsy-Konfiguration unvollständig. Bitte Shop-ID, Keystring und Shared Secret prüfen.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    if (!ctype_digit($shop_id)) {
      $messageStack->add_session('Die Etsy Shop-ID muss numerisch sein.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    $token_data = bx_etsy_get_valid_token($shop_id);
    if (!$token_data || empty($token_data['access_token'])) {
      $messageStack->add_session('Kein gültiges Etsy Access Token verfügbar. Bitte Verbindung erneuern.', 'error');
      xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
    }

    try {
      if (!function_exists('bx_etsy_update_listing_personalization')) {
        throw new Exception('Personalisierungs-Helper nicht verfügbar.');
      }

      $result = bx_etsy_update_listing_personalization((int)$shop_id, $listing_id, $payload, (string)$token_data['access_token'], $client_id, $shared_secret);

      if (!empty($result['success'])) {
        $response_data = is_array($result['data']) ? $result['data'] : array('message' => 'Antwort ohne Datensatz erhalten.');
        $_SESSION['bx_etsy_personalization_response_json'] = json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      } else {
        $error_text = (string)($result['error'] ?? 'Personalisierung konnte nicht gespeichert werden.');
        $_SESSION['bx_etsy_personalization_response_json'] = json_encode(array('error' => $error_text), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      }

      $_SESSION['bx_etsy_personalization_listing_id'] = (string)$listing_id;
      $_SESSION['bx_etsy_personalization_payload_json'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      $messageStack->add_session('Personalisierung erfolgreich gespeichert.', 'success');
    } catch (Exception $e) {
      $messageStack->add_session('Fehler beim Speichern der Personalisierung: ' . $e->getMessage(), 'error');
    }

    xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
  }

// Token-Status prüfen
if (BX_ETSY_AVAILABLE) {
  $config  = bx_etsy_get_config();
  $shop_id = trim((string)($config['MODULE_BX_ETSY_MANAGER_SHOP_ID'] ?? ''));

  if ($shop_id !== '') {
        $token_query = xtc_db_query("SELECT * FROM bx_etsy_oauth_tokens 
                                      WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
        
        if (xtc_db_num_rows($token_query) > 0) {
            $etsy_connected  = true;
            $etsy_token_data = xtc_db_fetch_array($token_query);
        }
    }
}

// Erfolgs-/Fehlermeldungen aus Session anzeigen
if (isset($_SESSION['etsy_success'])) {
    $messageStack->add($_SESSION['etsy_success'], 'success');
    unset($_SESSION['etsy_success']);
}

if (isset($_SESSION['etsy_error'])) {
    $messageStack->add($_SESSION['etsy_error'], 'error');
    unset($_SESSION['etsy_error']);
}

if (isset($_SESSION['bx_etsy_personalization_response_json'])) {
  $personalization_response_json = (string)$_SESSION['bx_etsy_personalization_response_json'];
}

$personalization_listing_id_value = isset($_SESSION['bx_etsy_personalization_listing_id'])
  ? (string)$_SESSION['bx_etsy_personalization_listing_id']
  : '';

$personalization_payload_value = isset($_SESSION['bx_etsy_personalization_payload_json'])
  ? (string)$_SESSION['bx_etsy_personalization_payload_json']
  : $personalization_sample_json;

// =============================================================================

require_once (DIR_WS_INCLUDES.'head.php');

$messageStack->output();
?>

</head>
<!-- header //-->
<?php require(DIR_WS_INCLUDES.'header.php'); ?>

<!-- header_eof //-->
<!-- body //-->
<table class="tableBody">
  <tr>
    <?php //left_navigation
    if (USE_ADMIN_TOP_MENU == 'false') {
      echo '<td class="columnLeft2">'.PHP_EOL;
      echo '<!-- left_navigation //-->'.PHP_EOL;
      require_once(DIR_WS_INCLUDES.'column_left.php');
      echo '<!-- left_navigation eof //-->'.PHP_EOL;
      echo '</td>'.PHP_EOL;
    }
    ?>
    <!-- body_text //-->
    <td class="boxCenter">
      <div class="pageHeadingImage" style="width: 65px;">
        <?php echo xtc_image(DIR_WS_ICONS.'heading/bx_etsymanager.png', MODULE_BX_ETSY_MANAGER, '', '', 'style="max-height: 32px;"'); ?>
      </div>
      <div class="pageHeading flt-l">
        <?php echo MODULE_BX_ETSY_MANAGER; ?>
        <div class="main pdg2">
          <?php echo MODULE_BX_ETSY_MANAGER_SUBTITLE; ?>
        </div>
      </div>
      <div class="clear"></div>

      <table class="tableCenter" style="margin-top: 5px;">
        <tr>
          <td class="boxCenterLeft">
            <!-- BOF Bereich für eventuelle Filter Features -->
            <div id="headboard">
              <div class="main" style="margin: 5px 10px;"><strong><?php echo MODULE_BX_ETSY_MANAGER; ?></strong></div>
              <div class="main" style="margin: 5px 10px;">&nbsp;</div>
            </div>
            <!-- EOF Bereich für eventuelle Filter Features -->

            <div class="etsy-tabs">
              <ul class="tab-nav">
                <li><a href="#tab-dashboard"><span style="font-size: 14px;">📊</span> Dashboard</a></li>
                <li><a href="#tab-products"><span style="font-size: 14px;">🛍️</span> Produkte</a></li>
                <li><a href="#tab-support"><span style="font-size: 14px;">🛠️</span> Support</a></li>
              </ul>

              <div class="tab-content">

                <!-- TAB 1: DASHBOARD //-->
                <div id="tab-dashboard">

                  <!-- Hauptbereich für zukünftige Features (Produktlisten, Orders, etc.) -->
                  <div class="main">
                    <strong>📦 Produktverwaltung</strong>
                    <p>Demnächst verfügbar: Hier werden Ihre Etsy-Listings angezeigt.</p>
                  </div>
                  
                  <div class="main">
                      <strong>📋 Bestellungen</strong>
                      <p>Demnächst verfügbar: Hier können Sie Ihre Etsy-Bestellungen verwalten.</p>
                  </div>

                  <?php if ($etsy_connected && BX_ETSY_AVAILABLE) { ?>
                  <hr style="margin: 15px 0; border: 0; border-top: 1px solid #ddd;">
                  <div class="main">
                    <strong>🧩 Personalisierung (Etsy API v3)</strong>
                    <p>Testbereich für das neue Personalisierungsmodell mit mehreren Fragen (text, dropdown, file_upload).</p>

                    <?php echo xtc_draw_form('etsy_personalization_get', FILENAME_ETSY_MANAGER, 'action=personalization_get', 'post', 'style="margin: 10px 0 6px 0;"'); ?>
                      <div style="margin-bottom: 8px;">
                        <label for="listing_id_get"><strong>Listing-ID:</strong></label><br>
                        <input type="text" id="listing_id_get" name="listing_id" value="<?php echo htmlspecialchars($personalization_listing_id_value); ?>" style="width: 260px;">
                      </div>
                      <?php echo xtc_button(BUTTON_SEARCH); ?>
                    </form>

                    <?php echo xtc_draw_form('etsy_personalization_update', FILENAME_ETSY_MANAGER, 'action=personalization_update', 'post', 'style="margin: 10px 0;"'); ?>
                      <div style="margin-bottom: 8px;">
                        <label for="listing_id_update"><strong>Listing-ID:</strong></label><br>
                        <input type="text" id="listing_id_update" name="listing_id" value="<?php echo htmlspecialchars($personalization_listing_id_value); ?>" style="width: 260px;">
                      </div>
                      <div style="margin-bottom: 8px;">
                        <label for="personalization_questions_json"><strong>Personalisierungsfragen (JSON):</strong></label><br>
                        <textarea id="personalization_questions_json" name="personalization_questions_json" rows="14" style="width: 100%; max-width: 900px; font-family: Consolas, monospace;"><?php echo htmlspecialchars($personalization_payload_value); ?></textarea>
                      </div>
                      <?php echo xtc_button(BUTTON_SAVE); ?>
                    </form>

                    <?php if (!empty($personalization_response_json)) { ?>
                    <div style="margin-top: 10px;">
                      <strong>API-Antwort:</strong>
                      <pre style="white-space: pre-wrap; background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 10px; max-height: 320px; overflow: auto;"><?php echo htmlspecialchars($personalization_response_json); ?></pre>
                    </div>
                    <?php } ?>
                  </div>
                  <?php } ?>

                </div>
                <!-- end tab-dashboard //-->

                <!-- TAB 2: PRODUKTE //-->
                <div id="tab-products">
                  Produktliste
                </div>
                <!-- end tab-products //-->

                <!-- TAB 3: SUPPORT-AKTIONEN //-->
                <div id="tab-support">
                  Support-Aktionen
                </div>
                <!-- end tab-support //-->

              </div>
            </div>

          </td>
          <td class="boxRight">
<?php
  // =============================================================================
  // OAuth Status Box in Sidebar
  // =============================================================================
  
  $heading  = array();
  $contents = array();
  
  $heading[] = array('text' => '<strong>🔗 Etsy Verbindung</strong>');
  
  if (!BX_ETSY_AVAILABLE) {
      $contents[] = array('text' => '<div class="warning_message" style="font-size: 11px;">⚠️ Etsy SDK nicht gefunden</div>');
  } elseif ($etsy_connected) {
      // Verbunden
      $contents[] = array('text' => '<div style="background: linear-gradient(135deg, #00b894 0%, #00cec9 100%); border: 3px solid #00b894; border-radius: 8px; padding: 0px 20px 10px 20px; margin: 5px 0; text-align: center; box-shadow: 0 4px 15px rgba(0,184,148,0.4); position: relative; overflow: hidden;">
                                      <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.2) 0%, transparent 70%);"></div>
                                      <span style="color: #ffffff; font-size: 48px; display: block; margin-bottom: 10px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); animation: pulse 2s ease-in-out infinite;">🎉</span>
                                      <strong style="color: #ffffff; display: block; font-size: 16px; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">✨ VERBUNDEN ✨</strong>
                                    </div>');
      $contents[] = array('text' => '<strong>Status:</strong> Verbunden');
      $contents[] = array('text' => '<strong>Shop:</strong> ' . htmlspecialchars($etsy_token_data['shop_id']));
      $contents[] = array('text' => '<strong>User ID:</strong> ' . htmlspecialchars($etsy_token_data['user_id']));
      $contents[] = array('text' => '<strong>Token läuft ab:</strong><br>' . date('d.m.Y H:i', strtotime($etsy_token_data['expires_at'])) . ' Uhr');
      
      // Verbleibende Zeit
      $expires_timestamp = strtotime($etsy_token_data['expires_at']);
      $contents[] = array('text' => '<div id="bx-etsy-token-countdown" data-expires-timestamp="' . (int)$expires_timestamp . '">Lade Timer...</div>');
      
      $contents[] = array('text' => '<div style="margin-top: 10px;">' . xtc_button_link('⭕ Trennen', xtc_href_link(FILENAME_ETSY_MANAGER, 'action=disconnect')) . '</div>');
      
  } else {
      // Nicht verbunden
      $contents[] = array('text' => '<div style="background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%); border: 2px solid #e17055; border-radius: 6px; padding: 0px 20px 10px 20px; margin: 5px 0; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                      <span style="color: #d63031; font-size: 36px; display: block; margin-bottom: 10px;">❌</span>
                                      <strong style="color: #2d3436; display: block; margin-bottom: 5px;">Nicht verbunden</strong>
                                    </div>');
      $contents[] = array('align' => 'center', 'text' => 'Verbinden Sie Ihren Shop mit Etsy');
      $contents[] = array('align' => 'center', 'text' => '<div style="margin-top: 10px;">' . xtc_button_link('🔗 Verbinden', xtc_href_link(FILENAME_ETSY_MANAGER, 'action=connect')) . '</div>');
      $contents[] = array('align' => 'center', 'text' => '<span style="color: #666;">Sie werden zu Etsy weitergeleitet</span>');
  }
  
  if ( (xtc_not_null($heading)) && (xtc_not_null($contents)) ) {
    $box = new box;
    echo $box->infoBox($heading, $contents);
  }
  
  // =============================================================================
  // Features Box (nur wenn verbunden)
  // =============================================================================
  
  if ($etsy_connected) {
      $heading  = array();
      $contents = array();
      
      $heading[]  = array('text' => '<strong>📦 Funktionen</strong>');
      $contents[] = array('text' => '✅ Produkte hochladen');
      $contents[] = array('text' => '✅ Listings verwalten');
      $contents[] = array('text' => '✅ Bestellungen abrufen');
      $contents[] = array('text' => '✅ Lagerbestände sync');
      $contents[] = array('text' => '✅ Bilder hochladen');
      
      if ( (xtc_not_null($heading)) && (xtc_not_null($contents)) ) {
        $box = new box;
        echo $box->infoBox($heading, $contents);
      }
  }
  
  // =============================================================================
  // Hinweise
  // =============================================================================

  $heading  = array();
  $contents = array();

  $heading[]  = array('text' => '<strong>ℹ️ Hinweise</strong>');
  $contents[] = array('text' => '<strong>Erste Schritte:</strong><br>
                                1. Konfigurieren Sie das Modul mit Ihren Etsy API-Zugangsdaten<br>
                                2. Klicken Sie auf "Mit Etsy verbinden"<br>
                                3. Autorisieren Sie die Verbindung auf Etsy.com');
  $contents[] = array('text' => '<strong>Sicherheit:</strong><br>
                                Ihre Zugangsdaten werden verschlüsselt gespeichert und nur für die API-Kommunikation verwendet.');
  $contents[] = array('text' => '<strong>Token-Verwaltung:</strong><br>
                                Access Token läuft nach 1 Stunde ab und wird automatisch erneuert. Refresh Token ist 90 Tage gültig.');

  if ( (xtc_not_null($heading)) && (xtc_not_null($contents)) ) {
    $box = new box;
    echo $box->infoBox($heading, $contents);
  }
  
  // =============================================================================
  // Etsy Developer Portal Link
  // =============================================================================
  
    $heading  = array();
    $contents = array();
    
    $heading[]  = array('text' => '<strong>🔧 Etsy API</strong>');
    $contents[] = array('text' => '<a href="https://www.etsy.com/developers/your-apps" target="_blank" class="button">🔗 Developer Portal</a>');
    $contents[] = array('text' => 'Verwalten Sie Ihre App-Zugangsdaten');
    
    if ( (xtc_not_null($heading)) && (xtc_not_null($contents)) ) {
      $box = new box;
      echo $box->infoBox($heading, $contents);
    }
?>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<!-- body_eof //-->
<!-- footer //-->
<?php require(DIR_WS_INCLUDES.'footer.php'); ?>
<!-- footer_eof //-->

</body>
</html>
<?php require(DIR_WS_INCLUDES.'application_bottom.php'); ?>
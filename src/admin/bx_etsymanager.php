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

require(DIR_FS_CATALOG.DIR_WS_CLASSES . 'xtcPrice.php');
$xtPrice = new xtcPrice($_SESSION['currency'],'');

if (!defined('BX_ETSY_AVAILABLE')) {
  require_once(DIR_FS_CATALOG.'includes/classes/bx_dependency_resolver.php');

  try {
    bx_dependency_resolver::require('modified_etsy');
    define('BX_ETSY_AVAILABLE', true);
  } catch (Exception $e) {
    define('BX_ETSY_AVAILABLE', false);
    error_log('BX Etsy nicht verfügbar: ' . $e->getMessage());
  }
}

//display per page
defined('MAX_DISPLAY_LIST_ETSY') or define('MAX_DISPLAY_LIST_ETSY', 10);
$cfg_max_display_results_key = 'MAX_DISPLAY_LIST_ETSY';
$page_max_display_results    = xtc_cfg_save_max_display_results($cfg_max_display_results_key);

// =============================================================================
// OAuth Flow Handler
// =============================================================================

$etsy_connected  = false;
$etsy_token_data = null;
$oauth_url       = '';

$actionWithList = array('connect', 
                        'disconnect', 
                        'personalization_get', 
                        'personalization_update', 
                        'edit', 
                        'delete', 
                        'details', 
                        'save', 
                        'update');

$action = (isset($_GET['action']) && in_array($_GET['action'], $actionWithList) ? $_GET['action'] : null);
$page   = (isset($_GET['page']) ? (int)$_GET['page']   : 1);

// Action: Mit Etsy verbinden
$bx_edit_etsy_order_id = ($action === 'edit' && isset($_GET['etsy_order_id']))
  ? trim((string)$_GET['etsy_order_id'])
  : '';

// Action: Mit Etsy verbinden
if ($action == 'connect' && BX_ETSY_AVAILABLE) {

  $client_id     = MODULE_BX_ETSY_MANAGER_KEYSTRING;
  $shared_secret = MODULE_BX_ETSY_MANAGER_SHARED_SECRET;
  $shop_id       = MODULE_BX_ETSY_MANAGER_SHOP_ID;
  $redirect_uri  = MODULE_BX_ETSY_MANAGER_REDIRECT_URI;

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
if ($action == 'disconnect') {
  $shop_id = trim((string)MODULE_BX_ETSY_MANAGER_SHOP_ID);

  if ($shop_id !== '') {
        xtc_db_query("DELETE FROM bx_etsy_oauth_tokens WHERE shop_id = '" . xtc_db_input($shop_id) . "'");
        $messageStack->add_session('✅ Etsy-Verbindung wurde getrennt.', 'success');
    }
    
    xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
}


/**
 * START Personalisierungsfragen
 * Vorläufig hier platziert, bis ich weiß wohin damit.
 */
$personalization_response_json = '';
// Beispiel für Personalisierungsfragen (neues Etsy-Personalisierungsmodell mit mehreren Fragen)
$personalization_sample_json = "{\n  \"personalization_questions\": [\n    {\n      \"question_text\": \"Bitte Wunschtext angeben\",\n      \"question_type\": \"text_input\",\n      \"required\": true,\n      \"max_allowed_characters\": 100\n    }\n  ]\n}";

// Action: Personalisierung für ein Listing laden (neues Etsy-Personalisierungsmodell)
if ($action == 'personalization_get' && BX_ETSY_AVAILABLE) {
  $listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

  if ($listing_id <= 0) {
    $messageStack->add_session('Bitte eine gültige Listing-ID angeben.', 'error');
    xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
  }

  if (!function_exists('bx_etsy_get_valid_token')) {
    $messageStack->add_session('BX Etsy Hilfsfunktionen sind nicht geladen.', 'error');
    xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
  }

  $shop_id = trim((string)MODULE_BX_ETSY_MANAGER_SHOP_ID);
  $client_id = trim((string)MODULE_BX_ETSY_MANAGER_KEYSTRING);
  $shared_secret = trim((string)MODULE_BX_ETSY_MANAGER_SHARED_SECRET);

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
if ($action == 'personalization_update' && BX_ETSY_AVAILABLE) {
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

  if (!function_exists('bx_etsy_get_valid_token')) {
    $messageStack->add_session('BX Etsy Hilfsfunktionen sind nicht geladen.', 'error');
    xtc_redirect(xtc_href_link(FILENAME_ETSY_MANAGER));
  }

  $shop_id       = trim((string)MODULE_BX_ETSY_MANAGER_SHOP_ID);
  $client_id     = trim((string)MODULE_BX_ETSY_MANAGER_KEYSTRING);
  $shared_secret = trim((string)MODULE_BX_ETSY_MANAGER_SHARED_SECRET);

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

if (isset($_SESSION['bx_etsy_personalization_response_json'])) {
  $personalization_response_json = (string)$_SESSION['bx_etsy_personalization_response_json'];
}

$personalization_listing_id_value = isset($_SESSION['bx_etsy_personalization_listing_id'])
  ? (string)$_SESSION['bx_etsy_personalization_listing_id']
  : '';

$personalization_payload_value = isset($_SESSION['bx_etsy_personalization_payload_json'])
  ? (string)$_SESSION['bx_etsy_personalization_payload_json']
  : $personalization_sample_json;
/* ENDE Personalisierungsfragen */


// Erfolgs-/Fehlermeldungen aus Session anzeigen
if (isset($_SESSION['etsy_success'])) {
    $messageStack->add($_SESSION['etsy_success'], 'success');
    unset($_SESSION['etsy_success']);
}

if (isset($_SESSION['etsy_error'])) {
    $messageStack->add($_SESSION['etsy_error'], 'error');
    unset($_SESSION['etsy_error']);
}

$bx_dashboard_load_mode     = 'lazy_preload';
$bx_dashboard_allowed_modes = array('eager', 'lazy', 'lazy_preload');

if (defined('MODULE_BX_ETSY_MANAGER_DASHBOARD_LOAD_MODE')) {
  $bx_dashboard_load_mode_candidate = strtolower(trim((string)MODULE_BX_ETSY_MANAGER_DASHBOARD_LOAD_MODE));
  if (in_array($bx_dashboard_load_mode_candidate, $bx_dashboard_allowed_modes, true)) {
    $bx_dashboard_load_mode = $bx_dashboard_load_mode_candidate;
  }
}

$bx_dashboard_is_eager = ($bx_dashboard_load_mode === 'eager');
$bx_etsy_tab_dashboard = array('content' => '', 'right' => '');
if ($bx_dashboard_is_eager) {
  $bx_etsy_tab_dashboard = bx_etsy_render_tab_dashboard();
}

// Token-Status pruefen (fuer evtl. andere Stellen im Template, z.B. Header-Hinweise)
list($etsy_connected, $etsy_token_data) = bx_etsy_get_connection_status();
// =============================================================================
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
        <?php echo xtc_image(DIR_WS_ICONS.'heading/bx_etsymanager.png', MODULE_BX_ETSY_MANAGER, '', '', 'style="height:100%;"'); ?>
      </div>
      <div class="pageHeading pdg2 flt-l">
        <?php echo MODULE_BX_ETSY_MANAGER; ?>
        <div class="main pdg2"><?php echo MODULE_BX_ETSY_MANAGER_SUBTITLE; ?></div> 
      </div>
      <div class="clear"></div>

      <?php if (function_exists('bx_etsy_mock_enabled') && bx_etsy_mock_enabled()) { ?>
      <div style="background-color: #ffcc00; color: #000; padding: 10px; text-align: center; font-weight: bold; border: 2px solid #cc0000; margin-top: 15px; margin-bottom: 5px;">
        ⚠️ MOCK-MODUS AKTIV &ndash; Szenario: <?php echo htmlspecialchars(bx_etsy_get_mock_scenario()); ?>
      </div>
      <?php } ?>

      <div class="bx-grid">
        <!-- Hauptinhalt (Links) -->
        <section class="bx-main-content">

          <div class="bx-headboard">
            <strong><?php echo MODULE_BX_ETSY_MANAGER; ?></strong>
          </div>

          <?php if ($action === 'edit') { ?>

          <article class="bx-panel">
            <div class="bx-panel-title">✏️ Bestellung bearbeiten</div>
            <p>
              Diese Seite ist noch nicht implementiert.<br>
              Erkannte Etsy-Bestellungs-ID:
              <strong><?php echo $bx_edit_etsy_order_id !== '' ? htmlspecialchars($bx_edit_etsy_order_id, ENT_QUOTES, 'UTF-8') : '(keine übergeben)'; ?></strong>
            </p>
            <p>
              <a href="<?php echo xtc_href_link(FILENAME_ETSY_MANAGER); ?>">&larr; Zurück zur Übersicht</a>
            </p>
          </article> <!-- bx-panel -->

          <?php } else { ?>

          <article class="etsy-tabs bx-panel" data-dashboard-load-mode="<?php echo htmlspecialchars($bx_dashboard_load_mode, ENT_QUOTES, 'UTF-8'); ?>">
            <ul class="tab-nav">
              <li><a href="#tab-dashboard" data-tab="dashboard"><span style="font-size: 14px;">📊</span> Dashboard</a></li>
              <li><a href="#tab-orders" data-tab="orders"><span style="font-size: 14px;">🛍️</span> Bestellungen</a></li>
              <li><a href="#tab-listings" data-tab="listings"><span style="font-size: 14px;">🛍️</span> Listings</a></li>
              <li><a href="#tab-support" data-tab="support"><span style="font-size: 14px;">🛠️</span> Support</a></li>
            </ul>

            <div class="tab-content">

              <!-- TAB 1: DASHBOARD //-->
              <div id="tab-dashboard" data-loaded="<?php echo $bx_dashboard_is_eager ? '1' : '0'; ?>">
              <?php
                if ($bx_dashboard_is_eager) { 
                echo $bx_etsy_tab_dashboard['content'];
                } else { ?>
                <div class="bx-etsy-tab-loading">
                  <div class="ms-spinner"></div>
                  <div>⏳ Lade Dashboard…</div>
                </div>
              <?php } ?>
              </div>
              <!-- end tab-dashboard //-->

              <!-- TAB 2: Bestellungen (lazy per AJAX geladen) //-->
              <div id="tab-orders" data-loaded="0">
                <div class="bx-etsy-tab-loading">
                  <div class="ms-spinner"></div>
                  <div>⏳ Lade Bestellungen…</div>
                </div>
              </div>
              <!-- end tab-orders //-->

              <!-- TAB 3: Listings (lazy per AJAX geladen) //-->
              <div id="tab-listings" data-loaded="0">
                <div class="bx-etsy-tab-loading">
                  <div class="ms-spinner"></div>
                  <div>⏳ Lade Listings…</div>
                </div>
              </div>
              <!-- end tab-listings //-->

              <!-- TAB 4: Support (lazy per AJAX geladen) //-->
              <div id="tab-support" data-loaded="0">
                <div class="bx-etsy-tab-loading">
                  <div class="ms-spinner"></div>
                  <div>⏳ Lade Support…</div>
                </div>
              </div>
              <!-- end tab-support //-->

            </div>
          </article> <!-- etsy-tabs -->

          <?php } ?>

        </section>
        
        <!-- Seitenleiste (Rechts) -->
        <aside class="bx-sidebar">
          
          <div id="tab-dashboard-right">
          <?php
          if ($bx_dashboard_is_eager) { 
            echo $bx_etsy_tab_dashboard['right']; 
          } else { ?>
        
          <div class="bx-headboard bx-headboard--secondary">
            <strong>BX Module - Details</strong>
          </div>
          
          <article class="bx-panel">
            <div class="bx-panel-title">🔗 Etsy Verbindung</div>
            <div class="bx-etsy-tab-loading">
              <div class="ms-spinner"></div>
              <div>⏳ Lade Dashboard-Details…</div>
            </div>
          </article> <!-- bx-panel -->
          
          <?php } ?>
          </div> <!-- tab-dashboard-right -->

          <div id="tab-orders-right">
            <div class="bx-headboard bx-headboard--secondary">
              <strong>Bestelldetails</strong>
            </div>
            <article class="bx-panel">
              <div class="bx-etsy-tab-loading">
                <div class="ms-spinner"></div>
                <div>⏳ Lade Bestelldetails…</div>
              </div>
            </article> <!-- bx-panel -->

          </div> <!-- tab-orders-right wird per AJAX befüllt -->

          <div id="tab-listings-right"></div> <!-- tab-listings-right wird per AJAX befüllt -->

          <div id="tab-support-right"></div> <!-- tab-support-right wird per AJAX befüllt -->

        </aside>
      </div> <!-- bx-grid -->

    </td> <!-- end boxCenter //-->
  </tr>
</table>
<!-- body_eof //-->
<!-- footer //-->
<?php require(DIR_WS_INCLUDES.'footer.php'); ?>
<!-- footer_eof //-->

</body>
</html>
<?php require(DIR_WS_INCLUDES.'application_bottom.php'); ?>
<?php
/* -----------------------------------------------------------------------------------------
   $Id: bx_etsymanager.php 00000 2026-01-15 00:00:00Z benax $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   -----------------------------------------------------------------------------------------
   based on: 
   (c) 2000-2001 The Exchange Project  (earlier name of osCommerce)
   (c) 2002-2003 osCommerce(flat.php,v 1.6 2003/02/16); www.oscommerce.com 
   (c) 2003	 nextcommerce (flat.php,v 1.4 2003/08/13); www.nextcommerce.org
   (c) 2006 xt:Commerce; www.xt-commerce.com

   Released under the GNU General Public License 
   ---------------------------------------------------------------------------------------*/

  define('MODULE_BX_ETSY_MANAGER_TITLE', 'BX Etsy Manager');
  define('MODULE_BX_ETSY_MANAGER_DESC', '
    <details class="bxac-card">
    <summary class="bxac-summary" style="list-style: none;">
      <span class="bxac-arrow">▸</span>
      ' . xtc_image(DIR_WS_ICONS.'heading/bx_etsymanager.png', 'BX Etsy Manager', '', '', 'style="max-height: 32px; margin: 2px;"') . '
      <span class="bxac-title">BX Etsy Manager</span>
    </summary>
    <div class="bxac-body">
      <h3 style="margin-top: 0;">Artikelmanger für Etsy</h3>
      <p>Mit dem BX Etsy Manager können Sie Ihre Produkte direkt aus dem modified eCommerce Shop in Ihren Etsy Shop exportieren und verwalten</p>
    </div>
  </details>');
  
  define('MODULE_BX_ETSY_MANAGER_CONFIG_GROUP_TITLE', 'BX Etsy Manager Konfiguration');
  define('MODULE_BX_ETSY_MANAGER_CONFIG_GROUP_DESC', 'Modul einstellen und konfigurieren');

  define('MODULE_BX_ETSY_MANAGER_STATUS_TITLE', 'Status');
  define('MODULE_BX_ETSY_MANAGER_STATUS_DESC', 'Aktiviert den BX Etsy Manager');
  
  define('MODULE_BX_ETSY_MANAGER_VERSION_TITLE', 'Version');
  define('MODULE_BX_ETSY_MANAGER_VERSION_DESC', 'Installierte Version des BX Etsy Manager Moduls');
  
  define('MODULE_BX_ETSY_MANAGER_SORT_ORDER_TITLE', 'Sortierreihenfolge');
  define('MODULE_BX_ETSY_MANAGER_SORT_ORDER_DESC', 'Sortierreihenfolge des BX Etsy Manager Moduls im Systemmenü');
  
  define('MODULE_BX_ETSY_MANAGER_CONFIG_ID_TITLE', 'Konfigurationsgruppen-ID');
  define('MODULE_BX_ETSY_MANAGER_CONFIG_ID_DESC', 'Automatisch generierte Konfigurationsgruppen-ID des BX Etsy Manager Moduls');

  define('MODULE_BX_ETSY_MANAGER_KEYSTRING_TITLE', 'Etsy API Keystring');
  define('MODULE_BX_ETSY_MANAGER_KEYSTRING_DESC', 'Ihre Etsy App API Keystring (Client ID) aus dem <a href="https://www.etsy.com/developers/your-apps" target="_blank">Etsy Developer Portal</a>');
  
  define('MODULE_BX_ETSY_MANAGER_SHARED_SECRET_TITLE', 'Etsy Shared Secret');
  define('MODULE_BX_ETSY_MANAGER_SHARED_SECRET_DESC', 'Ihr Etsy App Shared Secret aus dem <a href="https://www.etsy.com/developers/your-apps" target="_blank">Etsy Developer Portal</a>');

  define('MODULE_BX_ETSY_MANAGER_WEBHOOK_SECRET_TITLE', 'Etsy Webhook Secret');
  define('MODULE_BX_ETSY_MANAGER_WEBHOOK_SECRET_DESC', 'Ihr Etsy Webhook Secret aus dem <a href="https://www.etsy.com/developers/webhooks-consumer-portal/1455865997243" target="_blank">Etsy Developer Portal</a>');
  
  define('MODULE_BX_ETSY_MANAGER_SHOP_ID_TITLE', 'Etsy Shop ID');
  define('MODULE_BX_ETSY_MANAGER_SHOP_ID_DESC', 'Ihr Etsy Shop ID (z.B. 123456789)');
  
  define('MODULE_BX_ETSY_MANAGER_REDIRECT_URI_TITLE', 'OAuth Redirect URI');
  define('MODULE_BX_ETSY_MANAGER_REDIRECT_URI_DESC', 'Die Callback-URL für OAuth 2.0 Authentifizierung. Muss im <a href="https://www.etsy.com/developers/your-apps" target="_blank">Etsy Developer Portal</a> als Callback URL registriert sein. Standard: ' . HTTPS_SERVER . '/callback/bx_etsymanager/bx_etsymanager.php');

  define('MODULE_BX_ETSY_MANAGER_DASHBOARD_CACHE_MINUTES_TITLE', 'Dashboard Cache Intervall (Minuten)');
  define('MODULE_BX_ETSY_MANAGER_DASHBOARD_CACHE_MINUTES_DESC', 'Intervall in Minuten für den Etsy-Dashboard-Cache. 0 = Cache deaktiviert, 180 = 3 Stunden.');

  define('MODULE_BX_ETSY_MANAGER_DASHBOARD_REQUEST_TIMEOUT_TITLE', 'Dashboard Request Timeout (Sekunden)');
  define('MODULE_BX_ETSY_MANAGER_DASHBOARD_REQUEST_TIMEOUT_DESC', 'Timeout in Sekunden für die Etsy-Dashboard-Anfragen. Standard: 8 Sekunden.');

  define('MODULE_BX_ETSY_MANAGER_MOCK_MODE_TITLE', 'Mock Mode');
  define('MODULE_BX_ETSY_MANAGER_MOCK_MODE_DESC', 'Aktiviert den Mock Mode für die Etsy-API.');
  define('MODULE_BX_ETSY_MANAGER_MOCK_SCENARIO_TITLE', 'Mock Scenario');
  define('MODULE_BX_ETSY_MANAGER_MOCK_SCENARIO_DESC', 'Wählen Sie ein Mock-Szenario für die Etsy-API.');

  define('MODULE_BX_ETSY_MANAGER_SCHEDULED_TASKS_TITLE', 'Scheduled Tasks aktivieren');
  define('MODULE_BX_ETSY_MANAGER_SCHEDULED_TASKS_DESC', 'Aktiviert die periodische Synchronisation der Etsy-Bestellungen.');
  define('MODULE_BX_ETSY_MANAGER_CHECK_INTERVAL_TITLE', 'Prüfintervall');
  define('MODULE_BX_ETSY_MANAGER_CHECK_INTERVAL_DESC', 'Intervall für den Etsy-Order-Sync.');
  define('MODULE_BX_ETSY_MANAGER_CHECK_UNIT_TITLE', 'Prüfeinheit');
  define('MODULE_BX_ETSY_MANAGER_CHECK_UNIT_DESC', 'Einheit für den Etsy-Order-Sync (m, h, d, w).');
  
<?php
/* -----------------------------------------------------------------------------------------
  $Id: admin/includes/modules/system/bx_etsymanager.php 1000 2025-12-30 12:00:00Z benax $

  modified eCommerce Shopsoftware
  http://www.modified-shop.org

  Copyright (c) 2009 - 2013 [www.modified-shop.org]
  -----------------------------------------------------------------------------------------
  Released under the GNU General Public License
  ---------------------------------------------------------------------------------------*/

  defined( '_VALID_XTC' ) or die( 'Direct Access to this location is not allowed.' );
  
  class bx_etsymanager {
    public string $code;
    public string $version;
    public string $title;
    public string $description;
    public int $sort_order;
    public string $enabled;
    public string $development_status;
    private bool $_check;

    public function __construct() {
      $this->code        = 'bx_etsymanager';
      $this->version     = '0.5.0';
      $this->title       = MODULE_BX_ETSY_MANAGER_TITLE;
      $this->description = MODULE_BX_ETSY_MANAGER_DESC;
      $this->sort_order  = defined('MODULE_BX_ETSY_MANAGER_SORT_ORDER') ? MODULE_BX_ETSY_MANAGER_SORT_ORDER : 0;
      $this->enabled     = ((defined('MODULE_BX_ETSY_MANAGER_STATUS') && MODULE_BX_ETSY_MANAGER_STATUS == 'True') ? true : false);
      $this->development_status = '';
      }

    /**
       * Returns whether the module is installed.
       * @return bool
       * 
       * */
    public function check(): bool {
      if (!isset($this->_check)) {
        if (defined('MODULE_BX_ETSY_MANAGER_STATUS')) {
          $this->_check = true;
        } else {
          $check_query = xtc_db_query("SELECT configuration_value 
                                        FROM " . TABLE_CONFIGURATION . " 
                                        WHERE configuration_key = 'MODULE_BX_ETSY_MANAGER_STATUS'");
          $this->_check = xtc_db_num_rows($check_query);
        }
      }
      return $this->_check;
    }

    /**
      * Actions performed when the user clicks the install button.
      *
      * @return void
      */
    public function install(): void {
      $freeId_query = xtc_db_query("SELECT MIN(configuration_group_id+1) AS id 
                                  FROM ".TABLE_CONFIGURATION_GROUP." 
                                  WHERE (configuration_group_id+1) 
                                          NOT IN (SELECT configuration_group_id FROM ".TABLE_CONFIGURATION_GROUP." WHERE configuration_group_id IS NOT NULL);");
      $freeId = xtc_db_fetch_array($freeId_query);
      $freeIdValue = (int)$freeId["id"];

      $freeSort_query = xtc_db_query("SELECT MIN(sort_order+1) AS sort_order 
                                            FROM ".TABLE_CONFIGURATION_GROUP." 
                                            WHERE (sort_order+1) 
                                          NOT IN (SELECT sort_order FROM ".TABLE_CONFIGURATION_GROUP." WHERE sort_order IS NOT NULL);");
      $freeSort = xtc_db_fetch_array($freeSort_query);
      $freeSortValue = (int)$freeSort["sort_order"];

      xtc_db_query("ALTER TABLE ".TABLE_ADMIN_ACCESS." ADD ".$this->code." INTEGER(1) DEFAULT 0");
      xtc_db_query("UPDATE ".TABLE_ADMIN_ACCESS." SET ".$this->code." = 1");

      xtc_db_query("INSERT INTO ".TABLE_CONFIGURATION_GROUP." ( configuration_group_id, 
                                                                      configuration_group_title, 
                                                                      configuration_group_description, 
                                                                      sort_order, 
                                                                      visible) 
                  VALUES ( ".$freeIdValue.", 'BX Etsy Manager Konfiguration', 'Modul einstellen und konfigurieren', ".$freeSortValue.", 1)");

      xtc_db_query("INSERT INTO ".TABLE_CONFIGURATION." ( configuration_key, 
                                                                configuration_value, 
                                                                configuration_group_id, 
                                                                sort_order, 
                                                                date_added, 
                                                                use_function, 
                                                                set_function )
                  VALUES ('MODULE_BX_ETSY_MANAGER_STATUS', 'True', '".$freeIdValue."', '1', NOW(), '', 'xtc_cfg_select_option(array(\'True\', \'False\'), '),
                    ('MODULE_BX_ETSY_MANAGER_VERSION', '".$this->version."', '".$freeIdValue."', '2', NOW(), '', ''),
                    ('MODULE_BX_ETSY_MANAGER_CONFIG_ID', '".$freeIdValue."', '".$freeIdValue."', '3', NOW(), '', 'bx_configuration_field_version('),
                    ('MODULE_BX_ETSY_MANAGER_KEYSTRING', '', '".$freeIdValue."', '4', NOW(), '', ''),
                    ('MODULE_BX_ETSY_MANAGER_SHARED_SECRET', '', '".$freeIdValue."', '5', NOW(), '', ''),
                    ('MODULE_BX_ETSY_MANAGER_SHOP_ID', '', '".$freeIdValue."', '6', NOW(), '', 'bx_configuration_field_version('),
                    ('MODULE_BX_ETSY_MANAGER_REDIRECT_URI', '', '".$freeIdValue."', '7', NOW(), '', '');");
      
      // Create OAuth tokens table
      xtc_db_query("CREATE TABLE bx_etsy_oauth_tokens (
          id INT(11) NOT NULL AUTO_INCREMENT,
          shop_id VARCHAR(255) NOT NULL,
          access_token TEXT NOT NULL,
          refresh_token TEXT NOT NULL,
          token_type VARCHAR(50) DEFAULT 'Bearer',
          expires_at DATETIME NOT NULL,
          scopes TEXT,
          user_id VARCHAR(50),
          created_at DATETIME NOT NULL,
          updated_at DATETIME NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY shop_id (shop_id),
          KEY expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

      // Create OAuth state table
      xtc_db_query("CREATE TABLE IF NOT EXISTS bx_etsy_oauth_state (
          id int(11) NOT NULL AUTO_INCREMENT,
          state varchar(255) NOT NULL,
          code_verifier text NOT NULL,
          scopes text DEFAULT NULL,
          shop_id varchar(255) DEFAULT NULL,
          created_at datetime NOT NULL,
          expires_at datetime NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY state (state),
          KEY expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    public function update() {}
      
    /**
      * Actions performed when the user clicks the uninstall button.
      *
      * @return void
      */
      
    public function remove(): void {
      xtc_db_query("DELETE FROM ".TABLE_CONFIGURATION." WHERE configuration_key in ('".implode("', '", $this->keys())."')");
      xtc_db_query("DROP TABLE IF EXISTS bx_etsy_oauth_tokens");
      xtc_db_query("DROP TABLE IF EXISTS bx_etsy_oauth_state");
      xtc_db_query("ALTER TABLE ".TABLE_ADMIN_ACCESS." DROP ".$this->code);
    }

    /**
      * Configuration keys used by the module. Used when installing and removing the module.
      *
      * @return array
      */
    public function keys(): array {
      $keys = array('MODULE_BX_ETSY_MANAGER_VERSION',
                    'MODULE_BX_ETSY_MANAGER_STATUS',
                    'MODULE_BX_ETSY_MANAGER_CONFIG_ID',
                    'MODULE_BX_ETSY_MANAGER_KEYSTRING',
                    'MODULE_BX_ETSY_MANAGER_SHARED_SECRET',
                    'MODULE_BX_ETSY_MANAGER_SHOP_ID',
                    'MODULE_BX_ETSY_MANAGER_REDIRECT_URI',
                  );
      return $keys;
    }
    
    public function process() { }
      
    /**
      * Additional HTML to show during module configuration.
      *
      * @return array
      */
      
    public function display() {
      return array('text' => '<div style="text-align: center;">'.xtc_button(BUTTON_SAVE).xtc_button_link(BUTTON_CANCEL, xtc_href_link(FILENAME_MODULE_EXPORT, 'set='.$_GET['set'].'&module='.$this->code))."</div>");
    }
      
    /**
      * Action to perform when the configuration key '_VERSION' is being displayed.
      *
      * @param string $value
      * @param string $constant
      *
      * @return string
      */
    public function configurationFieldVersion(string $value, string $constant): string {
      return xtc_draw_input_field( 'configuration['.$constant.']', $value, 'readonly="true" style="opacity: 0.4;"');
    }
      
    public function get_bx_etsy_manager_group_id() {
      $result = '';
      $result_query_raw = xtc_db_query("SELECT configuration_value AS value 
                                            FROM ".TABLE_CONFIGURATION."
                                          WHERE configuration_key = 'MODULE_BX_ETSY_MANAGER_CONFIG_ID'");
      if( 0 < xtc_db_num_rows($result_query_raw)) {
        $result_query= xtc_db_fetch_array($result_query_raw);
        $result = $result_query['value'];
      }
      return $result;
    }
  }
  
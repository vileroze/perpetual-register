<?php
/**
 * Plugin Name: Perpetual Register
 * Description: A plugin to manage a perpetual register with CSV data import functionality.
 * Version: 1.0.0
 * Author: WP Creative
 * Text Domain: perpetual-register
 */


if (!defined('ABSPATH')) {
    exit;
}

define('PPR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PPR_VERSION', '1.0.0');


class PerpetualRegister {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'perpetual_register_entries';
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function activate() {
        $this->create_table();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }

    private function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate(); //ensures the table is created with the correct charset
        
        $sql = "CREATE TABLE {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            org_id int(11) NOT NULL,
            entry varchar(255) NOT NULL,
            lifestat text,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

}

new PerpetualRegister();
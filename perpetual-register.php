<?php
/**
 * Plugin Name: Perpetual Register
 * Description: A plugin to manage a perpetual register with CSV data import functionality.
 * Version: 1.0.0
 * Author: WP Creative
 * Author URI: https://wpcreative.com.au
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

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
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

    public function add_admin_menu() {
        add_options_page(
            __('Perpetual Register Manager', 'perpetual-register'),
            __('Perpetual Register Manager', 'perpetual-register'),
            'manage_options',
            'perpetual-register',
            array($this, 'admin_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_perpetual-register') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'ppr-admin-js',
            PPR_PLUGIN_URL . 'assets/admin/admin.js',
            array('jquery'),
            PPR_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ppr-admin-css',
            PPR_PLUGIN_URL . 'assets/admin/admin.css',
            array(),
            PPR_VERSION
        );
        
        wp_localize_script('ppr-admin-js', 'ppr_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ppr_nonce')
        ));
    }


    public function enqueue_public_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'ppr-public-js',
            PPR_PLUGIN_URL . 'assets/public/public.js',
            array('jquery'),
            PPR_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ppr-public-css',
            PPR_PLUGIN_URL . 'assets/public/public.css',
            array(),
            PPR_VERSION
        );
    }


    public function admin_page() {
        ?>
        <div>
            <h1><?php _e('Perpetual Data Manager', 'perpetual-register'); ?></h1>
        </div>
        <?php
    }

}

new PerpetualRegister();
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


class PerpetualRegister
{

    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'perpetual_register_entries';

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));

        add_action('wp_ajax_ppr_upload_csv', array($this, 'handle_csv_upload'));
        add_action('wp_ajax_ppr_get_data', array($this, 'get_existing_data'));

        // Shortcode
        add_shortcode('perpetual_data', array($this, 'display_perpetual_data'));
    }

    public function activate()
    {
        $this->create_table();
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }

    private function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate(); //ensures the table is created with the correct charset

        $sql = "CREATE TABLE {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            org_id int(11) NOT NULL UNIQUE,
            entry varchar(255) NOT NULL,
            lifestats text
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_admin_menu()
    {
        add_options_page(
            __('Perpetual Register Manager', 'perpetual-register'),
            __('Perpetual Register Manager', 'perpetual-register'),
            'manage_options',
            'perpetual-register',
            array($this, 'admin_page')
        );
    }

    public function enqueue_admin_scripts($hook)
    {
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

        wp_enqueue_script(
            'ppr-papaparse-js',
            'https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js',
            array('jquery'),
            '5.4.1',
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


    public function enqueue_public_scripts()
    {
        wp_enqueue_script('jquery');

        //return if shortcode not found
        if (!is_singular() || !has_shortcode(get_post()->post_content, 'perpetual_data')) {
            return;
        }
        
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


    public function admin_page()
    { ?>
        <div class="ppr-wrapper">
            <h1><?php _e('CSV Data Manager', 'perpetual-register'); ?></h1>

            <div id="ppr-upload-section">

                <div id="ppr-messages" class="ppr-messages"></div>

                <div id="ppr-drop-zone" class="ppr-drop-zone">
                    <div class="ppr-drop-content">
                        <span class="dashicons dashicons-cloud-upload"></span>
                        <p><?php _e('Drag and drop your CSV file here, or click to select', 'perpetual-register'); ?></p>
                        <input type="file" id="ppr-file-input" accept=".csv" style="display: none;">
                        <div id="ppr-file-info" class="ppr-file-info" style="display: none;"></div>
                    </div>
                    <div id="ppr-loading" class="ppr-loading" style="display: none;">
                        <span class="spinner is-active"></span>
                        <p><?php _e('Processing file...', 'perpetual-register'); ?></p>
                    </div>
                </div>

                <div id="ppr-options-wrapper" style="display: none;">
                    <h3><?php _e('Upload Options', 'perpetual-register'); ?></h3>

                    <div class="ppr-options">
                        <label>
                            <input type="radio" name="ppr_upload_mode" value="replace" class="radio-input">
                            <span class="radio-tile">
                                <?php _e('Replace existing data', 'perpetual-register'); ?>
                            </span>
                        </label>
                        <label>
                            <input type="radio" name="ppr_upload_mode" value="append" class="radio-input">
                            <span class="radio-tile">
                                <?php _e('Append to existing data', 'perpetual-register'); ?>
                            </span>
                        </label>
                    </div>
                </div>

                <div id="ppr-preview-section" style="display: none;">
                    <button type="button" id="ppr-preview-btn" class="button"><?php _e('Preview Data', 'perpetual-register'); ?></button>
                    <button type="button" id="ppr-upload-btn" class="button button-primary" disabled><?php _e('Upload Data', 'perpetual-register'); ?></button>
                </div>


            </div>

            <div id="ppr-data-section">
                <h2><?php _e('Existing Data', 'perpetual-register'); ?></h2>
                <div id="ppr-data-loading" class="ppr-loading" style="display: none;">
                    <span class="spinner is-active"></span>
                    <p><?php _e('Loading data...', 'perpetual-register'); ?></p>
                </div>
                <div id="ppr-data-list" class="ppr-data-list"></div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div id="ppr-preview-modal" class="ppr-modal" style="display: none;">
            <div class="ppr-modal-content">
                <div class="ppr-modal-header">
                    <h3><?php _e('CSV Preview', 'perpetual-register'); ?></h3>
                    <span class="ppr-modal-close">&times;</span>
                </div>
                <div class="ppr-modal-body">
                    <div id="ppr-preview-table"></div>
                </div>
            </div>
        </div>

<?php
    }

    public function handle_csv_upload()
    {
        check_ajax_referer('ppr_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'perpetual-register'));
        }

        if (!isset($_FILES['csv_file'])) {
            wp_send_json_error(__('No file uploaded', 'perpetual-register'));
        }

        $mode = sanitize_text_field($_POST['mode']);
        $file = $_FILES['csv_file'];

        $result = $this->process_csv_file($file, $mode);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    private function process_csv_file($file, $mode = 'append')
    {
        // validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => __('File upload error', 'perpetual-register'));
        }

        if (!$this->is_csv_file($file)) {
            return array('success' => false, 'message' => __('Invalid file type. Please upload a CSV file.', 'perpetual-register'));
        }

        // read CSV
        $csv_data = $this->read_csv_file($file['tmp_name']);

        if (!$csv_data['success']) {
            return $csv_data;
        }

        $data = $csv_data['data'];
        $headers = $csv_data['headers'];

        // validate headers
        $required_headers = array('id', 'entry', 'lifestats');
        $missing_headers = array_diff($required_headers, $headers);

        if (!empty($missing_headers)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Missing required columns: %s', 'perpetual-register'), implode(', ', $missing_headers))
            );
        }

        // Validate data
        $validation_result = $this->validate_csv_data($data);
        if (!$validation_result['success']) {
            return $validation_result;
        }


        // Save to database
        $result = $this->save_csv_data($data, $mode);

        return $result;
    }

    private function is_csv_file($file)
    {
        $allowed_types = array('text/csv');
        $file_type = $file['type'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        return in_array($file_type, $allowed_types) || $file_extension === 'csv';
    }

    private function read_csv_file($file_path)
    {
        if (!file_exists($file_path)) {
            return array('success' => false, 'message' => __('File not found', 'perpetual-register'));
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return array('success' => false, 'message' => __('Cannot read file', 'perpetual-register'));
        }

        $data = array();
        $headers = array();
        $row_count = 0;

        // will continue running till end of file
        while (($row = fgetcsv($handle)) !== false) {
            if ($row_count === 0) {
                $headers = array_map('strtolower', array_map('trim', $row));
            } else {
                if (count($row) === count($headers)) {
                    // creates an associative array, where keys are headers and values are row data
                    $data[] = array_combine($headers, array_map('trim', $row));
                }
            }
            $row_count++;
        }

        fclose($handle);

        return array('success' => true, 'data' => $data, 'headers' => $headers);
    }

    private function validate_csv_data($data)
    {
        $errors = array();

        foreach ($data as $index => $row) {
            $row_num = $index + 2; // +2 to skip header

            // Check required fields
            if (empty($row['id'])) {
                $errors[] = sprintf(__('Row %d: ID cannot be empty', 'perpetual-register'), $row_num);
            }

            if (empty($row['entry'])) {
                $errors[] = sprintf(__('Row %d: Entry cannot be empty', 'perpetual-register'), $row_num);
            }

            // Validate ID is numeric
            if (!empty($row['id']) && !is_numeric($row['id'])) {
                $errors[] = sprintf(__('Row %d: ID must be numeric', 'perpetual-register'), $row_num);
            }
        }

        if (!empty($errors)) {
            return array('success' => false, 'message' => implode('<br>', $errors));
        }

        return array('success' => true);
    }


    private function save_csv_data($data, $mode = 'append')
    {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');
            
            if ($mode === 'replace') {
                // Clear existing data in replace mode
                $wpdb->query("TRUNCATE TABLE {$this->table_name}");
            }

            $inserted = 0;
            $skipped = 0;

            foreach ($data as $row) {
                $org_id = intval($row['id']); // CSV's id maps to org_id
                $entry = sanitize_text_field($row['entry']);
                $lifestats = sanitize_textarea_field($row['lifestats']);

                // In append mode, skip if org_id already exists
                if ($mode === 'append') {
                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$this->table_name} WHERE org_id = %d",
                        $org_id
                    ));

                    if ($existing) {
                        $skipped++;
                        continue;
                    }
                }

                // Insert or replace, letting id auto-increment
                $result = $wpdb->replace($this->table_name, array(
                    'org_id' => $org_id,
                    'entry' => $entry,
                    'lifestats' => $lifestats
                ), array('%d', '%s', '%s'));

                if ($result !== false) {
                    $inserted++;
                }
            }

            $wpdb->query('COMMIT');

            return array(
                'success' => true,
                'message' => sprintf(
                    __('Successfully processed %d records. %d inserted/updated, %d skipped.', 'perpetual-register'),
                    count($data),
                    $inserted,
                    $skipped
                )
            );
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'message' => __('Database error occurred: %s', 'perpetual-register'), $e->getMessage());
        }
    }


    public function get_existing_data()
    {
        check_ajax_referer('ppr_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'perpetual-register'));
        }

        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY entry ASC",
            ARRAY_A
        );

        wp_send_json_success($results);
    }


    public function display_perpetual_data($atts) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT entry, lifestats FROM {$this->table_name} ORDER BY entry ASC",
            ARRAY_A
        );
        
        if (empty($results)) {
            return '<p>' . __('No data available.', 'perpetual-register') . '</p>';
        }
        
        $output = '<div class="perpetual-data-display">';
        
        foreach ($results as $row) {
            $output .= '<div class="perpetual-data-item">';
            $output .= '<div class="perpetual-entry">' . esc_html($row['entry']) . '</div>';
            if (!empty($row['lifestats'])) {
                $output .= '<div class="perpetual-lifestats">' . esc_html($row['lifestats']) . '</div>';
            }
            $output .= '</div>';
        }

        $output .= '<p class="no-items" style="display: none;">No entries found.</p>';
        
        $output .= '</div>';
        
        return $output;
    }
}

new PerpetualRegister();

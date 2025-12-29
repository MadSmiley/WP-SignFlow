<?php
/**
 * Admin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_signflow_save_template', array($this, 'save_template'));
        add_action('admin_post_signflow_delete_template', array($this, 'delete_template'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'WP SignFlow',
            'SignFlow',
            'manage_options',
            'wp-signflow',
            array($this, 'render_templates_page'),
            'dashicons-edit-page',
            30
        );

        add_submenu_page(
            'wp-signflow',
            'Templates',
            'Templates',
            'manage_options',
            'wp-signflow',
            array($this, 'render_templates_page')
        );

        add_submenu_page(
            'wp-signflow',
            'Contracts',
            'Contracts',
            'manage_options',
            'wp-signflow-contracts',
            array($this, 'render_contracts_page')
        );

        add_submenu_page(
            'wp-signflow',
            'Settings',
            'Settings',
            'manage_options',
            'wp-signflow-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            null, // Hidden from menu
            'Edit Template',
            'Edit Template',
            'manage_options',
            'wp-signflow-edit-template',
            array($this, 'render_edit_template_page')
        );

        add_submenu_page(
            null, // Hidden from menu
            'View Contract',
            'View Contract',
            'manage_options',
            'wp-signflow-view-contract',
            array($this, 'render_view_contract_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wp-signflow') === false) {
            return;
        }

        wp_enqueue_style(
            'wp-signflow-admin',
            WP_SIGNFLOW_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_SIGNFLOW_VERSION
        );

        wp_enqueue_script(
            'wp-signflow-admin',
            WP_SIGNFLOW_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WP_SIGNFLOW_VERSION,
            true
        );

        wp_enqueue_editor();
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('signflow_settings', 'signflow_storage_type');
        register_setting('signflow_settings', 'signflow_gcs_bucket');
        register_setting('signflow_settings', 'signflow_gcs_credentials');
        register_setting('signflow_settings', 'signflow_api_key');
    }

    /**
     * Render templates page
     */
    public function render_templates_page() {
        $templates = WP_SignFlow_Template_Manager::get_templates();
        include WP_SIGNFLOW_PLUGIN_DIR . 'admin/views/templates.php';
    }

    /**
     * Render edit template page
     */
    public function render_edit_template_page() {
        $template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $template = null;

        if ($template_id) {
            $template = WP_SignFlow_Template_Manager::get_template($template_id);
        }

        include WP_SIGNFLOW_PLUGIN_DIR . 'admin/views/edit-template.php';
    }

    /**
     * Render contracts page
     */
    public function render_contracts_page() {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('contracts');

        $contracts = $wpdb->get_results(
            "SELECT c.*, t.name as template_name
            FROM $table c
            LEFT JOIN " . WP_SignFlow_Database::get_table('templates') . " t ON c.template_id = t.id
            ORDER BY c.created_at DESC
            LIMIT 100"
        );

        include WP_SIGNFLOW_PLUGIN_DIR . 'admin/views/contracts.php';
    }

    /**
     * Render view contract page
     */
    public function render_view_contract_page() {
        $contract_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);
        $signature = WP_SignFlow_Signature_Handler::get_signature($contract_id);
        $audit_trail = WP_SignFlow_Audit_Trail::get_contract_audit($contract_id);

        include WP_SIGNFLOW_PLUGIN_DIR . 'admin/views/view-contract.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include WP_SIGNFLOW_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Save template
     */
    public function save_template() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('signflow_save_template');

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $name = sanitize_text_field($_POST['template_name']);
        $content = wp_kses_post($_POST['template_content']);

        // Extract variables from content
        $variables = WP_SignFlow_Template_Manager::extract_variables($content);

        if ($template_id) {
            // Update existing template
            WP_SignFlow_Template_Manager::update_template($template_id, array(
                'name' => $name,
                'content' => $content,
                'variables' => $variables
            ));
        } else {
            // Create new template
            WP_SignFlow_Template_Manager::create_template($name, $content, $variables);
        }

        wp_redirect(admin_url('admin.php?page=wp-signflow&message=saved'));
        exit;
    }

    /**
     * Delete template
     */
    public function delete_template() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('signflow_delete_template_' . $_GET['id']);

        $template_id = intval($_GET['id']);
        WP_SignFlow_Template_Manager::delete_template($template_id);

        wp_redirect(admin_url('admin.php?page=wp-signflow&message=deleted'));
        exit;
    }
}

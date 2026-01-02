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
        add_action('admin_post_signflow_create_contract', array($this, 'create_contract'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('WP SignFlow', 'wp-signflow'),
            __('SignFlow', 'wp-signflow'),
            'manage_options',
            'wp-signflow',
            array($this, 'render_templates_page'),
            'dashicons-edit-page',
            30
        );

        add_submenu_page(
            'wp-signflow',
            __('Templates', 'wp-signflow'),
            __('Templates', 'wp-signflow'),
            'manage_options',
            'wp-signflow',
            array($this, 'render_templates_page')
        );

        add_submenu_page(
            'wp-signflow',
            __('Contracts', 'wp-signflow'),
            __('Contracts', 'wp-signflow'),
            'manage_options',
            'wp-signflow-contracts',
            array($this, 'render_contracts_page')
        );

        add_submenu_page(
            'wp-signflow-contracts', // Hidden from menu
            __('Create Contract', 'wp-signflow'),
            __('Create Contract', 'wp-signflow'),
            'manage_options',
            'wp-signflow-create-contract',
            array($this, 'render_create_contract_page')
        );

        add_submenu_page(
            'wp-signflow',
            __('Settings', 'wp-signflow'),
            __('Settings', 'wp-signflow'),
            'manage_options',
            'wp-signflow-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'wp-signflow-settings', // Hidden from menu
            __('Edit Template', 'wp-signflow'),
            __('Edit Template', 'wp-signflow'),
            'manage_options',
            'wp-signflow-edit-template',
            array($this, 'render_edit_template_page')
        );

        add_submenu_page(
            'wp-signflow-contracts', // Hidden from menu
            __('View Contract', 'wp-signflow'),
            __('View Contract', 'wp-signflow'),
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
        register_setting('signflow_settings', 'signflow_certificate_language');
        register_setting('signflow_settings', 'signflow_storage_type');
        register_setting('signflow_settings', 'signflow_storage_path');
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
        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $contract_id = intval($_GET['id']);

            // Verify nonce
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_contract_' . $contract_id)) {
                wp_die('Security check failed');
            }

            // Delete contract
            $result = WP_SignFlow_Contract_Generator::delete_contract($contract_id);

            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Contract deleted successfully.</p></div>';
            }
        }

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
        $language = isset($_POST['template_language']) ? sanitize_text_field($_POST['template_language']) : 'en';

        // Extract variables from content
        $variables = WP_SignFlow_Template_Manager::extract_variables($content);

        if ($template_id) {
            // Update existing template
            WP_SignFlow_Template_Manager::update_template($template_id, array(
                'name' => $name,
                'content' => $content,
                'variables' => $variables,
                'language' => $language
            ));
        } else {
            // Create new template
            WP_SignFlow_Template_Manager::create_template($name, $content, $variables, $language);
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

    /**
     * Render create contract page
     */
    public function render_create_contract_page() {
        include WP_SIGNFLOW_PLUGIN_DIR . 'admin/views/create-contract.php';
    }

    /**
     * Create contract
     */
    public function create_contract() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'wp-signflow'));
        }

        check_admin_referer('signflow_create_contract', 'signflow_nonce');

        // Get form data
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $variables = isset($_POST['variables']) ? $_POST['variables'] : array();
        $signer_email = isset($_POST['signer_email']) ? sanitize_email($_POST['signer_email']) : '';
        $signer_name = isset($_POST['signer_name']) ? sanitize_text_field($_POST['signer_name']) : '';
        $metadata_json = isset($_POST['metadata']) ? trim($_POST['metadata']) : '';

        // Validate
        if (empty($template_id)) {
            wp_redirect(admin_url('admin.php?page=wp-signflow-create-contract&error=' . urlencode(__('Please select a template.', 'wp-signflow'))));
            exit;
        }

        // Sanitize variables
        $sanitized_variables = array();
        foreach ($variables as $key => $value) {
            $sanitized_variables[sanitize_text_field($key)] = sanitize_text_field($value);
        }

        // Prepare metadata
        $metadata = array();
        if (!empty($signer_email)) {
            $metadata['signer_email'] = $signer_email;
        }
        if (!empty($signer_name)) {
            $metadata['signer_name'] = $signer_name;
        }

        // Parse custom metadata JSON
        if (!empty($metadata_json)) {
            $custom_metadata = json_decode($metadata_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($custom_metadata)) {
                $metadata['custom'] = $custom_metadata;
            } else {
                wp_redirect(admin_url('admin.php?page=wp-signflow-create-contract&error=' . urlencode(__('Invalid JSON format in metadata field.', 'wp-signflow'))));
                exit;
            }
        }

        // Generate contract
        $result = WP_SignFlow_Contract_Generator::generate_contract($template_id, $sanitized_variables, $metadata);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=wp-signflow-create-contract&error=' . urlencode($result->get_error_message())));
            exit;
        }

        // Redirect to contracts page with success message
        wp_redirect(admin_url('admin.php?page=wp-signflow-contracts&success=1'));
        exit;
    }
}

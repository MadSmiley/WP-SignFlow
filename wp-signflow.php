<?php
/**
 * Plugin Name: WP SignFlow
 * Plugin URI: https://github.com/MadSmiley/WP-SignFlow
 * Description: Complete contract management system with templates, dynamic generation, tablet signature, and secure storage
 * Version: 1.0.0
 * Author: Germain Belacel
 * Author URI: https://www.linkedin.com/in/germain-belacel/
 * License: GPL v2 or later
 * Text Domain: wp-signflow
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_SIGNFLOW_VERSION', '1.0.0');
define('WP_SIGNFLOW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_SIGNFLOW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_SIGNFLOW_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main WP_SignFlow class
 */
class WP_SignFlow {

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'includes/class-database.php';
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'includes/class-template-manager.php';
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'includes/class-contract-generator.php';
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'includes/class-pdf-generator.php';
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'includes/class-signature-handler.php';
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'includes/class-storage-manager.php';
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'includes/class-audit-trail.php';
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'includes/class-translations.php';
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'includes/class-public-api.php';
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'admin/class-admin.php';
        require_once WP_SIGNFLOW_PLUGIN_DIR . 'public/class-public-signature.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'init'));

        // Cleanup cron
        add_action('signflow_cleanup_expired_contracts', array('WP_SignFlow_Contract_Generator', 'delete_expired_contracts'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('wp-signflow', false, dirname(WP_SIGNFLOW_PLUGIN_BASENAME) . '/languages');

        // Initialize components
        WP_SignFlow_Database::get_instance();
        WP_SignFlow_Admin::get_instance();
        WP_SignFlow_Public_Signature::get_instance();
        WP_SignFlow_Public_API::get_instance();

        // Allow other plugins to register their templates
        do_action('signflow_register_templates');
    }

    /**
     * Plugin activation
     */
    public function activate() {
        WP_SignFlow_Database::create_tables();

        // Create storage directory
        $signflow_dir = WP_SignFlow_Storage_Manager::get_storage_dir();
        if (!file_exists($signflow_dir)) {
            wp_mkdir_p($signflow_dir);
            // Add .htaccess for security (block directory listing, allow PDF access)
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.pdf>\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</Files>\n";
            file_put_contents($signflow_dir . '/.htaccess', $htaccess_content);
        }

        // Schedule cleanup cron (daily)
        if (!wp_next_scheduled('signflow_cleanup_expired_contracts')) {
            wp_schedule_event(time(), 'daily', 'signflow_cleanup_expired_contracts');
        }

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove scheduled cron
        $timestamp = wp_next_scheduled('signflow_cleanup_expired_contracts');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'signflow_cleanup_expired_contracts');
        }

        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function wp_signflow() {
    return WP_SignFlow::get_instance();
}

// Start the plugin
wp_signflow();

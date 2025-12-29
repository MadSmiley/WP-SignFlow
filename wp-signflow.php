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
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize components
        WP_SignFlow_Database::get_instance();
        WP_SignFlow_Admin::get_instance();
        WP_SignFlow_Public_Signature::get_instance();
        WP_SignFlow_Public_API::get_instance();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        WP_SignFlow_Database::create_tables();

        // Create upload directory
        $upload_dir = wp_upload_dir();
        $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
        if (!file_exists($signflow_dir)) {
            wp_mkdir_p($signflow_dir);
            // Add .htaccess for security
            file_put_contents($signflow_dir . '/.htaccess', 'deny from all');
        }

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
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

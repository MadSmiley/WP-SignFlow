<?php
/**
 * Database management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Database {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Templates table
        $table_templates = $wpdb->prefix . 'signflow_templates';
        $sql_templates = "CREATE TABLE $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            content longtext NOT NULL,
            variables text,
            language varchar(10) DEFAULT 'en',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20),
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        // Contracts table
        $table_contracts = $wpdb->prefix . 'signflow_contracts';
        $sql_contracts = "CREATE TABLE $table_contracts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            template_id bigint(20),
            contract_token varchar(64) NOT NULL,
            contract_data longtext,
            original_pdf_path varchar(500),
            original_hash varchar(64),
            signed_pdf_path varchar(500),
            signed_pdf_hash varchar(64),
            certificate_path varchar(500),
            status varchar(20) DEFAULT 'pending',
            signed_at datetime,
            expires_at datetime,
            ip_address varchar(45),
            user_agent text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_token (contract_token),
            KEY template_id (template_id),
            KEY status (status),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Signatures table - only metadata, no signature data stored
        $table_signatures = $wpdb->prefix . 'signflow_signatures';
        $sql_signatures = "CREATE TABLE $table_signatures (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) NOT NULL,
            signer_name varchar(255),
            signer_email varchar(255),
            consent_given tinyint(1) DEFAULT 0,
            signed_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY  (id),
            KEY contract_id (contract_id)
        ) $charset_collate;";

        // Audit trail table
        $table_audit = $wpdb->prefix . 'signflow_audit';
        $sql_audit = "CREATE TABLE $table_audit (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contract_id bigint(20),
            event_type varchar(50) NOT NULL,
            event_data longtext,
            previous_hash varchar(64),
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contract_id (contract_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_templates);
        dbDelta($sql_contracts);
        dbDelta($sql_signatures);
        dbDelta($sql_audit);
    }

    /**
     * Get table name
     */
    public static function get_table($table) {
        global $wpdb;
        return $wpdb->prefix . 'signflow_' . $table;
    }
}

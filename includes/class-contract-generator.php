<?php
/**
 * Contract Generator class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Contract_Generator {

    /**
     * Generate contract from template
     */
    public static function generate_contract($template_id, $variables, $metadata = array()) {
        // Get template
        $template = WP_SignFlow_Template_Manager::get_template($template_id);
        if (!$template) {
            return new WP_Error('invalid_template', 'Template not found');
        }

        // Replace variables in content
        $content = self::replace_variables($template->content, $variables);

        // Generate unique token
        $token = self::generate_token();

        // Save contract to database
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('contracts');

        $result = $wpdb->insert(
            $table,
            array(
                'template_id' => $template_id,
                'contract_token' => $token,
                'contract_data' => maybe_serialize(array(
                    'variables' => $variables,
                    'content' => $content
                )),
                'status' => 'pending',
                'metadata' => maybe_serialize($metadata)
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );

        if (!$result) {
            return new WP_Error('save_failed', 'Failed to save contract');
        }

        $contract_id = $wpdb->insert_id;

        // Log audit event
        WP_SignFlow_Audit_Trail::log_event($contract_id, 'contract_generated', array(
            'template_id' => $template_id,
            'variables' => array_keys($variables)
        ));

        // Generate PDF
        $pdf_result = WP_SignFlow_PDF_Generator::generate_pdf($contract_id, $content);
        if (is_wp_error($pdf_result)) {
            return $pdf_result;
        }

        return array(
            'contract_id' => $contract_id,
            'token' => $token,
            'signature_url' => self::get_signature_url($token)
        );
    }

    /**
     * Replace variables in content
     */
    public static function replace_variables($content, $variables) {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', esc_html($value), $content);
        }
        return $content;
    }

    /**
     * Generate unique token
     */
    private static function generate_token() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get signature URL for contract
     */
    public static function get_signature_url($token) {
        return add_query_arg(array(
            'signflow_action' => 'sign',
            'token' => $token
        ), home_url('/'));
    }

    /**
     * Get contract by token
     */
    public static function get_contract_by_token($token) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('contracts');

        $contract = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE contract_token = %s",
            $token
        ));

        if ($contract) {
            $contract->contract_data = maybe_unserialize($contract->contract_data);
            $contract->metadata = maybe_unserialize($contract->metadata);
        }

        return $contract;
    }

    /**
     * Get contract by ID
     */
    public static function get_contract($id) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('contracts');

        $contract = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($contract) {
            $contract->contract_data = maybe_unserialize($contract->contract_data);
            $contract->metadata = maybe_unserialize($contract->metadata);
        }

        return $contract;
    }

    /**
     * Update contract status
     */
    public static function update_contract_status($contract_id, $status) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('contracts');

        return $wpdb->update(
            $table,
            array('status' => $status),
            array('id' => $contract_id),
            array('%s'),
            array('%d')
        );
    }
}

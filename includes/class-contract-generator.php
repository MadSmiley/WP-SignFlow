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

        // Calculate expiration date (30 days from now by default)
        $expiration_days = isset($metadata['expiration_days']) ? (int)$metadata['expiration_days'] : 30;
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $expiration_days . ' days'));

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
                'expires_at' => $expires_at,
                'metadata' => maybe_serialize($metadata)
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$result) {
            return new WP_Error('save_failed', 'Failed to save contract');
        }

        $contract_id = $wpdb->insert_id;

        // Generate PDF
        $pdf_result = WP_SignFlow_PDF_Generator::generate_pdf($contract_id, $content);
        if (is_wp_error($pdf_result)) {
            return $pdf_result;
        }

        // Get contract with hash to log in audit trail
        $contract = self::get_contract($contract_id);

        // Log audit event with original hash
        WP_SignFlow_Audit_Trail::log_event($contract_id, 'contract_generated', array(
            'template_id' => $template_id,
            'variables' => array_keys($variables),
            'pdf_file' => $pdf_result,
            'original_hash' => $contract->original_hash
        ));

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
     * Delete expired unsigned contracts
     */
    public static function delete_expired_contracts() {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('contracts');

        // Find expired unsigned contracts
        $expired_contracts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, pdf_path FROM $table
            WHERE status = 'pending'
            AND expires_at < %s",
            current_time('mysql')
        ));

        if (!$expired_contracts) {
            return 0;
        }

        $upload_dir = wp_upload_dir();
        $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
        $deleted_count = 0;

        foreach ($expired_contracts as $contract) {
            // Delete PDF file
            if ($contract->pdf_path) {
                $filepath = $signflow_dir . '/' . $contract->pdf_path;
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }
            }

            // Delete from database
            $wpdb->delete(
                $table,
                array('id' => $contract->id),
                array('%d')
            );

            // Log deletion
            WP_SignFlow_Audit_Trail::log_event($contract->id, 'contract_expired_deleted', array(
                'reason' => 'automatic_cleanup'
            ));

            $deleted_count++;
        }

        return $deleted_count;
    }

    /**
     * Delete a specific contract (manual deletion)
     */
    public static function delete_contract($contract_id) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('contracts');

        // Get contract
        $contract = self::get_contract($contract_id);
        if (!$contract) {
            return new WP_Error('invalid_contract', 'Contract not found');
        }

        // Only allow deletion of unsigned contracts
        if ($contract->status === 'signed') {
            return new WP_Error('contract_signed', 'Cannot delete signed contracts');
        }

        // Delete PDF file
        if ($contract->pdf_path) {
            $upload_dir = wp_upload_dir();
            $filepath = $upload_dir['basedir'] . '/wp-signflow/' . $contract->pdf_path;
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }

        // Delete from database
        $result = $wpdb->delete(
            $table,
            array('id' => $contract_id),
            array('%d')
        );

        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete contract');
        }

        // Log deletion
        WP_SignFlow_Audit_Trail::log_event($contract_id, 'contract_deleted', array(
            'reason' => 'manual_deletion'
        ));

        return true;
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

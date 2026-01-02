<?php
/**
 * Signature Handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Signature_Handler {

    /**
     * Process signature submission
     */
    public static function process_signature($contract_id, $signature_data, $signer_info) {
        // Validate contract
        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);
        if (!$contract) {
            return new WP_Error('invalid_contract', 'Contract not found');
        }

        if ($contract->status === 'signed') {
            return new WP_Error('already_signed', 'Contract already signed');
        }

        // Validate signature data
        if (empty($signature_data)) {
            return new WP_Error('invalid_signature', 'Signature data is required');
        }

        // Validate consent
        if (empty($signer_info['consent']) || $signer_info['consent'] !== 'yes') {
            return new WP_Error('consent_required', 'Consent is required');
        }

        // Save signature image
        $signature_image = self::save_signature_image($contract_id, $signature_data);
        if (is_wp_error($signature_image)) {
            return $signature_image;
        }

        // Get IP and User Agent
        $ip_address = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        // Save signature to database
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('signatures');

        $result = $wpdb->insert(
            $table,
            array(
                'contract_id' => $contract_id,
                'signature_data' => $signature_data,
                'signature_image' => basename($signature_image),
                'signer_name' => sanitize_text_field($signer_info['name'] ?? ''),
                'signer_email' => sanitize_email($signer_info['email'] ?? ''),
                'consent_given' => 1,
                'ip_address' => $ip_address,
                'user_agent' => substr($user_agent, 0, 500)
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        if (!$result) {
            return new WP_Error('signature_save_failed', 'Failed to save signature');
        }

        // Log audit event
        $audit_data = array(
            'signer_name' => $signer_info['name'] ?? '',
            'signer_email' => $signer_info['email'] ?? ''
        );

        // Add consent timestamp if available
        if (!empty($signer_info['consent_timestamp'])) {
            $audit_data['consent_timestamp'] = $signer_info['consent_timestamp'];
        }

        WP_SignFlow_Audit_Trail::log_event($contract_id, 'signature_captured', $audit_data);

        // Get original hash from database (calculated at contract generation)
        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);
        $original_hash = $contract->original_hash;

        // Add signature to PDF
        $pdf_result = WP_SignFlow_PDF_Generator::add_signature_to_pdf($contract_id, $signature_image);
        if (is_wp_error($pdf_result)) {
            return $pdf_result;
        }

        // Calculate hash of signed PDF
        $pdf_path = WP_SignFlow_PDF_Generator::get_pdf_path($pdf_result);
        $signed_hash = WP_SignFlow_PDF_Generator::calculate_hash($pdf_path);

        // Generate certificate with both hashes
        $signed_date = current_time('mysql');
        $certificate_result = WP_SignFlow_PDF_Generator::generate_certificate(
            $contract_id,
            $original_hash,
            $signed_hash,
            $signer_info['name'] ?? '',
            $signer_info['email'] ?? '',
            $signed_date
        );

        $certificate_path = is_wp_error($certificate_result) ? null : $certificate_result;

        // Update contract
        $wpdb->update(
            WP_SignFlow_Database::get_table('contracts'),
            array(
                'signed_pdf_hash' => $signed_hash,
                'certificate_path' => $certificate_path,
                'status' => 'signed',
                'signed_at' => $signed_date,
                'ip_address' => $ip_address,
                'user_agent' => substr($user_agent, 0, 500)
            ),
            array('id' => $contract_id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        // Delete signature image (no longer needed)
        if (file_exists($signature_image)) {
            @unlink($signature_image);
        }

        // Log completion
        WP_SignFlow_Audit_Trail::log_event($contract_id, 'contract_signed', array(
            'original_hash' => $original_hash,
            'signed_hash' => $signed_hash,
            'pdf_file' => $pdf_result,
            'certificate_file' => $certificate_path
        ));

        // Store in configured storage (Google Cloud or local)
        $storage_result = WP_SignFlow_Storage_Manager::store_signed_contract($contract_id, $pdf_path);

        return array(
            'success' => true,
            'contract_id' => $contract_id,
            'original_hash' => $original_hash,
            'signed_hash' => $signed_hash,
            'signed_at' => current_time('mysql')
        );
    }

    /**
     * Save signature image from base64 data
     */
    private static function save_signature_image($contract_id, $signature_data) {
        // Validate base64 image data
        if (!preg_match('/^data:image\/(png|jpg|jpeg);base64,/', $signature_data, $matches)) {
            return new WP_Error('invalid_image', 'Invalid signature image format');
        }

        $image_type = $matches[1];
        $image_data = preg_replace('/^data:image\/(png|jpg|jpeg);base64,/', '', $signature_data);
        $image_data = base64_decode($image_data);

        if ($image_data === false) {
            return new WP_Error('invalid_image', 'Failed to decode signature image');
        }

        // Generate filename
        $filename = 'signature_' . $contract_id . '_' . time() . '.' . $image_type;
        $upload_dir = wp_upload_dir();
        $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
        $filepath = $signflow_dir . '/' . $filename;

        // Save file
        $result = file_put_contents($filepath, $image_data);
        if ($result === false) {
            return new WP_Error('save_failed', 'Failed to save signature image');
        }

        return $filepath;
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /**
     * Verify signature authenticity
     */
    public static function verify_signature($contract_id) {
        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);
        if (!$contract || !$contract->signed_pdf_path || !$contract->signed_pdf_hash) {
            return false;
        }

        $pdf_path = WP_SignFlow_PDF_Generator::get_pdf_path($contract->signed_pdf_path);
        if (!file_exists($pdf_path)) {
            return false;
        }

        $current_hash = WP_SignFlow_PDF_Generator::calculate_hash($pdf_path);
        return $current_hash === $contract->signed_pdf_hash;
    }

    /**
     * Get signature details
     */
    public static function get_signature($contract_id) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('signatures');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE contract_id = %d ORDER BY signed_at DESC LIMIT 1",
            $contract_id
        ));
    }
}

<?php
/**
 * Storage Manager class
 * Handles local and Google Cloud Storage
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Storage_Manager {

    /**
     * Store signed contract based on configuration
     */
    public static function store_signed_contract($contract_id, $pdf_path) {
        $storage_type = get_option('signflow_storage_type', 'local');

        if ($storage_type === 'google_cloud') {
            return self::upload_to_google_cloud($contract_id, $pdf_path);
        }

        // Default: local storage (already saved)
        return array(
            'success' => true,
            'storage' => 'local',
            'path' => $pdf_path
        );
    }

    /**
     * Upload to Google Cloud Storage
     */
    private static function upload_to_google_cloud($contract_id, $pdf_path) {
        // Check if Google Cloud credentials are configured
        $bucket_name = get_option('signflow_gcs_bucket');
        $credentials_json = get_option('signflow_gcs_credentials');

        if (empty($bucket_name) || empty($credentials_json)) {
            return new WP_Error('gcs_not_configured', 'Google Cloud Storage is not configured');
        }

        // Check if Google Cloud Storage library is available
        if (!class_exists('Google\Cloud\Storage\StorageClient')) {
            return new WP_Error('gcs_library_missing', 'Google Cloud Storage library is not installed. Run: composer require google/cloud-storage');
        }

        try {
            // Decode credentials
            $credentials = json_decode($credentials_json, true);
            if (!$credentials) {
                return new WP_Error('invalid_credentials', 'Invalid Google Cloud credentials');
            }

            // Create storage client
            $storage = new \Google\Cloud\Storage\StorageClient([
                'keyFile' => $credentials
            ]);

            $bucket = $storage->bucket($bucket_name);

            // Generate object name
            $object_name = 'contracts/' . basename($pdf_path);

            // Upload file
            $file = fopen($pdf_path, 'r');
            $object = $bucket->upload($file, [
                'name' => $object_name,
                'metadata' => [
                    'contract_id' => $contract_id,
                    'uploaded_at' => current_time('mysql')
                ]
            ]);

            // Log audit event
            WP_SignFlow_Audit_Trail::log_event($contract_id, 'uploaded_to_gcs', array(
                'bucket' => $bucket_name,
                'object' => $object_name
            ));

            return array(
                'success' => true,
                'storage' => 'google_cloud',
                'bucket' => $bucket_name,
                'object' => $object_name,
                'url' => $object->signedUrl(new \DateTime('+1 year'))
            );

        } catch (Exception $e) {
            return new WP_Error('gcs_upload_failed', $e->getMessage());
        }
    }

    /**
     * Download from Google Cloud Storage
     */
    public static function download_from_google_cloud($object_name, $destination_path) {
        $bucket_name = get_option('signflow_gcs_bucket');
        $credentials_json = get_option('signflow_gcs_credentials');

        if (empty($bucket_name) || empty($credentials_json)) {
            return new WP_Error('gcs_not_configured', 'Google Cloud Storage is not configured');
        }

        try {
            $credentials = json_decode($credentials_json, true);
            $storage = new \Google\Cloud\Storage\StorageClient([
                'keyFile' => $credentials
            ]);

            $bucket = $storage->bucket($bucket_name);
            $object = $bucket->object($object_name);

            $object->downloadToFile($destination_path);

            return $destination_path;

        } catch (Exception $e) {
            return new WP_Error('gcs_download_failed', $e->getMessage());
        }
    }

    /**
     * Get signed URL for contract (for Google Cloud Storage)
     */
    public static function get_signed_url($contract_id, $expiration = '+1 hour') {
        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);
        if (!$contract) {
            return false;
        }

        $storage_type = get_option('signflow_storage_type', 'local');

        if ($storage_type === 'google_cloud') {
            $bucket_name = get_option('signflow_gcs_bucket');
            $credentials_json = get_option('signflow_gcs_credentials');

            if (empty($bucket_name) || empty($credentials_json)) {
                return false;
            }

            try {
                $credentials = json_decode($credentials_json, true);
                $storage = new \Google\Cloud\Storage\StorageClient([
                    'keyFile' => $credentials
                ]);

                $bucket = $storage->bucket($bucket_name);
                $object_name = 'contracts/' . $contract->pdf_path;
                $object = $bucket->object($object_name);

                return $object->signedUrl(new \DateTime($expiration));

            } catch (Exception $e) {
                return false;
            }
        }

        // Local storage: return direct URL (protected by .htaccess)
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/wp-signflow/' . $contract->pdf_path;
    }

    /**
     * Delete contract file
     */
    public static function delete_contract($contract_id) {
        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);
        if (!$contract) {
            return false;
        }

        $storage_type = get_option('signflow_storage_type', 'local');

        if ($storage_type === 'google_cloud') {
            return self::delete_from_google_cloud('contracts/' . $contract->pdf_path);
        }

        // Delete from local storage
        $pdf_path = WP_SignFlow_PDF_Generator::get_pdf_path($contract->pdf_path);
        if (file_exists($pdf_path)) {
            return unlink($pdf_path);
        }

        return false;
    }

    /**
     * Delete from Google Cloud Storage
     */
    private static function delete_from_google_cloud($object_name) {
        $bucket_name = get_option('signflow_gcs_bucket');
        $credentials_json = get_option('signflow_gcs_credentials');

        if (empty($bucket_name) || empty($credentials_json)) {
            return false;
        }

        try {
            $credentials = json_decode($credentials_json, true);
            $storage = new \Google\Cloud\Storage\StorageClient([
                'keyFile' => $credentials
            ]);

            $bucket = $storage->bucket($bucket_name);
            $object = $bucket->object($object_name);
            $object->delete();

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Test Google Cloud Storage connection
     */
    public static function test_gcs_connection() {
        $bucket_name = get_option('signflow_gcs_bucket');
        $credentials_json = get_option('signflow_gcs_credentials');

        if (empty($bucket_name) || empty($credentials_json)) {
            return array(
                'success' => false,
                'message' => 'Credentials not configured'
            );
        }

        try {
            $credentials = json_decode($credentials_json, true);
            $storage = new \Google\Cloud\Storage\StorageClient([
                'keyFile' => $credentials
            ]);

            $bucket = $storage->bucket($bucket_name);
            $bucket->exists();

            return array(
                'success' => true,
                'message' => 'Connection successful'
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}

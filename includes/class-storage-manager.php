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
     * Get local storage directory path
     */
    public static function get_storage_dir() {
        $custom_path = get_option('signflow_storage_path', '');

        // Use custom path if set and exists
        if (!empty($custom_path) && is_dir($custom_path) && is_writable($custom_path)) {
            return rtrim($custom_path, '/\\');
        }

        // Default to wp-content/uploads/wp-signflow
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/wp-signflow';
    }

    /**
     * Get storage URL base
     */
    public static function get_storage_url() {
        $custom_path = get_option('signflow_storage_path', '');
        $upload_dir = wp_upload_dir();
        $default_dir = $upload_dir['basedir'] . '/wp-signflow';

        // If using custom path, we can't easily generate URL
        if (!empty($custom_path) && $custom_path !== $default_dir) {
            // Return empty - files from custom path should be served differently
            return '';
        }

        return $upload_dir['baseurl'] . '/wp-signflow';
    }

    /**
     * Convert full file path to public URL
     */
    public static function path_to_url($filepath) {
        if (empty($filepath)) {
            return '';
        }

        $storage_path = self::get_storage_dir();
        $storage_url = self::get_storage_url();

        // If using custom storage path without URL, cannot generate URL
        if (empty($storage_url)) {
            return '';
        }

        // Replace storage path with storage URL
        $url = str_replace($storage_path, $storage_url, $filepath);
        $url = str_replace('\\', '/', $url); // Normalize slashes for Windows

        return $url;
    }

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
                // Use signed PDF if exists, otherwise original
                $pdf_filename = !empty($contract->signed_pdf_path) ? $contract->signed_pdf_path : $contract->original_pdf_path;
                $object_name = 'contracts/' . $pdf_filename;
                $object = $bucket->object($object_name);

                return $object->signedUrl(new \DateTime($expiration));

            } catch (Exception $e) {
                return false;
            }
        }

        // Local storage: return direct URL (protected by .htaccess)
        // Use signed PDF if exists, otherwise original (paths are now full paths)
        $pdf_path = !empty($contract->signed_pdf_path) ? $contract->signed_pdf_path : $contract->original_pdf_path;
        return self::path_to_url($pdf_path);
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

        $success = true;

        if ($storage_type === 'google_cloud') {
            // Delete both original and signed from Google Cloud
            if ($contract->original_pdf_path) {
                $success = $success && self::delete_from_google_cloud('contracts/' . $contract->original_pdf_path);
            }
            if ($contract->signed_pdf_path) {
                $success = $success && self::delete_from_google_cloud('contracts/' . $contract->signed_pdf_path);
            }
            return $success;
        }

        // Delete from local storage
        if ($contract->original_pdf_path) {
            $pdf_path = WP_SignFlow_PDF_Generator::get_pdf_path($contract->original_pdf_path);
            if (file_exists($pdf_path)) {
                $success = $success && unlink($pdf_path);
            }
        }
        if ($contract->signed_pdf_path) {
            $pdf_path = WP_SignFlow_PDF_Generator::get_pdf_path($contract->signed_pdf_path);
            if (file_exists($pdf_path)) {
                $success = $success && unlink($pdf_path);
            }
        }
        return $success;

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

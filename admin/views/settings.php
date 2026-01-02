<?php
/**
 * Settings view
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle API key generation
if (isset($_POST['generate_api_key'])) {
    check_admin_referer('signflow_settings');
    $new_api_key = bin2hex(random_bytes(32));
    update_option('signflow_api_key', $new_api_key);
    echo '<div class="notice notice-success"><p>' . esc_html__('New API key generated!', 'wp-signflow') . '</p></div>';
}

// Handle GCS connection test
if (isset($_POST['test_gcs'])) {
    check_admin_referer('signflow_settings');
    $test_result = WP_SignFlow_Storage_Manager::test_gcs_connection();
    if ($test_result['success']) {
        echo '<div class="notice notice-success"><p>' . esc_html__('Google Cloud Storage connection successful!', 'wp-signflow') . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . sprintf(__('Connection failed: %s', 'wp-signflow'), esc_html($test_result['message'])) . '</p></div>';
    }
}

$storage_type = get_option('signflow_storage_type', 'local');
$storage_path = get_option('signflow_storage_path', '');
$gcs_bucket = get_option('signflow_gcs_bucket', '');
$gcs_credentials = get_option('signflow_gcs_credentials', '');
$api_key = get_option('signflow_api_key', '');
$certificate_language = get_option('signflow_certificate_language', 'en');

// Default storage path
$default_upload_dir = wp_upload_dir();
$default_storage_path = $default_upload_dir['basedir'] . '/wp-signflow';
?>

<div class="wrap">
    <h1><?php _e('SignFlow Settings', 'wp-signflow'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('signflow_settings'); ?>

        <h2><?php _e('General Settings', 'wp-signflow'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="signflow_certificate_language"><?php _e('Certificate Language', 'wp-signflow'); ?></label>
                </th>
                <td>
                    <select name="signflow_certificate_language" id="signflow_certificate_language">
                        <option value="en" <?php selected($certificate_language, 'en'); ?>>English</option>
                        <option value="fr" <?php selected($certificate_language, 'fr'); ?>>Français</option>
                    </select>
                    <p class="description"><?php _e('Language used for generated signature certificates', 'wp-signflow'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php _e('Storage Settings', 'wp-signflow'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Storage Type', 'wp-signflow'); ?></th>
                <td>
                    <label>
                        <input type="radio" name="signflow_storage_type" value="local"
                               <?php checked($storage_type, 'local'); ?>>
                        <?php _e('Local Storage', 'wp-signflow'); ?>
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="signflow_storage_type" value="google_cloud"
                               <?php checked($storage_type, 'google_cloud'); ?>>
                        <?php _e('Google Cloud Storage', 'wp-signflow'); ?>
                    </label>
                    <p class="description"><?php _e('Choose where to store signed contracts', 'wp-signflow'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="signflow_storage_path"><?php _e('Local Storage Path', 'wp-signflow'); ?></label>
                </th>
                <td>
                    <input type="text" name="signflow_storage_path" id="signflow_storage_path"
                           value="<?php echo esc_attr($storage_path); ?>" class="regular-text"
                           placeholder="<?php echo esc_attr($default_storage_path); ?>">
                    <p class="description">
                        <?php printf(__('Custom storage directory path (absolute path). Leave empty to use default: %s', 'wp-signflow'), '<code>' . esc_html($default_storage_path) . '</code>'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h3><?php _e('Google Cloud Storage Configuration', 'wp-signflow'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="signflow_gcs_bucket"><?php _e('Bucket Name', 'wp-signflow'); ?></label>
                </th>
                <td>
                    <input type="text" name="signflow_gcs_bucket" id="signflow_gcs_bucket"
                           value="<?php echo esc_attr($gcs_bucket); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Google Cloud Storage bucket name', 'wp-signflow'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="signflow_gcs_credentials"><?php _e('Service Account JSON', 'wp-signflow'); ?></label>
                </th>
                <td>
                    <textarea name="signflow_gcs_credentials" id="signflow_gcs_credentials"
                              rows="10" class="large-text code"><?php echo esc_textarea($gcs_credentials); ?></textarea>
                    <p class="description">
                        <?php printf(__('Paste your Google Cloud service account JSON credentials here. <a href="%s" target="_blank">Learn how to create service account keys</a>', 'wp-signflow'), 'https://cloud.google.com/iam/docs/creating-managing-service-account-keys'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'wp-signflow'); ?>">
        </p>
    </form>

    <form method="post" style="display: inline;">
        <?php wp_nonce_field('signflow_settings'); ?>
        <input type="submit" name="test_gcs" class="button" value="<?php esc_attr_e('Test Google Cloud Connection', 'wp-signflow'); ?>">
    </form>

    <hr>

    <h2><?php _e('API Settings', 'wp-signflow'); ?></h2>
    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('API Key', 'wp-signflow'); ?></th>
            <td>
                <?php if ($api_key): ?>
                    <input type="text" readonly value="<?php echo esc_attr($api_key); ?>" class="regular-text code">
                    <p class="description"><?php printf(__('Use this key in the %s header for REST API requests', 'wp-signflow'), '<code>X-SignFlow-API-Key</code>'); ?></p>
                <?php else: ?>
                    <p class="description"><?php _e('No API key generated yet. Click below to generate one.', 'wp-signflow'); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <form method="post">
        <?php wp_nonce_field('signflow_settings'); ?>
        <input type="submit" name="generate_api_key" class="button" value="<?php echo esc_attr($api_key ? __('Regenerate API Key', 'wp-signflow') : __('Generate API Key', 'wp-signflow')); ?>">
        <p class="description" style="color: #dc3232;">
            <?php if ($api_key): ?>
                <?php _e('Warning: Regenerating will invalidate the current API key and break any integrations using it.', 'wp-signflow'); ?>
            <?php endif; ?>
        </p>
    </form>

    <hr>

    <h2><?php _e('API Documentation', 'wp-signflow'); ?></h2>
    <h3><?php _e('REST API Endpoints', 'wp-signflow'); ?></h3>
    <table class="widefat" style="margin-top: 20px;">
        <thead>
            <tr>
                <th><?php _e('Endpoint', 'wp-signflow'); ?></th>
                <th><?php _e('Method', 'wp-signflow'); ?></th>
                <th><?php _e('Description', 'wp-signflow'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>/wp-json/signflow/v1/generate</code></td>
                <td>POST</td>
                <td><?php _e('Generate a new contract', 'wp-signflow'); ?></td>
            </tr>
            <tr>
                <td><code>/wp-json/signflow/v1/contract/{id}</code></td>
                <td>GET</td>
                <td><?php _e('Get contract details', 'wp-signflow'); ?></td>
            </tr>
            <tr>
                <td><code>/wp-json/signflow/v1/contract/{id}/status</code></td>
                <td>GET</td>
                <td><?php _e('Get contract status', 'wp-signflow'); ?></td>
            </tr>
            <tr>
                <td><code>/wp-json/signflow/v1/contract/{id}/verify</code></td>
                <td>GET</td>
                <td><?php _e('Verify contract signature', 'wp-signflow'); ?></td>
            </tr>
            <tr>
                <td><code>/wp-json/signflow/v1/contract/{id}/audit</code></td>
                <td>GET</td>
                <td><?php _e('Get contract audit trail', 'wp-signflow'); ?></td>
            </tr>
        </tbody>
    </table>

    <h3><?php _e('PHP Functions for Other Plugins', 'wp-signflow'); ?></h3>
    <pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; overflow-x: auto;"><code>// Generate a contract
$result = signflow_generate_contract('template-slug', [
    'client_name' => 'John Doe',
    'contract_date' => date('Y-m-d'),
    'amount' => '$1,000'
]);

// Get contract status
$status = signflow_get_contract_status($contract_id);

// Verify signature
$is_valid = signflow_verify_signature($contract_id);

// Get audit trail
$audit = signflow_get_audit_trail($contract_id);

// Get signature URL
$url = signflow_get_signature_url($token);
</code></pre>
</div>

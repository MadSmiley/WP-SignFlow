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
    echo '<div class="notice notice-success"><p>New API key generated!</p></div>';
}

// Handle GCS connection test
if (isset($_POST['test_gcs'])) {
    check_admin_referer('signflow_settings');
    $test_result = WP_SignFlow_Storage_Manager::test_gcs_connection();
    if ($test_result['success']) {
        echo '<div class="notice notice-success"><p>Google Cloud Storage connection successful!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Connection failed: ' . esc_html($test_result['message']) . '</p></div>';
    }
}

$storage_type = get_option('signflow_storage_type', 'local');
$gcs_bucket = get_option('signflow_gcs_bucket', '');
$gcs_credentials = get_option('signflow_gcs_credentials', '');
$api_key = get_option('signflow_api_key', '');
?>

<div class="wrap">
    <h1>SignFlow Settings</h1>

    <form method="post" action="options.php">
        <?php settings_fields('signflow_settings'); ?>

        <h2>Storage Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Storage Type</th>
                <td>
                    <label>
                        <input type="radio" name="signflow_storage_type" value="local"
                               <?php checked($storage_type, 'local'); ?>>
                        Local Storage
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="signflow_storage_type" value="google_cloud"
                               <?php checked($storage_type, 'google_cloud'); ?>>
                        Google Cloud Storage
                    </label>
                    <p class="description">Choose where to store signed contracts</p>
                </td>
            </tr>
        </table>

        <h3>Google Cloud Storage Configuration</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="signflow_gcs_bucket">Bucket Name</label>
                </th>
                <td>
                    <input type="text" name="signflow_gcs_bucket" id="signflow_gcs_bucket"
                           value="<?php echo esc_attr($gcs_bucket); ?>" class="regular-text">
                    <p class="description">Your Google Cloud Storage bucket name</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="signflow_gcs_credentials">Service Account JSON</label>
                </th>
                <td>
                    <textarea name="signflow_gcs_credentials" id="signflow_gcs_credentials"
                              rows="10" class="large-text code"><?php echo esc_textarea($gcs_credentials); ?></textarea>
                    <p class="description">
                        Paste your Google Cloud service account JSON credentials here.
                        <a href="https://cloud.google.com/iam/docs/creating-managing-service-account-keys" target="_blank">Learn how to create service account keys</a>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
        </p>
    </form>

    <form method="post" style="display: inline;">
        <?php wp_nonce_field('signflow_settings'); ?>
        <input type="submit" name="test_gcs" class="button" value="Test Google Cloud Connection">
    </form>

    <hr>

    <h2>API Settings</h2>
    <table class="form-table">
        <tr>
            <th scope="row">API Key</th>
            <td>
                <?php if ($api_key): ?>
                    <input type="text" readonly value="<?php echo esc_attr($api_key); ?>" class="regular-text code">
                    <p class="description">Use this key in the <code>X-SignFlow-API-Key</code> header for REST API requests</p>
                <?php else: ?>
                    <p class="description">No API key generated yet. Click below to generate one.</p>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <form method="post">
        <?php wp_nonce_field('signflow_settings'); ?>
        <input type="submit" name="generate_api_key" class="button" value="<?php echo $api_key ? 'Regenerate API Key' : 'Generate API Key'; ?>">
        <p class="description" style="color: #dc3232;">
            <?php if ($api_key): ?>
                Warning: Regenerating will invalidate the current API key and break any integrations using it.
            <?php endif; ?>
        </p>
    </form>

    <hr>

    <h2>API Documentation</h2>
    <h3>REST API Endpoints</h3>
    <table class="widefat" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Endpoint</th>
                <th>Method</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>/wp-json/signflow/v1/generate</code></td>
                <td>POST</td>
                <td>Generate a new contract</td>
            </tr>
            <tr>
                <td><code>/wp-json/signflow/v1/contract/{id}</code></td>
                <td>GET</td>
                <td>Get contract details</td>
            </tr>
            <tr>
                <td><code>/wp-json/signflow/v1/contract/{id}/status</code></td>
                <td>GET</td>
                <td>Get contract status</td>
            </tr>
            <tr>
                <td><code>/wp-json/signflow/v1/contract/{id}/verify</code></td>
                <td>GET</td>
                <td>Verify contract signature</td>
            </tr>
            <tr>
                <td><code>/wp-json/signflow/v1/contract/{id}/audit</code></td>
                <td>GET</td>
                <td>Get contract audit trail</td>
            </tr>
        </tbody>
    </table>

    <h3>PHP Functions for Other Plugins</h3>
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

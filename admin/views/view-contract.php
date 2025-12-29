<?php
/**
 * View contract details
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$contract) {
    wp_die('Contract not found');
}
?>

<div class="wrap">
    <h1><?php printf(__('Contract #%d', 'wp-signflow'), $contract->id); ?></h1>

    <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
        <h2><?php _e('Contract Information', 'wp-signflow'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php _e('Status:', 'wp-signflow'); ?></th>
                <td>
                    <?php if ($contract->status === 'signed'): ?>
                        <span style="color: #46b450; font-weight: bold;">✓ <?php _e('Signed', 'wp-signflow'); ?></span>
                    <?php else: ?>
                        <span style="color: #dc3232; font-weight: bold;">○ <?php _e('Pending', 'wp-signflow'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Created:', 'wp-signflow'); ?></th>
                <td><?php echo esc_html($contract->created_at); ?></td>
            </tr>
            <tr>
                <th><?php _e('Signed:', 'wp-signflow'); ?></th>
                <td><?php echo $contract->signed_at ? esc_html($contract->signed_at) : __('Not signed yet', 'wp-signflow'); ?></td>
            </tr>
            <?php if ($contract->pdf_hash): ?>
                <tr>
                    <th><?php _e('PDF Hash (SHA-256):', 'wp-signflow'); ?></th>
                    <td><code><?php echo esc_html($contract->pdf_hash); ?></code></td>
                </tr>
            <?php endif; ?>
            <?php if ($contract->pdf_path): ?>
                <tr>
                    <th><?php _e('Documents:', 'wp-signflow'); ?></th>
                    <td>
                        <?php
                        $upload_dir = wp_upload_dir();
                        $pdf_url = $upload_dir['baseurl'] . '/wp-signflow/' . $contract->pdf_path;
                        ?>
                        <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="button button-primary">
                            📄 <?php _e('Download Contract PDF', 'wp-signflow'); ?>
                        </a>
                        <?php if (isset($contract->certificate_path) && $contract->certificate_path): ?>
                            <?php
                            $cert_url = $upload_dir['baseurl'] . '/wp-signflow/' . $contract->certificate_path;
                            ?>
                            <a href="<?php echo esc_url($cert_url); ?>" target="_blank" class="button button-secondary" style="margin-left: 10px;">
                                🔒 <?php _e('Download Certificate', 'wp-signflow'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if ($contract->status === 'pending'): ?>
                <tr>
                    <th><?php _e('Signature URL:', 'wp-signflow'); ?></th>
                    <td>
                        <input type="text" readonly value="<?php echo esc_attr(WP_SignFlow_Contract_Generator::get_signature_url($contract->contract_token)); ?>" style="width: 100%; max-width: 600px;">
                        <br><a href="<?php echo esc_url(WP_SignFlow_Contract_Generator::get_signature_url($contract->contract_token)); ?>" target="_blank" class="button"><?php _e('Open Signature Page', 'wp-signflow'); ?></a>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if ($signature): ?>
        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
            <h2><?php _e('Signature Details', 'wp-signflow'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php _e('Signer Name:', 'wp-signflow'); ?></th>
                    <td><?php echo esc_html($signature->signer_name); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Signer Email:', 'wp-signflow'); ?></th>
                    <td><?php echo esc_html($signature->signer_email); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Signed At:', 'wp-signflow'); ?></th>
                    <td><?php echo esc_html($signature->signed_at); ?></td>
                </tr>
                <tr>
                    <th><?php _e('IP Address:', 'wp-signflow'); ?></th>
                    <td><?php echo esc_html($signature->ip_address); ?></td>
                </tr>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($audit_trail)): ?>
        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
            <h2><?php _e('Audit Trail', 'wp-signflow'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Event', 'wp-signflow'); ?></th>
                        <th><?php _e('Date/Time', 'wp-signflow'); ?></th>
                        <th><?php _e('IP Address', 'wp-signflow'); ?></th>
                        <th><?php _e('Details', 'wp-signflow'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit_trail as $event): ?>
                        <tr>
                            <td><strong><?php echo esc_html($event->event_type); ?></strong></td>
                            <td><?php echo esc_html($event->created_at); ?></td>
                            <td><?php echo esc_html($event->ip_address); ?></td>
                            <td>
                                <?php if (!empty($event->event_data)): ?>
                                    <code><?php echo esc_html(json_encode($event->event_data)); ?></code>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p>
        <a href="<?php echo admin_url('admin.php?page=wp-signflow-contracts'); ?>" class="button">← <?php _e('Back to Contracts', 'wp-signflow'); ?></a>
    </p>
</div>

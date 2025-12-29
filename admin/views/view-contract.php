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
    <h1>Contract #<?php echo esc_html($contract->id); ?></h1>

    <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
        <h2>Contract Information</h2>
        <table class="form-table">
            <tr>
                <th>Status:</th>
                <td>
                    <?php if ($contract->status === 'signed'): ?>
                        <span style="color: #46b450; font-weight: bold;">✓ Signed</span>
                    <?php else: ?>
                        <span style="color: #dc3232; font-weight: bold;">○ Pending</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Created:</th>
                <td><?php echo esc_html($contract->created_at); ?></td>
            </tr>
            <tr>
                <th>Signed:</th>
                <td><?php echo $contract->signed_at ? esc_html($contract->signed_at) : 'Not signed yet'; ?></td>
            </tr>
            <?php if ($contract->pdf_hash): ?>
                <tr>
                    <th>PDF Hash (SHA-256):</th>
                    <td><code><?php echo esc_html($contract->pdf_hash); ?></code></td>
                </tr>
            <?php endif; ?>
            <?php if ($contract->pdf_path): ?>
                <tr>
                    <th>Documents:</th>
                    <td>
                        <?php
                        $upload_dir = wp_upload_dir();
                        $pdf_url = $upload_dir['baseurl'] . '/wp-signflow/' . $contract->pdf_path;
                        ?>
                        <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="button button-primary">
                            📄 Download Contract PDF
                        </a>
                        <?php if (isset($contract->certificate_path) && $contract->certificate_path): ?>
                            <?php
                            $cert_url = $upload_dir['baseurl'] . '/wp-signflow/' . $contract->certificate_path;
                            ?>
                            <a href="<?php echo esc_url($cert_url); ?>" target="_blank" class="button button-secondary" style="margin-left: 10px;">
                                🔒 Download Certificate
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if ($contract->status === 'pending'): ?>
                <tr>
                    <th>Signature URL:</th>
                    <td>
                        <input type="text" readonly value="<?php echo esc_attr(WP_SignFlow_Contract_Generator::get_signature_url($contract->contract_token)); ?>" style="width: 100%; max-width: 600px;">
                        <br><a href="<?php echo esc_url(WP_SignFlow_Contract_Generator::get_signature_url($contract->contract_token)); ?>" target="_blank" class="button">Open Signature Page</a>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if ($signature): ?>
        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
            <h2>Signature Details</h2>
            <table class="form-table">
                <tr>
                    <th>Signer Name:</th>
                    <td><?php echo esc_html($signature->signer_name); ?></td>
                </tr>
                <tr>
                    <th>Signer Email:</th>
                    <td><?php echo esc_html($signature->signer_email); ?></td>
                </tr>
                <tr>
                    <th>Signed At:</th>
                    <td><?php echo esc_html($signature->signed_at); ?></td>
                </tr>
                <tr>
                    <th>IP Address:</th>
                    <td><?php echo esc_html($signature->ip_address); ?></td>
                </tr>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($audit_trail)): ?>
        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
            <h2>Audit Trail</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date/Time</th>
                        <th>IP Address</th>
                        <th>Details</th>
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
        <a href="<?php echo admin_url('admin.php?page=wp-signflow-contracts'); ?>" class="button">← Back to Contracts</a>
    </p>
</div>

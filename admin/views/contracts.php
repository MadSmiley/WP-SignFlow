<?php
/**
 * Contracts list view
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Contracts</h1>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Template</th>
                <th>Status</th>
                <th>Created</th>
                <th>Signed</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contracts)): ?>
                <tr>
                    <td colspan="6">No contracts found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($contracts as $contract): ?>
                    <tr>
                        <td><strong>#<?php echo esc_html($contract->id); ?></strong></td>
                        <td><?php echo esc_html($contract->template_name ?: 'N/A'); ?></td>
                        <td>
                            <?php if ($contract->status === 'signed'): ?>
                                <span style="color: #46b450;">✓ Signed</span>
                            <?php else: ?>
                                <span style="color: #dc3232;">○ Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($contract->created_at); ?></td>
                        <td><?php echo $contract->signed_at ? esc_html($contract->signed_at) : '-'; ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wp-signflow-view-contract&id=' . $contract->id); ?>">View</a>
                            <?php if ($contract->status === 'pending'): ?>
                                | <a href="<?php echo WP_SignFlow_Contract_Generator::get_signature_url($contract->contract_token); ?>" target="_blank">Signature Link</a>
                                | <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-signflow-contracts&action=delete&id=' . $contract->id), 'delete_contract_' . $contract->id); ?>"
                                     onclick="return confirm('Are you sure you want to delete this unsigned contract?');"
                                     style="color: #dc3232;">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

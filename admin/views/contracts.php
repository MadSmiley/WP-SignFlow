<?php
/**
 * Contracts list view
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Contracts', 'wp-signflow'); ?></h1>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'wp-signflow'); ?></th>
                <th><?php _e('Template', 'wp-signflow'); ?></th>
                <th><?php _e('Status', 'wp-signflow'); ?></th>
                <th><?php _e('Created', 'wp-signflow'); ?></th>
                <th><?php _e('Signed', 'wp-signflow'); ?></th>
                <th><?php _e('Actions', 'wp-signflow'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contracts)): ?>
                <tr>
                    <td colspan="6"><?php _e('No contracts found.', 'wp-signflow'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($contracts as $contract): ?>
                    <tr>
                        <td><strong>#<?php echo esc_html($contract->id); ?></strong></td>
                        <td><?php echo esc_html($contract->template_name ?: 'N/A'); ?></td>
                        <td>
                            <?php if ($contract->status === 'signed'): ?>
                                <span style="color: #46b450;">✓ <?php _e('Signed', 'wp-signflow'); ?></span>
                            <?php else: ?>
                                <span style="color: #dc3232;">○ <?php _e('Pending', 'wp-signflow'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($contract->created_at); ?></td>
                        <td><?php echo $contract->signed_at ? esc_html($contract->signed_at) : '-'; ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wp-signflow-view-contract&id=' . $contract->id); ?>"><?php _e('View', 'wp-signflow'); ?></a>
                            <?php if ($contract->status === 'pending'): ?>
                                | <a href="<?php echo WP_SignFlow_Contract_Generator::get_signature_url($contract->contract_token); ?>" target="_blank"><?php _e('Signature Link', 'wp-signflow'); ?></a>
                                | <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-signflow-contracts&action=delete&id=' . $contract->id), 'delete_contract_' . $contract->id); ?>"
                                     onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this unsigned contract?', 'wp-signflow')); ?>');"
                                     style="color: #dc3232;"><?php _e('Delete', 'wp-signflow'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

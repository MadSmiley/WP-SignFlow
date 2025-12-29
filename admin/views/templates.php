<?php
/**
 * Templates list view
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>
        Contract Templates
        <a href="<?php echo admin_url('admin.php?page=wp-signflow-edit-template'); ?>" class="page-title-action">Add New</a>
    </h1>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'saved'): ?>
        <div class="notice notice-success is-dismissible">
            <p>Template saved successfully!</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
        <div class="notice notice-success is-dismissible">
            <p>Template deleted successfully!</p>
        </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Variables</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($templates)): ?>
                <tr>
                    <td colspan="5">No templates found. <a href="<?php echo admin_url('admin.php?page=wp-signflow-edit-template'); ?>">Create your first template</a></td>
                </tr>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td><strong><?php echo esc_html($template->name); ?></strong></td>
                        <td><code><?php echo esc_html($template->slug); ?></code></td>
                        <td>
                            <?php if (!empty($template->variables)): ?>
                                <?php foreach ($template->variables as $var): ?>
                                    <code>{{<?php echo esc_html($var); ?>}}</code>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <em>No variables</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($template->created_at); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wp-signflow-edit-template&id=' . $template->id); ?>">Edit</a> |
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=signflow_delete_template&id=' . $template->id), 'signflow_delete_template_' . $template->id); ?>" onclick="return confirm('Are you sure?');" style="color: #b32d2e;">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

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
        <?php _e('Contract Templates', 'wp-signflow'); ?>
        <a href="<?php echo admin_url('admin.php?page=wp-signflow-edit-template'); ?>" class="page-title-action"><?php _e('Add New', 'wp-signflow'); ?></a>
    </h1>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'saved'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Template saved successfully!', 'wp-signflow'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Template deleted successfully!', 'wp-signflow'); ?></p>
        </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Name', 'wp-signflow'); ?></th>
                <th><?php _e('Language', 'wp-signflow'); ?></th>
                <th><?php _e('Slug', 'wp-signflow'); ?></th>
                <th><?php _e('Variables', 'wp-signflow'); ?></th>
                <th><?php _e('Created', 'wp-signflow'); ?></th>
                <th><?php _e('Actions', 'wp-signflow'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($templates)): ?>
                <tr>
                    <td colspan="6"><?php printf(__('No templates found. <a href="%s">Create your first template</a>', 'wp-signflow'), admin_url('admin.php?page=wp-signflow-edit-template')); ?></td>
                </tr>
            <?php else: ?>
                <?php
                $language_names = array(
                    'en' => 'English',
                    'fr' => 'Français',
                    'es' => 'Español',
                    'de' => 'Deutsch',
                    'it' => 'Italiano',
                    'pt' => 'Português',
                    'nl' => 'Nederlands'
                );
                ?>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td><strong><?php echo esc_html($template->name); ?></strong></td>
                        <td>
                            <?php
                            $lang = !empty($template->language) ? $template->language : 'en';
                            echo esc_html($language_names[$lang] ?? $lang);
                            ?>
                        </td>
                        <td><code><?php echo esc_html($template->slug); ?></code></td>
                        <td>
                            <?php
                            $declared_count = !empty($template->declared_variables) ? count($template->declared_variables) : 0;
                            $detected_count = !empty($template->detected_variables) ? count($template->detected_variables) : 0;

                            if ($declared_count > 0 || $detected_count > 0) {
                                echo sprintf(
                                    __('%d available / %d used', 'wp-signflow'),
                                    $declared_count,
                                    $detected_count
                                );
                            } else {
                                echo '<em>' . __('No variables', 'wp-signflow') . '</em>';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($template->created_at); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wp-signflow-edit-template&slug=' . urlencode($template->slug)); ?>"><?php _e('Edit', 'wp-signflow'); ?></a> |
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=signflow_delete_template&slug=' . urlencode($template->slug)), 'signflow_delete_template_' . $template->slug); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure?', 'wp-signflow')); ?>');" style="color: #b32d2e;"><?php _e('Delete', 'wp-signflow'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

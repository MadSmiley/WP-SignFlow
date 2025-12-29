<?php
/**
 * Edit template view
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_new = !$template;
$page_title = $is_new ? 'Add New Template' : 'Edit Template';
?>

<div class="wrap">
    <h1><?php echo $page_title; ?></h1>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="signflow_save_template">
        <?php wp_nonce_field('signflow_save_template'); ?>
        <?php if (!$is_new): ?>
            <input type="hidden" name="template_id" value="<?php echo esc_attr($template->id); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="template_name">Template Name</label>
                </th>
                <td>
                    <input type="text" name="template_name" id="template_name" class="regular-text"
                           value="<?php echo $is_new ? '' : esc_attr($template->name); ?>" required>
                    <p class="description">Give your template a descriptive name</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="template_content">Template Content</label>
                </th>
                <td>
                    <?php
                    $content = $is_new ? '' : $template->content;
                    wp_editor($content, 'template_content', array(
                        'textarea_name' => 'template_content',
                        'textarea_rows' => 20,
                        'teeny' => false,
                        'media_buttons' => false
                    ));
                    ?>
                    <p class="description">
                        Use <code>{{variable_name}}</code> for dynamic content.
                        Example: <code>{{client_name}}</code>, <code>{{contract_date}}</code>, <code>{{amount}}</code>
                    </p>
                </td>
            </tr>
        </table>

        <?php if (!$is_new && !empty($template->variables)): ?>
            <h2>Detected Variables</h2>
            <p>The following variables were found in your template:</p>
            <ul>
                <?php foreach ($template->variables as $var): ?>
                    <li><code>{{<?php echo esc_html($var); ?>}}</code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $is_new ? 'Create Template' : 'Update Template'; ?>">
            <a href="<?php echo admin_url('admin.php?page=wp-signflow'); ?>" class="button">Cancel</a>
        </p>
    </form>
</div>

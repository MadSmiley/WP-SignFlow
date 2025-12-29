<?php
/**
 * Edit template view
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_new = !$template;
$page_title = $is_new ? __('Add New Template', 'wp-signflow') : __('Edit Template', 'wp-signflow');
?>

<div class="wrap">
    <h1><?php echo esc_html($page_title); ?></h1>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="signflow_save_template">
        <?php wp_nonce_field('signflow_save_template'); ?>
        <?php if (!$is_new): ?>
            <input type="hidden" name="template_id" value="<?php echo esc_attr($template->id); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="template_name"><?php _e('Template Name', 'wp-signflow'); ?></label>
                </th>
                <td>
                    <input type="text" name="template_name" id="template_name" class="regular-text"
                           value="<?php echo $is_new ? '' : esc_attr($template->name); ?>" required>
                    <p class="description"><?php _e('Give your template a descriptive name', 'wp-signflow'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="template_language"><?php _e('Language', 'wp-signflow'); ?></label>
                </th>
                <td>
                    <select name="template_language" id="template_language" class="regular-text">
                        <?php
                        $languages = array(
                            'en' => 'English',
                            'fr' => 'Français',
                            'es' => 'Español',
                            'de' => 'Deutsch',
                            'it' => 'Italiano',
                            'pt' => 'Português',
                            'nl' => 'Nederlands'
                        );
                        $current_lang = $is_new ? 'en' : ($template->language ?? 'en');
                        foreach ($languages as $code => $name) {
                            $selected = ($code === $current_lang) ? 'selected' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select the language for the signature page.', 'wp-signflow'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="template_content"><?php _e('Template Content', 'wp-signflow'); ?></label>
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
                        <?php printf(__('Use %s for dynamic content. Example: %s, %s, %s', 'wp-signflow'), '<code>{{variable_name}}</code>', '<code>{{client_name}}</code>', '<code>{{contract_date}}</code>', '<code>{{amount}}</code>'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php if (!$is_new && !empty($template->variables)): ?>
            <h2><?php _e('Detected Variables', 'wp-signflow'); ?></h2>
            <p><?php _e('The following variables were found in your template:', 'wp-signflow'); ?></p>
            <ul>
                <?php foreach ($template->variables as $var): ?>
                    <li><code>{{<?php echo esc_html($var); ?>}}</code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr($is_new ? __('Create Template', 'wp-signflow') : __('Update Template', 'wp-signflow')); ?>">
            <a href="<?php echo admin_url('admin.php?page=wp-signflow'); ?>" class="button"><?php _e('Cancel', 'wp-signflow'); ?></a>
        </p>
    </form>
</div>

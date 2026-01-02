<?php
/**
 * Create Contract Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$templates = WP_SignFlow_Template_Manager::get_templates();
?>

<div class="wrap">
    <h1><?php _e('Create New Contract', 'wp-signflow'); ?></h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php _e('Contract created successfully!', 'wp-signflow'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-signflow-contracts')); ?>">
                    <?php _e('View all contracts', 'wp-signflow'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="create-contract-form">
        <input type="hidden" name="action" value="signflow_create_contract">
        <?php wp_nonce_field('signflow_create_contract', 'signflow_nonce'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="template_id"><?php _e('Template', 'wp-signflow'); ?> *</label>
                    </th>
                    <td>
                        <select name="template_id" id="template_id" class="regular-text" required>
                            <option value=""><?php _e('-- Select a template --', 'wp-signflow'); ?></option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo esc_attr($template->id); ?>"
                                        data-variables="<?php echo esc_attr(maybe_serialize($template->variables)); ?>"
                                        data-language="<?php echo esc_attr($template->language ?? 'en'); ?>">
                                    <?php echo esc_html($template->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select the template to use for this contract.', 'wp-signflow'); ?></p>
                    </td>
                </tr>

                <tr id="variables-section" style="display: none;">
                    <th scope="row">
                        <label><?php _e('Variables', 'wp-signflow'); ?></label>
                    </th>
                    <td>
                        <div id="variables-container"></div>
                        <p class="description"><?php _e('Fill in the variables for the selected template.', 'wp-signflow'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php _e('Create Contract', 'wp-signflow'); ?>
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-signflow-contracts')); ?>" class="button">
                <?php _e('Cancel', 'wp-signflow'); ?>
            </a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#template_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var variables = selectedOption.data('variables');

        if (variables) {
            try {
                // Parse serialized PHP data
                var varsArray = [];
                if (typeof variables === 'string' && variables.length > 0) {
                    // Simple parsing for array format
                    var matches = variables.match(/s:\d+:"([^"]+)"/g);
                    if (matches) {
                        matches.forEach(function(match) {
                            var varName = match.match(/s:\d+:"([^"]+)"/)[1];
                            if (varsArray.indexOf(varName) === -1) {
                                varsArray.push(varName);
                            }
                        });
                    }
                }

                if (varsArray.length > 0) {
                    var html = '<table class="form-table widefat">';
                    varsArray.forEach(function(varName) {
                        html += '<tr>';
                        html += '<th style="padding: 10px;"><label for="var_' + varName + '">' + varName + '</label></th>';
                        html += '<td style="padding: 10px;"><input type="text" name="variables[' + varName + ']" id="var_' + varName + '" class="regular-text"></td>';
                        html += '</tr>';
                    });
                    html += '</table>';

                    $('#variables-container').html(html);
                    $('#variables-section').show();
                } else {
                    $('#variables-section').hide();
                }
            } catch(e) {
                console.error('Error parsing variables:', e);
                $('#variables-section').hide();
            }
        } else {
            $('#variables-section').hide();
        }
    });
});
</script>
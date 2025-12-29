<?php
/**
 * Template Manager class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Template_Manager {

    /**
     * Create a new template
     */
    public static function create_template($name, $content, $variables = array(), $language = 'en') {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('templates');

        $slug = sanitize_title($name);

        $result = $wpdb->insert(
            $table,
            array(
                'name' => sanitize_text_field($name),
                'slug' => $slug,
                'content' => wp_kses_post($content),
                'variables' => maybe_serialize($variables),
                'language' => sanitize_text_field($language),
                'created_by' => get_current_user_id()
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update template
     */
    public static function update_template($id, $data) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('templates');

        $update_data = array();
        $format = array();

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $update_data['slug'] = sanitize_title($data['name']);
            $format[] = '%s';
            $format[] = '%s';
        }

        if (isset($data['content'])) {
            $update_data['content'] = wp_kses_post($data['content']);
            $format[] = '%s';
        }

        if (isset($data['variables'])) {
            $update_data['variables'] = maybe_serialize($data['variables']);
            $format[] = '%s';
        }

        if (isset($data['language'])) {
            $update_data['language'] = sanitize_text_field($data['language']);
            $format[] = '%s';
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }

    /**
     * Get template by ID
     */
    public static function get_template($id) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('templates');

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));

        if ($template) {
            $template->variables = maybe_unserialize($template->variables);
        }

        return $template;
    }

    /**
     * Get template by slug
     */
    public static function get_template_by_slug($slug) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('templates');

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s",
            $slug
        ));

        if ($template) {
            $template->variables = maybe_unserialize($template->variables);
        }

        return $template;
    }

    /**
     * Get all templates
     */
    public static function get_templates($args = array()) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('templates');

        $defaults = array(
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $query = "SELECT * FROM $table ORDER BY {$args['orderby']} {$args['order']} LIMIT {$args['limit']} OFFSET {$args['offset']}";
        $templates = $wpdb->get_results($query);

        foreach ($templates as $template) {
            $template->variables = maybe_unserialize($template->variables);
        }

        return $templates;
    }

    /**
     * Delete template
     */
    public static function delete_template($id) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('templates');

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    /**
     * Extract variables from template content
     */
    public static function extract_variables($content) {
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $content, $matches);
        return array_unique($matches[1]);
    }
}

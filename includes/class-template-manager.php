<?php
/**
 * Template Manager class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Template_Manager {

    /**
     * Register a template (for use by other plugins)
     *
     * @param string $slug Unique slug for the template (defined by the registering plugin)
     * @param array $args Template arguments
     *   - name (required): Template display name
     *   - content (required): Template HTML content with {{variables}}
     *   - variables (optional): Array of declared available variable names
     *   - language (optional): Template language (default: 'en')
     * @return string|false Template slug on success, false on failure
     */
    public static function register_template($slug, $args) {
        $defaults = array(
            'name' => '',
            'content' => '',
            'variables' => array(),
            'language' => 'en'
        );

        $args = wp_parse_args($args, $defaults);

        // Validate required fields
        if (empty($slug) || empty($args['name']) || empty($args['content'])) {
            return false;
        }

        // Check if template already exists
        $existing = self::get_template($slug);
        if ($existing) {
            // Update existing template
            return self::update_template($slug, $args);
        }

        // Create new template
        return self::create_template($args['name'], $args['content'], $args['variables'], $args['language'], $slug);
    }

    /**
     * Create a new template
     * @param string $name Template name
     * @param string $content Template HTML content
     * @param array $declared_variables Variables declared by the plugin (available for filling)
     * @param string $language Template language
     * @param string|null $custom_slug Custom slug (if null, generated from name)
     * @return string|false Template slug on success, false on failure
     */
    public static function create_template($name, $content, $declared_variables = array(), $language = 'en', $custom_slug = null) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('templates');

        // Use custom slug if provided, otherwise generate from name
        $slug = $custom_slug ? sanitize_key($custom_slug) : sanitize_title($name);

        // Always detect variables used in HTML
        $detected_variables = self::extract_variables($content);

        $result = $wpdb->insert(
            $table,
            array(
                'slug' => $slug,
                'name' => sanitize_text_field($name),
                'content' => wp_kses_post($content),
                'declared_variables' => maybe_serialize($declared_variables),
                'detected_variables' => maybe_serialize($detected_variables),
                'language' => sanitize_text_field($language),
                'created_by' => get_current_user_id()
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );

        if ($result) {
            return $slug;
        }

        return false;
    }

    /**
     * Update template
     * @param string $slug Template slug
     */
    public static function update_template($slug, $data) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('templates');

        $update_data = array();
        $format = array();

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }

        if (isset($data['content'])) {
            $update_data['content'] = wp_kses_post($data['content']);
            // Re-detect variables when content changes
            $update_data['detected_variables'] = maybe_serialize(self::extract_variables($data['content']));
            $format[] = '%s';
            $format[] = '%s';
        }

        if (isset($data['variables'])) {
            $update_data['declared_variables'] = maybe_serialize($data['variables']);
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
            array('slug' => $slug),
            $format,
            array('%s')
        );
    }

    /**
     * Get template by slug
     * @param string $slug Template slug
     */
    public static function get_template($slug) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('templates');

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s",
            $slug
        ));

        if ($template) {
            $template->declared_variables = maybe_unserialize($template->declared_variables);
            $template->detected_variables = maybe_unserialize($template->detected_variables);
        }

        return $template;
    }

    /**
     * Get template by slug (alias for backward compatibility)
     */
    public static function get_template_by_slug($slug) {
        return self::get_template($slug);
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
            $template->declared_variables = maybe_unserialize($template->declared_variables);
            $template->detected_variables = maybe_unserialize($template->detected_variables);
        }

        return $templates;
    }

    /**
     * Delete template by slug
     */
    public static function delete_template($slug) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('templates');

        return $wpdb->delete($table, array('slug' => $slug), array('%s'));
    }

    /**
     * Extract variables from template content
     */
    public static function extract_variables($content) {
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $content, $matches);
        return array_unique($matches[1]);
    }
}

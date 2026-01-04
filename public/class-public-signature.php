<?php
/**
 * Public Signature Page class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Public_Signature {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('template_redirect', array($this, 'handle_signature_page'));
        add_action('wp_ajax_signflow_submit_signature', array($this, 'ajax_submit_signature'));
        add_action('wp_ajax_nopriv_signflow_submit_signature', array($this, 'ajax_submit_signature'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Handle signature page request
     */
    public function handle_signature_page() {
        if (!isset($_GET['signflow_action']) || $_GET['signflow_action'] !== 'sign') {
            return;
        }

        if (empty($_GET['token'])) {
            $this->render_error_page('invalid_title', 'invalid_message');
            exit;
        }

        $token = sanitize_text_field($_GET['token']);
        $contract = WP_SignFlow_Contract_Generator::get_contract_by_token($token);

        if (!$contract) {
            $this->render_error_page('invalid_title', 'invalid_message');
            exit;
        }

        if ($contract->status === 'signed') {
            $this->render_error_page('already_signed_title', 'already_signed_message');
            exit;
        }

        // Check if contract has expired
        if (!empty($contract->expires_at)) {
            $expires_timestamp = strtotime($contract->expires_at);
            if ($expires_timestamp && $expires_timestamp < time()) {
                $this->render_error_page('expired_title', 'expired_message');
                exit;
            }
        }

        // Log page view
        WP_SignFlow_Audit_Trail::log_event($contract->id, 'signature_page_viewed', array(
            'token' => $token
        ));

        $this->render_signature_page($contract, $token);
        exit;
    }

    /**
     * Render signature page
     */
    private function render_signature_page($contract, $token) {
        // Get language - check URL parameter first, then template language
        $language = 'en';
        $available_languages = array('en', 'fr', 'es', 'de', 'it', 'pt', 'nl');

        if (!empty($_GET['lang']) && in_array($_GET['lang'], $available_languages)) {
            $language = sanitize_text_field($_GET['lang']);
        } elseif (!empty($contract->template_slug)) {
            $template = WP_SignFlow_Template_Manager::get_template($contract->template_slug);
            if ($template && !empty($template->language)) {
                $language = $template->language;
            }
        }

        // Get translations
        $translations = WP_SignFlow_Translations::get_signature_page_translations($language);

        // Get metadata for pre-filling signer information
        $metadata = !empty($contract->metadata) ? maybe_unserialize($contract->metadata) : array();
        $prefill_name = !empty($metadata['signer_name']) ? $metadata['signer_name'] : '';
        $prefill_email = !empty($metadata['signer_email']) ? $metadata['signer_email'] : '';
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html($translations['page_title']); ?> - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    background: #f5f5f5;
                    padding: 20px;
                }
                .signflow-container {
                    max-width: 900px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .signflow-header {
                    background: #2271b1;
                    color: white;
                    padding: 20px 30px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .signflow-header-content h1 {
                    font-size: 24px;
                    margin-bottom: 5px;
                }
                .language-selector {
                    margin-left: 20px;
                }
                .language-selector select {
                    padding: 8px 12px;
                    border: 1px solid rgba(255,255,255,0.3);
                    border-radius: 4px;
                    background: rgba(255,255,255,0.2);
                    color: white;
                    font-size: 14px;
                    cursor: pointer;
                    transition: all 0.3s;
                }
                .language-selector select:hover {
                    background: rgba(255,255,255,0.3);
                }
                .language-selector select option {
                    background: #2271b1;
                    color: white;
                }
                .signflow-content {
                    padding: 30px;
                }
                .contract-preview {
                    border: 1px solid #ddd;
                    padding: 20px;
                    margin-bottom: 30px;
                    background: #fafafa;
                    max-height: 400px;
                    overflow-y: auto;
                }
                .signature-section {
                    border-top: 2px solid #eee;
                    padding-top: 30px;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 600;
                    color: #333;
                }
                .form-group input[type="text"],
                .form-group input[type="email"] {
                    width: 100%;
                    padding: 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 16px;
                }
                .signature-pad-container {
                    border: 2px solid #2271b1;
                    border-radius: 4px;
                    background: white;
                    margin-bottom: 15px;
                    touch-action: none;
                }
                .signature-pad {
                    width: 100%;
                    height: 200px;
                    cursor: crosshair;
                }
                .signature-actions {
                    margin-bottom: 20px;
                }
                .btn {
                    padding: 12px 24px;
                    border: none;
                    border-radius: 4px;
                    font-size: 16px;
                    cursor: pointer;
                    transition: all 0.3s;
                }
                .btn-clear {
                    background: #f0f0f0;
                    color: #333;
                }
                .btn-clear:hover {
                    background: #e0e0e0;
                }
                .btn-primary {
                    background: #2271b1;
                    color: white;
                    font-size: 18px;
                    font-weight: 600;
                    width: 100%;
                    padding: 16px;
                }
                .btn-primary:hover {
                    background: #135e96;
                }
                .btn-primary:disabled {
                    background: #ccc;
                    cursor: not-allowed;
                }
                .consent-checkbox {
                    display: flex;
                    align-items: flex-start;
                    margin: 20px 0;
                    padding: 15px;
                    background: #f9f9f9;
                    border-radius: 4px;
                }
                .consent-checkbox input[type="checkbox"] {
                    margin-right: 10px;
                    margin-top: 4px;
                    width: 20px;
                    height: 20px;
                }
                .consent-checkbox label {
                    flex: 1;
                    font-size: 14px;
                    line-height: 1.6;
                }
                .alert {
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                    display: none;
                }
                .alert-success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                .alert-error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                @media (max-width: 768px) {
                    body { padding: 10px; }
                    .signflow-content { padding: 20px; }
                    .signflow-header {
                        flex-direction: column;
                        align-items: flex-start;
                    }
                    .signflow-header-content h1 { font-size: 20px; }
                    .language-selector {
                        margin-left: 0;
                        margin-top: 10px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="signflow-container">
                <div class="signflow-header">
                    <div class="signflow-header-content">
                        <h1><?php bloginfo('name'); ?></h1>
                        <p><?php echo esc_html($translations['page_title']); ?></p>
                    </div>
                    <div class="language-selector">
                        <select id="language-selector" onchange="changeLanguage(this.value)">
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
                            foreach ($language_names as $code => $name) {
                                $selected = ($code === $language) ? 'selected' : '';
                                echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="signflow-content">
                    <div class="alert alert-success" id="success-message">
                        <strong><?php echo esc_html($translations['success_title']); ?></strong> <?php echo esc_html($translations['success_message']); ?>
                    </div>
                    <div class="alert alert-error" id="error-message"></div>

                    <h2><?php echo esc_html($translations['contract_preview']); ?></h2>
                    <div class="contract-preview">
                        <?php
                        if ($contract->original_pdf_path) {
                            $pdf_url = WP_SignFlow_Storage_Manager::path_to_url($contract->original_pdf_path);

                            // Check if it's a PDF or HTML
                            if (substr($contract->original_pdf_path, -4) === '.pdf') {
                                // Display PDF in iframe
                                echo '<iframe src="' . esc_url($pdf_url) . '" style="width: 100%; height: 500px; border: none;"></iframe>';
                            } else {
                                // Fallback to HTML preview
                                echo '<iframe src="' . esc_url($pdf_url) . '" style="width: 100%; height: 500px; border: none;"></iframe>';
                            }
                        } else {
                            // Fallback to content preview
                            echo wp_kses_post($contract->contract_data['content']);
                        }
                        ?>
                    </div>

                    <div class="signature-section">
                        <h2><?php echo esc_html($translations['signature_section']); ?></h2>
                        <form id="signature-form">
                            <div class="form-group">
                                <label for="signer-name"><?php echo esc_html($translations['signer_name_label']); ?> *</label>
                                <input type="text" id="signer-name" name="signer_name" placeholder="<?php echo esc_attr($translations['signer_name_placeholder']); ?>" value="<?php echo esc_attr($prefill_name); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="signer-email"><?php echo esc_html($translations['signer_email_label']); ?> *</label>
                                <input type="email" id="signer-email" name="signer_email" placeholder="<?php echo esc_attr($translations['signer_email_placeholder']); ?>" value="<?php echo esc_attr($prefill_email); ?>" required>
                            </div>

                            <div class="form-group">
                                <label><?php echo esc_html($translations['signature_section']); ?> *</label>
                                <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
                                    <?php echo esc_html($translations['draw_signature_label']); ?>
                                </p>
                                <div class="signature-pad-container">
                                    <canvas id="signature-pad" class="signature-pad"></canvas>
                                </div>
                                <div class="signature-actions">
                                    <button type="button" class="btn btn-clear" id="clear-signature"><?php echo esc_html($translations['clear_button']); ?></button>
                                </div>
                            </div>

                            <div class="consent-checkbox">
                                <input type="checkbox" id="consent" name="consent" value="yes" required>
                                <label for="consent">
                                    <?php echo esc_html($translations['consent_label']); ?>
                                </label>
                            </div>

                            <input type="hidden" name="contract_token" value="<?php echo esc_attr($token); ?>">
                            <input type="hidden" name="action" value="signflow_submit_signature">
                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('signflow_signature'); ?>">
                            <input type="hidden" name="signature_data" id="signature-data">
                            <input type="hidden" name="consent_timestamp" id="consent-timestamp">

                            <button type="submit" class="btn btn-primary" id="submit-btn">
                                <?php echo esc_html($translations['submit_button']); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <script>
                function changeLanguage(lang) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('lang', lang);
                    window.location.href = url.toString();
                }
            </script>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Render error page
     */
    private function render_error_page($title_key, $message_key, $language = 'en') {
        $translations = WP_SignFlow_Translations::get_signature_page_translations($language);
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html($translations[$title_key]); ?> - <?php bloginfo('name'); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    background: #f5f5f5;
                    padding: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }
                .error-container {
                    max-width: 500px;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    padding: 40px;
                    text-align: center;
                }
                .error-icon {
                    font-size: 64px;
                    color: #dc3232;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #333;
                    margin-bottom: 15px;
                    font-size: 24px;
                }
                p {
                    color: #666;
                    font-size: 16px;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠</div>
                <h1><?php echo esc_html($translations[$title_key]); ?></h1>
                <p><?php echo esc_html($translations[$message_key]); ?></p>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!isset($_GET['signflow_action']) || $_GET['signflow_action'] !== 'sign') {
            return;
        }

        // Get language - check URL parameter first, then template language
        $language = 'en';
        $available_languages = array('en', 'fr', 'es', 'de', 'it', 'pt', 'nl');

        if (!empty($_GET['lang']) && in_array($_GET['lang'], $available_languages)) {
            $language = sanitize_text_field($_GET['lang']);
        } elseif (!empty($_GET['token'])) {
            $token = sanitize_text_field($_GET['token']);
            $contract = WP_SignFlow_Contract_Generator::get_contract_by_token($token);
            if ($contract && !empty($contract->template_slug)) {
                $template = WP_SignFlow_Template_Manager::get_template($contract->template_slug);
                if ($template && !empty($template->language)) {
                    $language = $template->language;
                }
            }
        }

        $translations = WP_SignFlow_Translations::get_signature_page_translations($language);

        wp_enqueue_script(
            'signature-pad',
            'https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js',
            array(),
            '4.1.7',
            true
        );

        wp_enqueue_script(
            'signflow-signature',
            WP_SIGNFLOW_PLUGIN_URL . 'assets/js/signature.js',
            array('signature-pad', 'jquery'),
            WP_SIGNFLOW_VERSION,
            true
        );

        wp_localize_script('signflow-signature', 'signflowData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'i18n' => array(
                'error_name_required' => $translations['error_name_required'],
                'error_email_required' => $translations['error_email_required'],
                'error_signature_required' => $translations['error_signature_required'],
                'error_consent_required' => $translations['error_consent_required'],
                'error_general' => $translations['error_general']
            )
        ));
    }

    /**
     * AJAX: Submit signature
     */
    public function ajax_submit_signature() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'signflow_signature')) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }

        // Validate required fields
        if (empty($_POST['contract_token']) || empty($_POST['signature_data']) ||
            empty($_POST['signer_name']) || empty($_POST['signer_email'])) {
            wp_send_json_error(array('message' => 'All fields are required'));
        }

        // Verify consent
        if (empty($_POST['consent']) || $_POST['consent'] !== 'yes') {
            wp_send_json_error(array('message' => 'You must consent to sign the contract'));
        }

        // Get contract
        $token = sanitize_text_field($_POST['contract_token']);
        $contract = WP_SignFlow_Contract_Generator::get_contract_by_token($token);

        if (!$contract) {
            wp_send_json_error(array('message' => 'Contract not found'));
        }

        // Get consent timestamp
        $consent_timestamp = !empty($_POST['consent_timestamp']) ? sanitize_text_field($_POST['consent_timestamp']) : null;

        // Process signature
        $signer_info = array(
            'name' => sanitize_text_field($_POST['signer_name']),
            'email' => sanitize_email($_POST['signer_email']),
            'consent' => 'yes',
            'consent_timestamp' => $consent_timestamp
        );

        $result = WP_SignFlow_Signature_Handler::process_signature(
            $contract->id,
            $_POST['signature_data'],
            $signer_info
        );

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }
}

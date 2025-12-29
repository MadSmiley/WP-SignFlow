<?php
/**
 * Public API class for external plugins
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Public_API {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('signflow/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_generate_contract'),
            'permission_callback' => array($this, 'check_api_permission')
        ));

        register_rest_route('signflow/v1', '/contract/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_contract'),
            'permission_callback' => array($this, 'check_api_permission')
        ));

        register_rest_route('signflow/v1', '/contract/(?P<id>\d+)/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_contract_status'),
            'permission_callback' => array($this, 'check_api_permission')
        ));

        register_rest_route('signflow/v1', '/contract/(?P<id>\d+)/verify', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_verify_contract'),
            'permission_callback' => array($this, 'check_api_permission')
        ));

        register_rest_route('signflow/v1', '/contract/(?P<id>\d+)/audit', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_audit_trail'),
            'permission_callback' => array($this, 'check_api_permission')
        ));
    }

    /**
     * Check API permission
     */
    public function check_api_permission($request) {
        // Check for API key in header
        $api_key = $request->get_header('X-SignFlow-API-Key');
        $stored_api_key = get_option('signflow_api_key');

        if (empty($stored_api_key)) {
            // If no API key is set, require user to be logged in
            return current_user_can('edit_posts');
        }

        return $api_key === $stored_api_key;
    }

    /**
     * API: Generate contract
     */
    public function api_generate_contract($request) {
        $params = $request->get_json_params();

        // Validate required parameters
        if (empty($params['template_id']) && empty($params['template_slug'])) {
            return new WP_Error('missing_parameter', 'template_id or template_slug is required', array('status' => 400));
        }

        if (empty($params['variables']) || !is_array($params['variables'])) {
            return new WP_Error('missing_parameter', 'variables array is required', array('status' => 400));
        }

        // Get template
        if (!empty($params['template_id'])) {
            $template_id = intval($params['template_id']);
        } else {
            $template = WP_SignFlow_Template_Manager::get_template_by_slug($params['template_slug']);
            if (!$template) {
                return new WP_Error('template_not_found', 'Template not found', array('status' => 404));
            }
            $template_id = $template->id;
        }

        // Generate contract
        $metadata = $params['metadata'] ?? array();
        $result = WP_SignFlow_Contract_Generator::generate_contract($template_id, $params['variables'], $metadata);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * API: Get contract details
     */
    public function api_get_contract($request) {
        $contract_id = intval($request['id']);
        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);

        if (!$contract) {
            return new WP_Error('contract_not_found', 'Contract not found', array('status' => 404));
        }

        // Get signature if exists
        $signature = WP_SignFlow_Signature_Handler::get_signature($contract_id);

        return rest_ensure_response(array(
            'contract' => $contract,
            'signature' => $signature
        ));
    }

    /**
     * API: Get contract status
     */
    public function api_get_contract_status($request) {
        $contract_id = intval($request['id']);
        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);

        if (!$contract) {
            return new WP_Error('contract_not_found', 'Contract not found', array('status' => 404));
        }

        return rest_ensure_response(array(
            'contract_id' => $contract->id,
            'status' => $contract->status,
            'signed_at' => $contract->signed_at,
            'pdf_hash' => $contract->pdf_hash
        ));
    }

    /**
     * API: Verify contract signature
     */
    public function api_verify_contract($request) {
        $contract_id = intval($request['id']);
        $is_valid = WP_SignFlow_Signature_Handler::verify_signature($contract_id);

        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);
        if (!$contract) {
            return new WP_Error('contract_not_found', 'Contract not found', array('status' => 404));
        }

        return rest_ensure_response(array(
            'contract_id' => $contract_id,
            'is_valid' => $is_valid,
            'status' => $contract->status,
            'pdf_hash' => $contract->pdf_hash,
            'signed_at' => $contract->signed_at
        ));
    }

    /**
     * API: Get audit trail
     */
    public function api_get_audit_trail($request) {
        $contract_id = intval($request['id']);
        $audit_trail = WP_SignFlow_Audit_Trail::get_contract_audit($contract_id);

        return rest_ensure_response(array(
            'contract_id' => $contract_id,
            'audit_trail' => $audit_trail
        ));
    }
}

/**
 * Helper functions for external plugins
 */

/**
 * Generate a contract (for use by other plugins)
 *
 * @param int|string $template Template ID or slug
 * @param array $variables Variables to replace in template
 * @param array $metadata Optional metadata
 * @return array|WP_Error Contract data or error
 */
function signflow_generate_contract($template, $variables, $metadata = array()) {
    // Get template ID
    if (is_numeric($template)) {
        $template_id = intval($template);
    } else {
        $template_obj = WP_SignFlow_Template_Manager::get_template_by_slug($template);
        if (!$template_obj) {
            return new WP_Error('template_not_found', 'Template not found');
        }
        $template_id = $template_obj->id;
    }

    return WP_SignFlow_Contract_Generator::generate_contract($template_id, $variables, $metadata);
}

/**
 * Get contract status
 *
 * @param int $contract_id Contract ID
 * @return object|false Contract object or false
 */
function signflow_get_contract($contract_id) {
    return WP_SignFlow_Contract_Generator::get_contract($contract_id);
}

/**
 * Get contract status
 *
 * @param int $contract_id Contract ID
 * @return string|false Contract status or false
 */
function signflow_get_contract_status($contract_id) {
    $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);
    return $contract ? $contract->status : false;
}

/**
 * Verify contract signature
 *
 * @param int $contract_id Contract ID
 * @return bool True if signature is valid
 */
function signflow_verify_signature($contract_id) {
    return WP_SignFlow_Signature_Handler::verify_signature($contract_id);
}

/**
 * Get contract audit trail
 *
 * @param int $contract_id Contract ID
 * @return array Audit trail events
 */
function signflow_get_audit_trail($contract_id) {
    return WP_SignFlow_Audit_Trail::get_contract_audit($contract_id);
}

/**
 * Get signature URL for contract
 *
 * @param string $token Contract token
 * @return string Signature URL
 */
function signflow_get_signature_url($token) {
    return WP_SignFlow_Contract_Generator::get_signature_url($token);
}

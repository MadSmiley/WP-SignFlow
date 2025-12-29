<?php
/**
 * Audit Trail class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Audit_Trail {

    /**
     * Log an event
     */
    public static function log_event($contract_id, $event_type, $event_data = array()) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('audit');

        $ip_address = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        return $wpdb->insert(
            $table,
            array(
                'contract_id' => $contract_id,
                'event_type' => sanitize_text_field($event_type),
                'event_data' => maybe_serialize($event_data),
                'ip_address' => $ip_address,
                'user_agent' => substr($user_agent, 0, 500)
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Get audit trail for contract
     */
    public static function get_contract_audit($contract_id) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('audit');

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE contract_id = %d ORDER BY created_at DESC",
            $contract_id
        ));

        foreach ($results as $result) {
            $result->event_data = maybe_unserialize($result->event_data);
        }

        return $results;
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /**
     * Export audit trail for contract
     */
    public static function export_audit_trail($contract_id, $format = 'json') {
        $audit = self::get_contract_audit($contract_id);

        if ($format === 'json') {
            return json_encode($audit, JSON_PRETTY_PRINT);
        }

        if ($format === 'csv') {
            $csv = "Event Type,Created At,IP Address,Event Data\n";
            foreach ($audit as $event) {
                $csv .= sprintf(
                    '"%s","%s","%s","%s"' . "\n",
                    $event->event_type,
                    $event->created_at,
                    $event->ip_address,
                    json_encode($event->event_data)
                );
            }
            return $csv;
        }

        return $audit;
    }
}

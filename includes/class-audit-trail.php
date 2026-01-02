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

        // Get previous log entry for this contract to calculate hash
        $previous_hash = null;
        $previous_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE contract_id = %d ORDER BY id DESC LIMIT 1",
            $contract_id
        ));

        if ($previous_log) {
            // Calculate hash of previous log entry
            $previous_hash = self::calculate_log_hash($previous_log);
        }

        return $wpdb->insert(
            $table,
            array(
                'contract_id' => $contract_id,
                'event_type' => sanitize_text_field($event_type),
                'event_data' => maybe_serialize($event_data),
                'previous_hash' => $previous_hash,
                'ip_address' => $ip_address,
                'user_agent' => substr($user_agent, 0, 500)
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Calculate hash of a log entry for blockchain-like integrity
     */
    private static function calculate_log_hash($log) {
        // Create a deterministic string from the log entry
        $data = array(
            'id' => $log->id,
            'contract_id' => $log->contract_id,
            'event_type' => $log->event_type,
            'event_data' => $log->event_data,
            'previous_hash' => $log->previous_hash ?? '',
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at
        );

        return hash('sha256', json_encode($data));
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
     * Verify audit trail integrity
     */
    public static function verify_audit_chain($contract_id) {
        global $wpdb;
        $table = WP_SignFlow_Database::get_table('audit');

        // Get all logs for this contract in chronological order
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE contract_id = %d ORDER BY id ASC",
            $contract_id
        ));

        if (empty($logs)) {
            return array(
                'valid' => true,
                'message' => 'No audit logs found'
            );
        }

        $previous_log = null;
        foreach ($logs as $index => $log) {
            // First log should have no previous_hash
            if ($index === 0) {
                if ($log->previous_hash !== null) {
                    return array(
                        'valid' => false,
                        'message' => 'First log entry should not have a previous_hash',
                        'failed_at_id' => $log->id
                    );
                }
            } else {
                // Calculate what the previous_hash should be
                $expected_hash = self::calculate_log_hash($previous_log);

                if ($log->previous_hash !== $expected_hash) {
                    return array(
                        'valid' => false,
                        'message' => 'Hash chain broken - log entry has been tampered',
                        'failed_at_id' => $log->id,
                        'expected_hash' => $expected_hash,
                        'actual_hash' => $log->previous_hash
                    );
                }
            }

            $previous_log = $log;
        }

        return array(
            'valid' => true,
            'message' => 'Audit trail integrity verified',
            'verified_entries' => count($logs)
        );
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

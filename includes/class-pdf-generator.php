<?php
/**
 * PDF Generator class
 * Uses FPDF (PHP pure, no system dependencies)
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_PDF_Generator {

    /**
     * Generate PDF from HTML content
     */
    public static function generate_pdf($contract_id, $html_content) {
        // Check if FPDF is available
        if (self::is_fpdf_available()) {
            $result = self::generate_with_fpdf($contract_id, $html_content);
            if (!is_wp_error($result)) {
                return $result;
            }
        }

        // Fallback to printable HTML
        return self::save_as_printable_html($contract_id, $html_content);
    }

    /**
     * Check if FPDF is available
     */
    private static function is_fpdf_available() {
        // Check multiple possible paths
        $paths = [
            WP_SIGNFLOW_PLUGIN_DIR . 'lib/fpdf/1.86/fpdf.php',
            WP_SIGNFLOW_PLUGIN_DIR . 'lib/fpdf.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                // Define constant with the path found
                if (!defined('FPDF_PATH')) {
                    define('FPDF_PATH', $path);
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Generate PDF using FPDF
     */
    private static function generate_with_fpdf($contract_id, $html_content) {
        try {
            // Load FPDF wrapper
            require_once WP_SIGNFLOW_PLUGIN_DIR . 'lib/class-fpdf-wrapper.php';

            // Create PDF
            $pdf = new WP_SignFlow_FPDF_Wrapper();
            $pdf->SetTitle('Contract #' . $contract_id);
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetCreator('WP SignFlow');

            // Set margins
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 15);

            // Add page
            $pdf->AddPage();

            // Add header
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Document Contractuel', 0, 1, 'C');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, 'Reference : #' . $contract_id . ' - Genere le ' . date('d/m/Y'), 0, 1, 'C');
            $pdf->Ln(5);

            // Add content
            $pdf->add_html_content($html_content);

            // Add footer
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->SetTextColor(128, 128, 128);
            $pdf->Cell(0, 5, 'Document genere par WP SignFlow - ' . get_bloginfo('name'), 0, 0, 'C');

            // Save PDF
            $filename = 'contract_' . $contract_id . '_' . time() . '.pdf';
            $upload_dir = wp_upload_dir();
            $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
            $filepath = $signflow_dir . '/' . $filename;

            $pdf->Output('F', $filepath);

            // Update contract with PDF path
            global $wpdb;
            $table = WP_SignFlow_Database::get_table('contracts');
            $wpdb->update(
                $table,
                array('pdf_path' => $filename),
                array('id' => $contract_id),
                array('%s'),
                array('%d')
            );

            return $filename;

        } catch (Exception $e) {
            return new WP_Error('pdf_generation_failed', $e->getMessage());
        }
    }

    /**
     * Save as printable HTML (fallback)
     */
    private static function save_as_printable_html($contract_id, $html_content) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contract #' . $contract_id . '</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
            margin: 0;
            padding: 20px;
            max-width: 800px;
        }
        h1 { font-size: 18pt; margin-bottom: 10px; color: #333; }
        h2 { font-size: 14pt; margin-top: 15px; margin-bottom: 8px; color: #555; }
        h3 { font-size: 12pt; margin-top: 12px; margin-bottom: 6px; color: #666; }
        p { margin: 8px 0; }
        strong, b { font-weight: bold; }
        em, i { font-style: italic; }
        ul { margin: 10px 0; padding-left: 20px; }
        li { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table th, table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        table th { background-color: #f5f5f5; font-weight: bold; }
        @media print {
            body { margin: 0; padding: 15mm; }
            .no-print { display: none; }
        }
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }
        .print-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 9pt;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="print-header">
        <h2>Document Contractuel</h2>
        <p style="font-size: 10pt; color: #666;">Référence: #' . $contract_id . ' - Généré le ' . date('d/m/Y à H:i') . '</p>
    </div>

    ' . $html_content . '

    <div class="print-footer">
        <p>Document généré par WP SignFlow - ' . get_bloginfo('name') . '</p>
        <p class="no-print" style="margin-top: 15px; padding: 10px; background: #fffacd; border: 1px solid #ffd700; border-radius: 4px;">
            <strong>⚠️ FPDF manquant :</strong> Pour générer automatiquement des PDFs, téléchargez FPDF depuis
            <a href="http://www.fpdf.org/" target="_blank">fpdf.org</a> et placez fpdf.php dans le dossier lib/ du plugin.<br>
            En attendant, utilisez Ctrl+P → Enregistrer en PDF pour obtenir un PDF.
        </p>
    </div>
</body>
</html>';

        $filename = 'contract_' . $contract_id . '_' . time() . '.html';
        $upload_dir = wp_upload_dir();
        $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
        $filepath = $signflow_dir . '/' . $filename;

        if (file_put_contents($filepath, $html) !== false) {
            // Update contract with file path
            global $wpdb;
            $table = WP_SignFlow_Database::get_table('contracts');
            $wpdb->update(
                $table,
                array('pdf_path' => $filename),
                array('id' => $contract_id),
                array('%s'),
                array('%d')
            );

            return $filename;
        }

        return new WP_Error('save_failed', 'Failed to save document');
    }

    /**
     * Add signature to existing PDF/HTML
     */
    public static function add_signature_to_pdf($contract_id, $signature_image_path) {
        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);
        if (!$contract || !$contract->pdf_path) {
            return new WP_Error('invalid_contract', 'Contract or document not found');
        }

        $upload_dir = wp_upload_dir();
        $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
        $file_path = $signflow_dir . '/' . $contract->pdf_path;

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Document file not found');
        }

        // Get signature info
        $signature = WP_SignFlow_Signature_Handler::get_signature($contract_id);
        $signer_name = $signature ? $signature->signer_name : '';
        $signed_date = current_time('mysql');

        return self::add_signature_to_fpdf($contract, $signature_image_path, $signer_name, $signed_date);
    }

    /**
     * Add signature to PDF using FPDF
     */
    private static function add_signature_to_fpdf($contract, $signature_image_path, $signer_name, $signed_date) {
        try {
            require_once WP_SIGNFLOW_PLUGIN_DIR . 'lib/class-fpdf-wrapper.php';

            $contract_id = $contract->id;
            $html_content = $contract->contract_data['content'];

            // Create new PDF with signature
            $pdf = new WP_SignFlow_FPDF_Wrapper();
            $pdf->SetTitle('Contract #' . $contract_id . ' - Signed');
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetCreator('WP SignFlow');
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();

            // Header
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Document Contractuel Signe', 0, 1, 'C');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 5, 'Reference : #' . $contract_id, 0, 1, 'C');
            $pdf->Ln(5);

            // Content
            $pdf->add_html_content($html_content);

            // Signature section
            $pdf->add_signature_section($signature_image_path, $signer_name, $signed_date);

            // Save
            $filename = 'contract_' . $contract_id . '_signed_' . time() . '.pdf';
            $upload_dir = wp_upload_dir();
            $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
            $filepath = $signflow_dir . '/' . $filename;

            $pdf->Output('F', $filepath);

            // Calculate hash
            $hash = hash_file('sha256', $filepath);

            // Update contract
            global $wpdb;
            $table = WP_SignFlow_Database::get_table('contracts');
            $wpdb->update(
                $table,
                array(
                    'pdf_path' => $filename,
                    'pdf_hash' => $hash,
                    'status' => 'signed',
                    'signed_at' => $signed_date
                ),
                array('id' => $contract_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );

            return $filename;

        } catch (Exception $e) {
            return new WP_Error('signature_add_failed', $e->getMessage());
        }
    }


    /**
     * Get file path
     */
    public static function get_pdf_path($filename) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/wp-signflow/' . $filename;
    }

    /**
     * Calculate file hash
     */
    public static function calculate_hash($filepath) {
        if (!file_exists($filepath)) {
            return false;
        }
        return hash_file('sha256', $filepath);
    }
}

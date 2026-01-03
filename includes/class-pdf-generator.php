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

            // Add content only (no header/footer)
            $pdf->add_html_content($html_content);

            // Save PDF
            $filename = 'contract_' . $contract_id . '_' . time() . '.pdf';
            $upload_dir = wp_upload_dir();
            $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
            $filepath = $signflow_dir . '/' . $filename;

            $pdf->Output('F', $filepath);

            // Calculate hash of original document
            $original_hash = hash_file('sha256', $filepath);

            // Update contract with original PDF only (signed fields remain empty)
            global $wpdb;
            $table = WP_SignFlow_Database::get_table('contracts');
            $wpdb->update(
                $table,
                array(
                    'original_pdf_path' => $filename,
                    'original_hash' => $original_hash
                ),
                array('id' => $contract_id),
                array('%s', '%s'),
                array('%d')
            );


            return $filename;

        } catch (Exception $e) {
            return new WP_Error('pdf_generation_failed', $e->getMessage());
        }
    }
    

    /**
     * Add signature to existing PDF/HTML
     * @param string $signature_data Base64 encoded image data
     */
    public static function add_signature_to_pdf($contract_id, $signature_data) {
        $contract = WP_SignFlow_Contract_Generator::get_contract($contract_id);
        if (!$contract || !$contract->original_pdf_path) {
            return new WP_Error('invalid_contract', 'Contract or document not found');
        }

        $upload_dir = wp_upload_dir();
        $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
        $file_path = $signflow_dir . '/' . $contract->original_pdf_path;

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Document file not found');
        }

        // Get signature info
        $signature = WP_SignFlow_Signature_Handler::get_signature($contract_id);
        $signer_name = $signature ? $signature->signer_name : '';
        $signed_date = current_time('mysql');

        return self::add_signature_to_fpdf($contract, $signature_data, $signer_name, $signed_date);
    }

    /**
     * Add signature to PDF using FPDF
     * @param string $signature_data Base64 encoded image data or file path
     */
    private static function add_signature_to_fpdf($contract, $signature_data, $signer_name, $signed_date) {
        try {
            // Ensure FPDF is loaded
            if (!self::is_fpdf_available()) {
                return new WP_Error('fpdf_not_available', 'FPDF library not available');
            }

            // Load FPDF if not already loaded
            if (!class_exists('FPDF') && defined('FPDF_PATH')) {
                require_once FPDF_PATH;
            }

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

            // Content only (no header)
            $pdf->add_html_content($html_content);

            // Signature section - pass base64 data directly
            $pdf->add_signature_section($signature_data, $signer_name, $signed_date);

            // Save
            $filename = 'contract_' . $contract_id . '_signed_' . time() . '.pdf';
            $upload_dir = wp_upload_dir();
            $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
            $filepath = $signflow_dir . '/' . $filename;

            $pdf->Output('F', $filepath);

            // Calculate hash
            $hash = hash_file('sha256', $filepath);

            // Update contract with signed PDF
            global $wpdb;
            $table = WP_SignFlow_Database::get_table('contracts');
            $wpdb->update(
                $table,
                array(
                    'signed_pdf_path' => $filename,
                    'signed_pdf_hash' => $hash,
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

    /**
     * Generate certificate PDF
     */
    public static function generate_certificate($contract_id, $original_hash, $signed_hash, $signer_name, $signer_email, $signed_date) {
        if (!self::is_fpdf_available()) {
            return new WP_Error('fpdf_not_available', 'FPDF library not available');
        }

        try {
            // Load FPDF if not already loaded
            if (!class_exists('FPDF') && defined('FPDF_PATH')) {
                require_once FPDF_PATH;
            }

            require_once WP_SIGNFLOW_PLUGIN_DIR . 'lib/class-fpdf-wrapper.php';

            // Get certificate language from settings
            $language = get_option('signflow_certificate_language', 'en');

            // Get translations
            $translations = self::get_certificate_translations($language);

            $pdf = new WP_SignFlow_FPDF_Wrapper();
            $pdf->SetTitle($translations['title_meta'] . ' - Contract #' . $contract_id);
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetCreator('WP SignFlow');
            $pdf->SetMargins(20, 20, 20);
            $pdf->SetAutoPageBreak(true, 20);
            $pdf->AddPage();

            // Title
            $pdf->SetFont('Arial', 'B', 20);
            $pdf->SetTextColor(34, 113, 177);
            $pdf->Cell(0, 15, $pdf->decode_text($translations['title']), 0, 1, 'C');
            $pdf->Ln(10);

            // Contract ID
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 8, $pdf->decode_text($translations['contract_ref']) . ' #' . $contract_id, 0, 1);
            $pdf->Ln(5);

            // Signer Information
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, $pdf->decode_text($translations['signer_info']), 0, 1);
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(50, 7, $pdf->decode_text($translations['name']) . ':', 0, 0);
            $pdf->Cell(0, 7, $pdf->decode_text($signer_name), 0, 1);
            $pdf->Cell(50, 7, $pdf->decode_text($translations['email']) . ':', 0, 0);
            $pdf->Cell(0, 7, $signer_email, 0, 1);
            $pdf->Cell(50, 7, $pdf->decode_text($translations['date']) . ':', 0, 0);
            $pdf->Cell(0, 7, gmdate('d/m/Y \a\t H:i:s', strtotime($signed_date)) . ' UTC', 0, 1);
            $pdf->Cell(50, 7, $pdf->decode_text($translations['signature_mode']) . ':', 0, 0);
            $pdf->MultiCell(0, 7, $pdf->decode_text($translations['signature_mode_value']));
            $pdf->Ln(10);

            // Document Hash
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, $pdf->decode_text($translations['doc_integrity']), 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(60, 7, $pdf->decode_text($translations['hash_algo']) . ':', 0, 0);
            $pdf->Cell(0, 7, 'SHA-256', 0, 1);

            $pdf->Cell(60, 7, $pdf->decode_text($translations['original_hash']) . ':', 0, 1);
            $pdf->SetFont('Courier', '', 8);
            $pdf->MultiCell(0, 5, $original_hash);

            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(60, 7, $pdf->decode_text($translations['signed_hash']) . ':', 0, 1);
            $pdf->SetFont('Courier', '', 8);
            $pdf->MultiCell(0, 5, $signed_hash);
            $pdf->Ln(10);

            // Certification Statement
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, $pdf->decode_text($translations['certification']), 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 6,
                $pdf->decode_text(sprintf($translations['certification_text'],
                    gmdate('d/m/Y \a\t H:i:s', strtotime($signed_date)) . ' UTC'
                ))
            );

            // Save certificate
            $filename = 'certificate_' . $contract_id . '_' . time() . '.pdf';
            $upload_dir = wp_upload_dir();
            $signflow_dir = $upload_dir['basedir'] . '/wp-signflow';
            $filepath = $signflow_dir . '/' . $filename;

            $pdf->Output('F', $filepath);

            return $filename;

        } catch (Exception $e) {
            return new WP_Error('certificate_generation_failed', $e->getMessage());
        }
    }

    /**
     * Get certificate translations
     */
    private static function get_certificate_translations($language) {
        $translations = array(
            'en' => array(
                'title_meta' => 'Certificate',
                'title' => 'Certificate of Electronic Signature',
                'contract_ref' => 'Contract Reference:',
                'signer_info' => 'Signer Information',
                'name' => 'Name',
                'email' => 'Email',
                'date' => 'Date',
                'signature_mode' => 'Signature Mode',
                'signature_mode_value' => 'Handwritten electronic signature on-site in the presence of an agent',
                'doc_integrity' => 'Document Integrity',
                'hash_algo' => 'Hash Algorithm',
                'original_hash' => 'Original Document Hash',
                'signed_hash' => 'Signed Document Hash',
                'certification' => 'Certification',
                'certification_text' => 'This certificate attests that the referenced contract was electronically signed on %s by the person identified above. The signer explicitly consented to this electronic signature after reviewing the contract in the presence of an agent.

The document\'s integrity is guaranteed by the SHA-256 hashes listed above.'
            ),
            'fr' => array(
                'title_meta' => 'Certificat',
                'title' => 'Certificat de Signature Electronique',
                'contract_ref' => 'Reference du Contrat :',
                'signer_info' => 'Informations du Signataire',
                'name' => 'Nom',
                'email' => 'Email',
                'date' => 'Date',
                'signature_mode' => 'Mode de Signature',
                'signature_mode_value' => 'Signature electronique manuscrite sur place en presence d\'un agent',
                'doc_integrity' => 'Integrite du Document',
                'hash_algo' => 'Algorithme de Hachage',
                'original_hash' => 'Hash du Document Original',
                'signed_hash' => 'Hash du Document Signe',
                'certification' => 'Certification',
                'certification_text' => "Ce certificat atteste que le contrat reference a ete signe electroniquement le %s par la personne identifiee ci-dessus. Le signataire a expressement consenti a cette signature electronique apres avoir examine le contrat en presence d\'un agent.

L\'integrite du document est garantie par les hash SHA-256 indiques ci-dessus."
            )
        );

        return isset($translations[$language]) ? $translations[$language] : $translations['en'];
    }
}

<?php
/**
 * FPDF Wrapper for WP SignFlow
 * Converts HTML to PDF using FPDF
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load FPDF if available (path set by PDF generator)
if (defined('FPDF_PATH') && file_exists(FPDF_PATH)) {
    require_once FPDF_PATH;
}

class WP_SignFlow_FPDF_Wrapper extends FPDF {

    private $current_font_size = 11;
    private $line_height = 6;

    /**
     * Convert HTML to PDF
     */
    public function add_html_content($html) {
        // Strip tags and convert to text with basic formatting
        $html = $this->prepare_html($html);

        // Process content
        $this->SetFont('Arial', '', $this->current_font_size);
        $this->SetTextColor(0, 0, 0);

        // Parse and add content
        $this->parse_html($html);
    }

    /**
     * Prepare HTML
     */
    private function prepare_html($html) {
        // Convert line breaks
        $html = str_replace(['<br>', '<br/>', '<br />'], "\n", $html);

        // Convert paragraphs
        $html = preg_replace('/<p[^>]*>/', "\n", $html);
        $html = str_replace('</p>', "\n", $html);

        // Convert headings
        $html = preg_replace('/<h1[^>]*>(.*?)<\/h1>/is', "\n\n###H1###$1###/H1###\n\n", $html);
        $html = preg_replace('/<h2[^>]*>(.*?)<\/h2>/is', "\n\n###H2###$1###/H2###\n\n", $html);
        $html = preg_replace('/<h3[^>]*>(.*?)<\/h3>/is', "\n\n###H3###$1###/H3###\n\n", $html);

        // Convert lists
        $html = preg_replace('/<li[^>]*>/', "\n• ", $html);
        $html = str_replace('</li>', '', $html);
        $html = preg_replace('/<\/?ul[^>]*>/', "\n", $html);
        $html = preg_replace('/<\/?ol[^>]*>/', "\n", $html);

        // Convert strong/bold
        $html = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', "###BOLD###$1###/BOLD###", $html);
        $html = preg_replace('/<b[^>]*>(.*?)<\/b>/is', "###BOLD###$1###/BOLD###", $html);

        // Remove remaining HTML tags
        $html = strip_tags($html);

        // Clean up whitespace
        $html = preg_replace('/\n\s*\n\s*\n/', "\n\n", $html);

        return trim($html);
    }

    /**
     * Parse HTML content
     */
    private function parse_html($content) {
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                $this->Ln($this->line_height / 2);
                continue;
            }

            // Handle H1
            if (preg_match('/###H1###(.*?)###\/H1###/', $line, $matches)) {
                $this->Ln($this->line_height);
                $this->SetFont('Arial', 'B', 16);
                $this->MultiCell(0, 8, $this->decode_text($matches[1]));
                $this->SetFont('Arial', '', $this->current_font_size);
                $this->Ln($this->line_height / 2);
                continue;
            }

            // Handle H2
            if (preg_match('/###H2###(.*?)###\/H2###/', $line, $matches)) {
                $this->Ln($this->line_height / 2);
                $this->SetFont('Arial', 'B', 14);
                $this->MultiCell(0, 7, $this->decode_text($matches[1]));
                $this->SetFont('Arial', '', $this->current_font_size);
                $this->Ln($this->line_height / 3);
                continue;
            }

            // Handle H3
            if (preg_match('/###H3###(.*?)###\/H3###/', $line, $matches)) {
                $this->Ln($this->line_height / 3);
                $this->SetFont('Arial', 'B', 12);
                $this->MultiCell(0, 6, $this->decode_text($matches[1]));
                $this->SetFont('Arial', '', $this->current_font_size);
                continue;
            }

            // Handle bold text
            if (strpos($line, '###BOLD###') !== false) {
                $parts = preg_split('/(###BOLD###|###\/BOLD###)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE);
                $is_bold = false;

                foreach ($parts as $part) {
                    if ($part === '###BOLD###') {
                        $is_bold = true;
                        continue;
                    }
                    if ($part === '###/BOLD###') {
                        $is_bold = false;
                        continue;
                    }

                    if (!empty($part)) {
                        $this->SetFont('Arial', $is_bold ? 'B' : '', $this->current_font_size);
                        $this->Write($this->line_height, $this->decode_text($part));
                    }
                }
                $this->Ln($this->line_height);
                $this->SetFont('Arial', '', $this->current_font_size);
                continue;
            }

            // Normal text
            $this->MultiCell(0, $this->line_height, $this->decode_text($line));
        }
    }

    /**
     * Decode HTML entities
     */
    public function decode_text($text) {
        // Handle null or empty input
        if ($text === null || $text === '') {
            return '';
        }

        $text = (string) $text;
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Convert UTF-8 to ISO-8859-1 for FPDF
        $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        return $text;
    }

    /**
     * Add signature image
     * @param string $signature_data Base64 encoded image data or file path
     */
    public function add_signature_section($signature_data, $signer_name, $signed_date) {
        $this->Ln(10);

        // Add separator line
        $this->SetDrawColor(0, 0, 0);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        // Title
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, 'Signature du Document', 0, 1);
        $this->Ln(3);

        // Signer info
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, 'Nom du signataire : ' . $this->decode_text($signer_name), 0, 1);
        $this->Cell(0, 6, 'Date de signature : ' . $signed_date, 0, 1);
        $this->Ln(5);

        // Signature image - only accept base64 data
        if (!preg_match('/^data:image\/(png|jpg|jpeg);base64,/', $signature_data, $matches)) {
            throw new Exception('Invalid signature data format - must be base64 encoded image');
        }

        $image_type = $matches[1] === 'jpg' ? 'jpeg' : $matches[1];
        $image_data = preg_replace('/^data:image\/(png|jpg|jpeg);base64,/', '', $signature_data);
        $image_data = base64_decode($image_data);

        if ($image_data === false || strlen($image_data) === 0) {
            throw new Exception('Failed to decode signature image data');
        }

        // Create temporary file with proper extension in system temp directory
        $temp_file = sys_get_temp_dir() . '/wpsfsg_' . uniqid() . '.' . $image_type;
        $write_result = file_put_contents($temp_file, $image_data);

        if ($write_result === false) {
            throw new Exception('Failed to write signature image to temporary file');
        }

        try {
            $this->Cell(0, 6, 'Signature : ', 0, 1);
            $this->Ln(2);

            // Add image (max width 80mm)
            $this->Image($temp_file, 10, $this->GetY(), 80, 0, strtoupper($image_type));
            $this->Ln(35); // Space for signature
        } catch (Exception $e) {
            // Clean up temp file before re-throwing
            if (file_exists($temp_file)) {
                @unlink($temp_file);
            }
            throw new Exception('Failed to add signature image to PDF: ' . $e->getMessage());
        }

        // Delete temporary file immediately after successful use
        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }
    }
}

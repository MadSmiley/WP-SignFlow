<?php
/**
 * Simple PDF Generator
 * Lightweight PDF generation without external dependencies
 * Based on FPDF concepts but simplified for our needs
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Simple_PDF {

    private $content = '';
    private $margin = 20;
    private $width = 210; // A4 width in mm
    private $height = 297; // A4 height in mm

    /**
     * Add HTML content (simplified)
     */
    public function add_html($html) {
        // Strip HTML tags and keep text
        $text = strip_tags($html, '<br><p><h1><h2><h3><strong><b><em><i><ul><li>');

        // Convert HTML to simple text with formatting
        $text = str_replace(['<br>', '<br/>', '<br />'], "\n", $text);
        $text = preg_replace('/<p[^>]*>/', "\n", $text);
        $text = str_replace('</p>', "\n", $text);
        $text = preg_replace('/<h[1-3][^>]*>/', "\n\n", $text);
        $text = str_replace(['</h1>', '</h2>', '</h3>'], "\n\n", $text);
        $text = preg_replace('/<li[^>]*>/', "\n• ", $text);
        $text = strip_tags($text);

        // Clean up whitespace
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);

        $this->content .= $text;
    }

    /**
     * Generate PDF and save to file
     */
    public function save($filepath) {
        // For now, we'll use a more compatible approach:
        // Generate an HTML file that can be printed to PDF
        $html = $this->generate_printable_html();

        // Try to use wkhtmltopdf if available
        if ($this->try_wkhtmltopdf($html, $filepath)) {
            return true;
        }

        // Fallback: Save as print-friendly HTML
        return $this->save_as_html($html, $filepath);
    }

    /**
     * Try to use wkhtmltopdf (if installed on server)
     */
    private function try_wkhtmltopdf($html, $filepath) {
        // Check if wkhtmltopdf is available
        $wkhtmltopdf_path = $this->find_wkhtmltopdf();
        if (!$wkhtmltopdf_path) {
            return false;
        }

        // Create temp HTML file
        $temp_html = sys_get_temp_dir() . '/signflow_temp_' . time() . '.html';
        file_put_contents($temp_html, $html);

        // Convert to PDF
        $command = sprintf(
            '%s --quiet --page-size A4 --margin-top 15 --margin-bottom 15 --margin-left 15 --margin-right 15 %s %s 2>&1',
            escapeshellarg($wkhtmltopdf_path),
            escapeshellarg($temp_html),
            escapeshellarg($filepath)
        );

        exec($command, $output, $return_var);

        // Clean up temp file
        @unlink($temp_html);

        return ($return_var === 0 && file_exists($filepath));
    }

    /**
     * Find wkhtmltopdf binary
     */
    private function find_wkhtmltopdf() {
        $possible_paths = [
            '/usr/local/bin/wkhtmltopdf',
            '/usr/bin/wkhtmltopdf',
            'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
            'wkhtmltopdf' // Try PATH
        ];

        foreach ($possible_paths as $path) {
            if (@is_executable($path)) {
                return $path;
            }
        }

        // Try to find in PATH
        exec('which wkhtmltopdf 2>/dev/null', $output);
        if (!empty($output[0]) && is_executable($output[0])) {
            return $output[0];
        }

        return false;
    }

    /**
     * Generate print-friendly HTML
     */
    private function generate_printable_html() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contract</title>
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
        }
        h1 { font-size: 18pt; margin-bottom: 10px; }
        h2 { font-size: 14pt; margin-top: 15px; margin-bottom: 8px; }
        h3 { font-size: 12pt; margin-top: 12px; margin-bottom: 6px; }
        p { margin: 8px 0; }
        strong, b { font-weight: bold; }
        em, i { font-style: italic; }
        ul { margin: 10px 0; padding-left: 20px; }
        li { margin: 5px 0; }
        @media print {
            body { margin: 0; padding: 15mm; }
        }
    </style>
</head>
<body>
' . nl2br(htmlspecialchars($this->content, ENT_QUOTES, 'UTF-8')) . '
</body>
</html>';
    }

    /**
     * Save as HTML (fallback)
     */
    private function save_as_html($html, $filepath) {
        // Change extension to .html if it's .pdf
        if (substr($filepath, -4) === '.pdf') {
            $filepath = substr($filepath, 0, -4) . '.html';
        }

        return file_put_contents($filepath, $html) !== false;
    }

    /**
     * Add signature image
     */
    public function add_signature($signature_image_path, $signer_name = '', $signed_date = '') {
        $this->content .= "\n\n" . str_repeat('_', 50) . "\n";
        $this->content .= "SIGNATURE\n\n";

        if ($signer_name) {
            $this->content .= "Nom: " . $signer_name . "\n";
        }

        if ($signed_date) {
            $this->content .= "Date: " . $signed_date . "\n";
        }

        $this->content .= "\n[Signature capturée électroniquement]\n";
        $this->content .= "Fichier: " . basename($signature_image_path) . "\n";
    }
}

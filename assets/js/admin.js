/**
 * Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Auto-detect variables in template editor
        if ($('#template_content').length) {
            $('#template_content').on('input', function() {
                detectVariables();
            });
        }

        // Storage type toggle
        $('input[name="signflow_storage_type"]').on('change', function() {
            toggleStorageFields();
        });
        toggleStorageFields();

        // Copy to clipboard for signature URLs
        $('.copy-signature-url').on('click', function(e) {
            e.preventDefault();
            const url = $(this).data('url');
            copyToClipboard(url);
            $(this).text('Copied!').addClass('copied');
            setTimeout(() => {
                $(this).text('Copy').removeClass('copied');
            }, 2000);
        });
    });

    /**
     * Detect variables in template content
     */
    function detectVariables() {
        const content = $('#template_content').val();
        const regex = /\{\{([a-zA-Z0-9_]+)\}\}/g;
        const variables = [];
        let match;

        while ((match = regex.exec(content)) !== null) {
            if (variables.indexOf(match[1]) === -1) {
                variables.push(match[1]);
            }
        }

        // Display detected variables
        if (variables.length > 0) {
            let html = '<div class="signflow-info-box">';
            html += '<h3>Detected Variables</h3>';
            html += '<p>The following variables will be replaced when generating contracts:</p>';
            html += '<div>';
            variables.forEach(function(variable) {
                html += '<code class="signflow-variable-tag">{{' + variable + '}}</code> ';
            });
            html += '</div></div>';

            $('#variables-preview').remove();
            $(html).attr('id', 'variables-preview').insertAfter('.form-table');
        } else {
            $('#variables-preview').remove();
        }
    }

    /**
     * Toggle storage configuration fields
     */
    function toggleStorageFields() {
        const storageType = $('input[name="signflow_storage_type"]:checked').val();
        const gcsFields = $('input[name="signflow_gcs_bucket"], textarea[name="signflow_gcs_credentials"]').closest('tr');

        if (storageType === 'google_cloud') {
            gcsFields.show();
        } else {
            gcsFields.hide();
        }
    }

    /**
     * Copy to clipboard
     */
    function copyToClipboard(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }

})(jQuery);

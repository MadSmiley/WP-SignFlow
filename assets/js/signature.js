/**
 * Signature Pad Handler
 */

(function($) {
    'use strict';

    let signaturePad;

    $(document).ready(function() {
        initSignaturePad();
        initForm();
    });

    /**
     * Initialize signature pad
     */
    function initSignaturePad() {
        const canvas = document.getElementById('signature-pad');
        if (!canvas) return;

        // Set canvas size
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            canvas.style.width = rect.width + 'px';
            canvas.style.height = rect.height + 'px';
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // Initialize SignaturePad
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)',
            minWidth: 1,
            maxWidth: 3,
            throttle: 0,
            velocityFilterWeight: 0.7
        });

        // Clear button
        $('#clear-signature').on('click', function(e) {
            e.preventDefault();
            signaturePad.clear();
        });
    }

    /**
     * Initialize form
     */
    function initForm() {
        const form = $('#signature-form');
        const submitBtn = $('#submit-btn');
        const successMessage = $('#success-message');
        const errorMessage = $('#error-message');

        // Store original button text
        submitBtn.data('original-text', submitBtn.text());

        // Track consent checkbox timestamp
        $('#consent').on('change', function() {
            if ($(this).is(':checked')) {
                const timestamp = new Date().toISOString();
                $('#consent-timestamp').val(timestamp);
            } else {
                // Clear timestamp if unchecked
                $('#consent-timestamp').val('');
            }
        });

        // Track signature completion timestamp
        const canvas = document.getElementById('signature-pad');
        if (canvas && signaturePad) {
            signaturePad.addEventListener('endStroke', function() {
                const timestamp = new Date().toISOString();
                $('#signature-timestamp').val(timestamp);
            });
        }

        form.on('submit', function(e) {
            e.preventDefault();

            // Hide messages
            successMessage.hide();
            errorMessage.hide();

            // Validate signature
            if (signaturePad.isEmpty()) {
                showError(signflowData.i18n.error_signature_required);
                return;
            }

            // Validate consent
            if (!$('#consent').is(':checked')) {
                showError(signflowData.i18n.error_consent_required);
                return;
            }

            // Get signature data
            const signatureData = signaturePad.toDataURL('image/png');
            $('#signature-data').val(signatureData);

            // Prepare form data
            const formData = new FormData(form[0]);

            // Disable submit button
            submitBtn.prop('disabled', true).text('Signing...');

            // Submit via AJAX
            $.ajax({
                url: signflowData.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showSuccess('Contract signed successfully! You can close this page.');
                        form.hide();

                        // Optionally redirect after 3 seconds
                        setTimeout(function() {
                            window.location.href = '/';
                        }, 3000);
                    } else {
                        showError(response.data.message || signflowData.i18n.error_general);
                        submitBtn.prop('disabled', false);
                        submitBtn.text(submitBtn.data('original-text'));
                    }
                },
                error: function(xhr) {
                    showError(signflowData.i18n.error_general);
                    submitBtn.prop('disabled', false);
                    submitBtn.text(submitBtn.data('original-text'));
                }
            });
        });

        function showError(message) {
            errorMessage.text(message).show();
            $('html, body').animate({ scrollTop: errorMessage.offset().top - 100 }, 500);
        }

        function showSuccess(message) {
            successMessage.find('strong').next().text(message);
            successMessage.show();
            $('html, body').animate({ scrollTop: 0 }, 500);
        }
    }

})(jQuery);

<?php
/**
 * Translations helper class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_SignFlow_Translations {

    /**
     * Get translations for signature page
     */
    public static function get_signature_page_translations($language = 'en') {
        $translations = array(
            'en' => array(
                'page_title' => 'Electronic Signature',
                'contract_preview' => 'Contract Preview',
                'signature_section' => 'Signature',
                'signer_name_label' => 'Full Name',
                'signer_name_placeholder' => 'Enter your full name',
                'signer_email_label' => 'Email Address',
                'signer_email_placeholder' => 'Enter your email address',
                'draw_signature_label' => 'Draw your signature below',
                'clear_button' => 'Clear',
                'consent_label' => 'I have read and agree to the terms of this contract',
                'submit_button' => 'Sign Contract',
                'success_title' => 'Contract Signed Successfully!',
                'success_message' => 'Your contract has been signed and securely stored. You will receive a confirmation email shortly.',
                'error_name_required' => 'Please enter your full name',
                'error_email_required' => 'Please enter a valid email address',
                'error_signature_required' => 'Please draw your signature',
                'error_consent_required' => 'You must consent to sign the contract',
                'error_general' => 'An error occurred. Please try again.',
                'already_signed_title' => 'Contract Already Signed',
                'already_signed_message' => 'This contract has already been signed.',
                'expired_title' => 'Contract Expired',
                'expired_message' => 'This contract has expired and can no longer be signed.',
                'invalid_title' => 'Invalid Contract',
                'invalid_message' => 'This contract link is invalid.'
            ),
            'fr' => array(
                'page_title' => 'Signature Électronique',
                'contract_preview' => 'Aperçu du Contrat',
                'signature_section' => 'Signature',
                'signer_name_label' => 'Nom Complet',
                'signer_name_placeholder' => 'Entrez votre nom complet',
                'signer_email_label' => 'Adresse Email',
                'signer_email_placeholder' => 'Entrez votre adresse email',
                'draw_signature_label' => 'Dessinez votre signature ci-dessous',
                'clear_button' => 'Effacer',
                'consent_label' => 'J\'ai lu et j\'accepte les termes de ce contrat',
                'submit_button' => 'Signer le Contrat',
                'success_title' => 'Contrat Signé avec Succès !',
                'success_message' => 'Votre contrat a été signé et stocké en toute sécurité. Vous recevrez un email de confirmation sous peu.',
                'error_name_required' => 'Veuillez entrer votre nom complet',
                'error_email_required' => 'Veuillez entrer une adresse email valide',
                'error_signature_required' => 'Veuillez dessiner votre signature',
                'error_consent_required' => 'Vous devez consentir à signer le contrat',
                'error_general' => 'Une erreur s\'est produite. Veuillez réessayer.',
                'already_signed_title' => 'Contrat Déjà Signé',
                'already_signed_message' => 'Ce contrat a déjà été signé.',
                'expired_title' => 'Contrat Expiré',
                'expired_message' => 'Ce contrat a expiré et ne peut plus être signé.',
                'invalid_title' => 'Contrat Invalide',
                'invalid_message' => 'Ce lien de contrat est invalide.'
            ),
            'es' => array(
                'page_title' => 'Firma Electrónica',
                'contract_preview' => 'Vista Previa del Contrato',
                'signature_section' => 'Firma',
                'signer_name_label' => 'Nombre Completo',
                'signer_name_placeholder' => 'Ingrese su nombre completo',
                'signer_email_label' => 'Correo Electrónico',
                'signer_email_placeholder' => 'Ingrese su correo electrónico',
                'draw_signature_label' => 'Dibuje su firma a continuación',
                'clear_button' => 'Borrar',
                'consent_label' => 'He leído y acepto los términos de este contrato',
                'submit_button' => 'Firmar Contrato',
                'success_title' => '¡Contrato Firmado Exitosamente!',
                'success_message' => 'Su contrato ha sido firmado y almacenado de forma segura. Recibirá un correo de confirmación en breve.',
                'error_name_required' => 'Por favor ingrese su nombre completo',
                'error_email_required' => 'Por favor ingrese una dirección de correo electrónico válida',
                'error_signature_required' => 'Por favor dibuje su firma',
                'error_consent_required' => 'Debe consentir para firmar el contrato',
                'error_general' => 'Ocurrió un error. Por favor, inténtelo de nuevo.',
                'already_signed_title' => 'Contrato Ya Firmado',
                'already_signed_message' => 'Este contrato ya ha sido firmado.',
                'expired_title' => 'Contrato Expirado',
                'expired_message' => 'Este contrato ha expirado y ya no puede ser firmado.',
                'invalid_title' => 'Contrato Inválido',
                'invalid_message' => 'Este enlace de contrato es inválido.'
            ),
            'de' => array(
                'page_title' => 'Elektronische Unterschrift',
                'contract_preview' => 'Vertragsvorschau',
                'signature_section' => 'Unterschrift',
                'signer_name_label' => 'Vollständiger Name',
                'signer_name_placeholder' => 'Geben Sie Ihren vollständigen Namen ein',
                'signer_email_label' => 'E-Mail-Adresse',
                'signer_email_placeholder' => 'Geben Sie Ihre E-Mail-Adresse ein',
                'draw_signature_label' => 'Zeichnen Sie Ihre Unterschrift unten',
                'clear_button' => 'Löschen',
                'consent_label' => 'Ich habe die Bedingungen dieses Vertrags gelesen und stimme ihnen zu',
                'submit_button' => 'Vertrag Unterschreiben',
                'success_title' => 'Vertrag Erfolgreich Unterschrieben!',
                'success_message' => 'Ihr Vertrag wurde unterschrieben und sicher gespeichert. Sie erhalten in Kürze eine Bestätigungs-E-Mail.',
                'error_name_required' => 'Bitte geben Sie Ihren vollständigen Namen ein',
                'error_email_required' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein',
                'error_signature_required' => 'Bitte zeichnen Sie Ihre Unterschrift',
                'error_consent_required' => 'Sie müssen zustimmen, um den Vertrag zu unterschreiben',
                'error_general' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.',
                'already_signed_title' => 'Vertrag Bereits Unterschrieben',
                'already_signed_message' => 'Dieser Vertrag wurde bereits unterschrieben.',
                'expired_title' => 'Vertrag Abgelaufen',
                'expired_message' => 'Dieser Vertrag ist abgelaufen und kann nicht mehr unterschrieben werden.',
                'invalid_title' => 'Ungültiger Vertrag',
                'invalid_message' => 'Dieser Vertragslink ist ungültig.'
            ),
            'it' => array(
                'page_title' => 'Firma Elettronica',
                'contract_preview' => 'Anteprima del Contratto',
                'signature_section' => 'Firma',
                'signer_name_label' => 'Nome Completo',
                'signer_name_placeholder' => 'Inserisci il tuo nome completo',
                'signer_email_label' => 'Indirizzo Email',
                'signer_email_placeholder' => 'Inserisci il tuo indirizzo email',
                'draw_signature_label' => 'Disegna la tua firma qui sotto',
                'clear_button' => 'Cancella',
                'consent_label' => 'Ho letto e accetto i termini di questo contratto',
                'submit_button' => 'Firma Contratto',
                'success_title' => 'Contratto Firmato con Successo!',
                'success_message' => 'Il tuo contratto è stato firmato e archiviato in modo sicuro. Riceverai a breve un\'email di conferma.',
                'error_name_required' => 'Inserisci il tuo nome completo',
                'error_email_required' => 'Inserisci un indirizzo email valido',
                'error_signature_required' => 'Disegna la tua firma',
                'error_consent_required' => 'Devi acconsentire per firmare il contratto',
                'error_general' => 'Si è verificato un errore. Riprova.',
                'already_signed_title' => 'Contratto Già Firmato',
                'already_signed_message' => 'Questo contratto è già stato firmato.',
                'expired_title' => 'Contratto Scaduto',
                'expired_message' => 'Questo contratto è scaduto e non può più essere firmato.',
                'invalid_title' => 'Contratto Non Valido',
                'invalid_message' => 'Questo link del contratto non è valido.'
            ),
            'pt' => array(
                'page_title' => 'Assinatura Eletrônica',
                'contract_preview' => 'Visualização do Contrato',
                'signature_section' => 'Assinatura',
                'signer_name_label' => 'Nome Completo',
                'signer_name_placeholder' => 'Digite seu nome completo',
                'signer_email_label' => 'Endereço de Email',
                'signer_email_placeholder' => 'Digite seu endereço de email',
                'draw_signature_label' => 'Desenhe sua assinatura abaixo',
                'clear_button' => 'Limpar',
                'consent_label' => 'Li e concordo com os termos deste contrato',
                'submit_button' => 'Assinar Contrato',
                'success_title' => 'Contrato Assinado com Sucesso!',
                'success_message' => 'Seu contrato foi assinado e armazenado com segurança. Você receberá um email de confirmação em breve.',
                'error_name_required' => 'Por favor, digite seu nome completo',
                'error_email_required' => 'Por favor, digite um endereço de email válido',
                'error_signature_required' => 'Por favor, desenhe sua assinatura',
                'error_consent_required' => 'Você deve consentir para assinar o contrato',
                'error_general' => 'Ocorreu um erro. Por favor, tente novamente.',
                'already_signed_title' => 'Contrato Já Assinado',
                'already_signed_message' => 'Este contrato já foi assinado.',
                'expired_title' => 'Contrato Expirado',
                'expired_message' => 'Este contrato expirou e não pode mais ser assinado.',
                'invalid_title' => 'Contrato Inválido',
                'invalid_message' => 'Este link de contrato é inválido.'
            ),
            'nl' => array(
                'page_title' => 'Elektronische Handtekening',
                'contract_preview' => 'Contract Voorbeeld',
                'signature_section' => 'Handtekening',
                'signer_name_label' => 'Volledige Naam',
                'signer_name_placeholder' => 'Voer uw volledige naam in',
                'signer_email_label' => 'E-mailadres',
                'signer_email_placeholder' => 'Voer uw e-mailadres in',
                'draw_signature_label' => 'Teken hieronder uw handtekening',
                'clear_button' => 'Wissen',
                'consent_label' => 'Ik heb de voorwaarden van dit contract gelezen en ga ermee akkoord',
                'submit_button' => 'Contract Ondertekenen',
                'success_title' => 'Contract Succesvol Ondertekend!',
                'success_message' => 'Uw contract is ondertekend en veilig opgeslagen. U ontvangt binnenkort een bevestigingsmail.',
                'error_name_required' => 'Voer uw volledige naam in',
                'error_email_required' => 'Voer een geldig e-mailadres in',
                'error_signature_required' => 'Teken uw handtekening',
                'error_consent_required' => 'U moet instemmen om het contract te ondertekenen',
                'error_general' => 'Er is een fout opgetreden. Probeer het opnieuw.',
                'already_signed_title' => 'Contract Al Ondertekend',
                'already_signed_message' => 'Dit contract is al ondertekend.',
                'expired_title' => 'Contract Verlopen',
                'expired_message' => 'Dit contract is verlopen en kan niet meer worden ondertekend.',
                'invalid_title' => 'Ongeldig Contract',
                'invalid_message' => 'Deze contractlink is ongeldig.'
            )
        );

        // Return requested language or fall back to English
        if (isset($translations[$language])) {
            return $translations[$language];
        }

        return $translations['en'];
    }

    /**
     * Get a specific translation string
     */
    public static function get_string($key, $language = 'en') {
        $translations = self::get_signature_page_translations($language);
        return isset($translations[$key]) ? $translations[$key] : $key;
    }
}

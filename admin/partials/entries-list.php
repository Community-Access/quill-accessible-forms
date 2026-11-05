<?php
/**
 * Entries List Page Template
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap qaf-wrap">
    <h1><?php esc_html_e( 'Form Entries', 'quill-accessible-forms' ); ?></h1>
    <p><?php esc_html_e( 'View and manage form submissions.', 'quill-accessible-forms' ); ?></p>
    
    <!-- Entries list will be populated here -->
    <div class="qaf-entries-container">
        <p><?php esc_html_e( 'Select a form to view its entries.', 'quill-accessible-forms' ); ?></p>
    </div>
</div>

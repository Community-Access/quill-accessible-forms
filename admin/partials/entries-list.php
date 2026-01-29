<?php
/**
 * Entries List Page Template
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap baf-wrap">
    <h1><?php esc_html_e( 'Form Entries', 'bits-accessible-forms' ); ?></h1>
    <p><?php esc_html_e( 'View and manage form submissions.', 'bits-accessible-forms' ); ?></p>
    
    <!-- Entries list will be populated here -->
    <div class="baf-entries-container">
        <p><?php esc_html_e( 'Select a form to view its entries.', 'bits-accessible-forms' ); ?></p>
    </div>
</div>

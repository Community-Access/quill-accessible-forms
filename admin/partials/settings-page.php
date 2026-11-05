<?php
/**
 * Settings Page Template
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = QAF_Settings::get_instance();
?>
<div class="wrap qaf-wrap">
    <h1><?php esc_html_e( 'QUILL Accessible Forms Settings', 'quill-accessible-forms' ); ?></h1>
    
    <form method="post" action="options.php" class="qaf-settings-form">
        <?php settings_fields( 'qaf_settings' ); ?>
        
        <div class="qaf-settings-tabs">
            <nav class="qaf-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'quill-accessible-forms' ); ?>">
                <button type="button" role="tab" aria-selected="true" aria-controls="qaf-tab-general" id="qaf-tab-btn-general" class="qaf-tab-btn active">
                    <?php esc_html_e( 'General', 'quill-accessible-forms' ); ?>
                </button>
                <button type="button" role="tab" aria-selected="false" aria-controls="qaf-tab-accessibility" id="qaf-tab-btn-accessibility" class="qaf-tab-btn">
                    <?php esc_html_e( 'Accessibility', 'quill-accessible-forms' ); ?>
                </button>
                <button type="button" role="tab" aria-selected="false" aria-controls="qaf-tab-security" id="qaf-tab-btn-security" class="qaf-tab-btn">
                    <?php esc_html_e( 'Security', 'quill-accessible-forms' ); ?>
                </button>
                <button type="button" role="tab" aria-selected="false" aria-controls="qaf-tab-integrations" id="qaf-tab-btn-integrations" class="qaf-tab-btn">
                    <?php esc_html_e( 'Integrations', 'quill-accessible-forms' ); ?>
                </button>
            </nav>
            
            <div id="qaf-tab-general" role="tabpanel" aria-labelledby="qaf-tab-btn-general" class="qaf-tab-panel active">
                <h2><?php esc_html_e( 'General Settings', 'quill-accessible-forms' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="qaf_default_label_placement"><?php esc_html_e( 'Default Label Placement', 'quill-accessible-forms' ); ?></label>
                        </th>
                        <td>
                            <select id="qaf_default_label_placement" name="qaf_settings[default_label_placement]">
                                <option value="above"><?php esc_html_e( 'Above field', 'quill-accessible-forms' ); ?></option>
                                <option value="below"><?php esc_html_e( 'Below field', 'quill-accessible-forms' ); ?></option>
                                <option value="left"><?php esc_html_e( 'Left of field', 'quill-accessible-forms' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="qaf_required_indicator"><?php esc_html_e( 'Required Field Indicator', 'quill-accessible-forms' ); ?></label>
                        </th>
                        <td>
                            <select id="qaf_required_indicator" name="qaf_settings[required_indicator]">
                                <option value="asterisk"><?php esc_html_e( 'Asterisk (*)', 'quill-accessible-forms' ); ?></option>
                                <option value="text"><?php esc_html_e( 'Text (Required)', 'quill-accessible-forms' ); ?></option>
                                <option value="both"><?php esc_html_e( 'Both (* Required)', 'quill-accessible-forms' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="qaf-tab-accessibility" role="tabpanel" aria-labelledby="qaf-tab-btn-accessibility" class="qaf-tab-panel" hidden>
                <h2><?php esc_html_e( 'Accessibility Settings', 'quill-accessible-forms' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Live Announcements', 'quill-accessible-forms' ); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e( 'Live Announcements', 'quill-accessible-forms' ); ?></legend>
                                <label>
                                    <input type="checkbox" name="qaf_settings[aria_live_announcements]" value="1" checked>
                                    <?php esc_html_e( 'Enable ARIA live region announcements for screen readers', 'quill-accessible-forms' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Focus Management', 'quill-accessible-forms' ); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e( 'Focus Management', 'quill-accessible-forms' ); ?></legend>
                                <label>
                                    <input type="checkbox" name="qaf_settings[focus_first_error]" value="1" checked>
                                    <?php esc_html_e( 'Move focus to first error on validation failure', 'quill-accessible-forms' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="qaf-tab-security" role="tabpanel" aria-labelledby="qaf-tab-btn-security" class="qaf-tab-panel" hidden>
                <h2><?php esc_html_e( 'Security Settings', 'quill-accessible-forms' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Honeypot Protection', 'quill-accessible-forms' ); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e( 'Honeypot Protection', 'quill-accessible-forms' ); ?></legend>
                                <label>
                                    <input type="checkbox" name="qaf_settings[enable_honeypot]" value="1" checked>
                                    <?php esc_html_e( 'Enable honeypot spam protection by default', 'quill-accessible-forms' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Rate Limiting', 'quill-accessible-forms' ); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e( 'Rate Limiting', 'quill-accessible-forms' ); ?></legend>
                                <label>
                                    <input type="checkbox" name="qaf_settings[rate_limit_enabled]" value="1" checked>
                                    <?php esc_html_e( 'Enable rate limiting on form submissions', 'quill-accessible-forms' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="qaf-tab-integrations" role="tabpanel" aria-labelledby="qaf-tab-btn-integrations" class="qaf-tab-panel" hidden>
                <h2><?php esc_html_e( 'Integrations', 'quill-accessible-forms' ); ?></h2>
                <p><?php esc_html_e( 'Configure third-party service integrations.', 'quill-accessible-forms' ); ?></p>

                <h3><?php esc_html_e( 'Google', 'quill-accessible-forms' ); ?></h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="qaf_google_maps_api_key"><?php esc_html_e( 'Google Maps API Key', 'quill-accessible-forms' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="qaf_google_maps_api_key" name="qaf_settings[google_maps_api_key]" value="<?php echo esc_attr( $settings->get( 'google_maps_api_key', '' ) ); ?>" class="regular-text" autocomplete="off">
                            <p class="description"><?php esc_html_e( 'Used for optional Address Autocomplete (Google Places).', 'quill-accessible-forms' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<?php
/**
 * Settings Page Template
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = BAF_Settings::get_instance();
?>
<div class="wrap baf-wrap">
    <h1><?php esc_html_e( 'BITS Accessible Forms Settings', 'bits-accessible-forms' ); ?></h1>
    
    <form method="post" action="options.php" class="baf-settings-form">
        <?php settings_fields( 'baf_settings' ); ?>
        
        <div class="baf-settings-tabs">
            <nav class="baf-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'bits-accessible-forms' ); ?>">
                <button type="button" role="tab" aria-selected="true" aria-controls="baf-tab-general" id="baf-tab-btn-general" class="baf-tab-btn active">
                    <?php esc_html_e( 'General', 'bits-accessible-forms' ); ?>
                </button>
                <button type="button" role="tab" aria-selected="false" aria-controls="baf-tab-accessibility" id="baf-tab-btn-accessibility" class="baf-tab-btn">
                    <?php esc_html_e( 'Accessibility', 'bits-accessible-forms' ); ?>
                </button>
                <button type="button" role="tab" aria-selected="false" aria-controls="baf-tab-security" id="baf-tab-btn-security" class="baf-tab-btn">
                    <?php esc_html_e( 'Security', 'bits-accessible-forms' ); ?>
                </button>
                <button type="button" role="tab" aria-selected="false" aria-controls="baf-tab-integrations" id="baf-tab-btn-integrations" class="baf-tab-btn">
                    <?php esc_html_e( 'Integrations', 'bits-accessible-forms' ); ?>
                </button>
            </nav>
            
            <div id="baf-tab-general" role="tabpanel" aria-labelledby="baf-tab-btn-general" class="baf-tab-panel active">
                <h2><?php esc_html_e( 'General Settings', 'bits-accessible-forms' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="baf_default_label_placement"><?php esc_html_e( 'Default Label Placement', 'bits-accessible-forms' ); ?></label>
                        </th>
                        <td>
                            <select id="baf_default_label_placement" name="baf_settings[default_label_placement]">
                                <option value="above"><?php esc_html_e( 'Above field', 'bits-accessible-forms' ); ?></option>
                                <option value="below"><?php esc_html_e( 'Below field', 'bits-accessible-forms' ); ?></option>
                                <option value="left"><?php esc_html_e( 'Left of field', 'bits-accessible-forms' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="baf_required_indicator"><?php esc_html_e( 'Required Field Indicator', 'bits-accessible-forms' ); ?></label>
                        </th>
                        <td>
                            <select id="baf_required_indicator" name="baf_settings[required_indicator]">
                                <option value="asterisk"><?php esc_html_e( 'Asterisk (*)', 'bits-accessible-forms' ); ?></option>
                                <option value="text"><?php esc_html_e( 'Text (Required)', 'bits-accessible-forms' ); ?></option>
                                <option value="both"><?php esc_html_e( 'Both (* Required)', 'bits-accessible-forms' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="baf-tab-accessibility" role="tabpanel" aria-labelledby="baf-tab-btn-accessibility" class="baf-tab-panel" hidden>
                <h2><?php esc_html_e( 'Accessibility Settings', 'bits-accessible-forms' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Live Announcements', 'bits-accessible-forms' ); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e( 'Live Announcements', 'bits-accessible-forms' ); ?></legend>
                                <label>
                                    <input type="checkbox" name="baf_settings[aria_live_announcements]" value="1" checked>
                                    <?php esc_html_e( 'Enable ARIA live region announcements for screen readers', 'bits-accessible-forms' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Focus Management', 'bits-accessible-forms' ); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e( 'Focus Management', 'bits-accessible-forms' ); ?></legend>
                                <label>
                                    <input type="checkbox" name="baf_settings[focus_first_error]" value="1" checked>
                                    <?php esc_html_e( 'Move focus to first error on validation failure', 'bits-accessible-forms' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="baf-tab-security" role="tabpanel" aria-labelledby="baf-tab-btn-security" class="baf-tab-panel" hidden>
                <h2><?php esc_html_e( 'Security Settings', 'bits-accessible-forms' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Honeypot Protection', 'bits-accessible-forms' ); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e( 'Honeypot Protection', 'bits-accessible-forms' ); ?></legend>
                                <label>
                                    <input type="checkbox" name="baf_settings[enable_honeypot]" value="1" checked>
                                    <?php esc_html_e( 'Enable honeypot spam protection by default', 'bits-accessible-forms' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Rate Limiting', 'bits-accessible-forms' ); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e( 'Rate Limiting', 'bits-accessible-forms' ); ?></legend>
                                <label>
                                    <input type="checkbox" name="baf_settings[rate_limit_enabled]" value="1" checked>
                                    <?php esc_html_e( 'Enable rate limiting on form submissions', 'bits-accessible-forms' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="baf-tab-integrations" role="tabpanel" aria-labelledby="baf-tab-btn-integrations" class="baf-tab-panel" hidden>
                <h2><?php esc_html_e( 'Integrations', 'bits-accessible-forms' ); ?></h2>
                <p><?php esc_html_e( 'Configure third-party service integrations.', 'bits-accessible-forms' ); ?></p>

                <h3><?php esc_html_e( 'Google', 'bits-accessible-forms' ); ?></h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="baf_google_maps_api_key"><?php esc_html_e( 'Google Maps API Key', 'bits-accessible-forms' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="baf_google_maps_api_key" name="baf_settings[google_maps_api_key]" value="<?php echo esc_attr( $settings->get( 'google_maps_api_key', '' ) ); ?>" class="regular-text" autocomplete="off">
                            <p class="description"><?php esc_html_e( 'Used for optional Address Autocomplete (Google Places).', 'bits-accessible-forms' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

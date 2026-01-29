<?php
/**
 * CSV Import Wizard Admin Page
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap baf-csv-import-wizard">
    <h1><?php esc_html_e( 'Import Form from CSV', 'bits-accessible-forms' ); ?></h1>

    <div class="baf-wizard-container">
        <!-- Progress Steps -->
        <nav class="baf-wizard-steps" aria-label="<?php esc_attr_e( 'Import wizard progress', 'bits-accessible-forms' ); ?>">
            <ol>
                <li class="baf-step active" data-step="1">
                    <span class="step-number">1</span>
                    <span class="step-label"><?php esc_html_e( 'Upload CSV', 'bits-accessible-forms' ); ?></span>
                </li>
                <li class="baf-step" data-step="2">
                    <span class="step-number">2</span>
                    <span class="step-label"><?php esc_html_e( 'Configure Fields', 'bits-accessible-forms' ); ?></span>
                </li>
                <li class="baf-step" data-step="3">
                    <span class="step-number">3</span>
                    <span class="step-label"><?php esc_html_e( 'Preview & Create', 'bits-accessible-forms' ); ?></span>
                </li>
            </ol>
        </nav>

        <!-- Step 1: Upload -->
        <div class="baf-wizard-step" id="step-upload" aria-labelledby="step-1-heading">
            <h2 id="step-1-heading"><?php esc_html_e( 'Step 1: Upload Your CSV File', 'bits-accessible-forms' ); ?></h2>
            
            <p class="description">
                <?php esc_html_e( 'Upload a CSV file with column headers. The wizard will analyze your data and suggest appropriate field types.', 'bits-accessible-forms' ); ?>
            </p>

            <form id="baf-csv-upload-form" enctype="multipart/form-data">
                <?php wp_nonce_field( 'baf_csv_import', 'baf_csv_nonce' ); ?>
                
                <div class="baf-upload-dropzone" id="csv-dropzone" role="region" aria-label="<?php esc_attr_e( 'File upload area', 'bits-accessible-forms' ); ?>">
                    <input type="file" 
                           id="csv-file-input" 
                           name="csv_file" 
                           accept=".csv,text/csv,application/csv" 
                           class="screen-reader-text"
                           aria-describedby="upload-instructions">
                    
                    <div class="dropzone-content">
                        <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                        <p id="upload-instructions">
                            <?php esc_html_e( 'Drag and drop your CSV file here, or', 'bits-accessible-forms' ); ?>
                            <label for="csv-file-input" class="baf-upload-browse">
                                <?php esc_html_e( 'browse to select a file', 'bits-accessible-forms' ); ?>
                            </label>
                        </p>
                        <p class="upload-hint"><?php esc_html_e( 'Supported formats: .csv', 'bits-accessible-forms' ); ?></p>
                    </div>
                    
                    <div class="dropzone-selected" style="display:none;">
                        <span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
                        <p class="selected-filename"></p>
                        <button type="button" class="button baf-change-file">
                            <?php esc_html_e( 'Change File', 'bits-accessible-forms' ); ?>
                        </button>
                    </div>
                </div>

                <fieldset class="baf-csv-options">
                    <legend><?php esc_html_e( 'CSV Options', 'bits-accessible-forms' ); ?></legend>
                    
                    <div class="baf-form-row">
                        <label for="csv-delimiter"><?php esc_html_e( 'Delimiter:', 'bits-accessible-forms' ); ?></label>
                        <select id="csv-delimiter" name="delimiter">
                            <option value=","><?php esc_html_e( 'Comma (,)', 'bits-accessible-forms' ); ?></option>
                            <option value=";"><?php esc_html_e( 'Semicolon (;)', 'bits-accessible-forms' ); ?></option>
                            <option value="tab"><?php esc_html_e( 'Tab', 'bits-accessible-forms' ); ?></option>
                            <option value="|"><?php esc_html_e( 'Pipe (|)', 'bits-accessible-forms' ); ?></option>
                        </select>
                    </div>

                    <div class="baf-form-row">
                        <label>
                            <input type="checkbox" name="has_header" id="csv-has-header" checked>
                            <?php esc_html_e( 'First row contains column headers', 'bits-accessible-forms' ); ?>
                        </label>
                    </div>

                    <div class="baf-form-row">
                        <label>
                            <input type="checkbox" name="infer_types" id="csv-infer-types" checked>
                            <?php esc_html_e( 'Auto-detect field types (email, phone, date, etc.)', 'bits-accessible-forms' ); ?>
                        </label>
                    </div>

                    <div class="baf-form-row">
                        <label>
                            <input type="checkbox" name="detect_choices" id="csv-detect-choices" checked>
                            <?php esc_html_e( 'Auto-detect dropdown options from repeated values', 'bits-accessible-forms' ); ?>
                        </label>
                    </div>

                    <div class="baf-form-row">
                        <label>
                            <input type="checkbox" name="detect_cascades" id="csv-detect-cascades" checked>
                            <?php esc_html_e( 'Auto-detect cascade relationships (e.g., country/state)', 'bits-accessible-forms' ); ?>
                        </label>
                    </div>
                </fieldset>

                <div class="baf-wizard-actions">
                    <button type="submit" class="button button-primary" id="btn-analyze" disabled>
                        <?php esc_html_e( 'Analyze CSV', 'bits-accessible-forms' ); ?>
                        <span class="spinner"></span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Step 2: Configure -->
        <div class="baf-wizard-step" id="step-configure" style="display:none;" aria-labelledby="step-2-heading">
            <h2 id="step-2-heading"><?php esc_html_e( 'Step 2: Configure Form Fields', 'bits-accessible-forms' ); ?></h2>
            
            <div class="baf-analysis-summary" id="analysis-summary" role="status">
                <!-- Filled by JavaScript -->
            </div>

            <div class="baf-form-settings">
                <h3><?php esc_html_e( 'Form Settings', 'bits-accessible-forms' ); ?></h3>
                
                <div class="baf-form-row">
                    <label for="form-title"><?php esc_html_e( 'Form Title:', 'bits-accessible-forms' ); ?></label>
                    <input type="text" id="form-title" name="form_title" class="regular-text" value="">
                </div>

                <div class="baf-form-row">
                    <label for="form-description"><?php esc_html_e( 'Description:', 'bits-accessible-forms' ); ?></label>
                    <textarea id="form-description" name="form_description" rows="2" class="large-text"></textarea>
                </div>
            </div>

            <div class="baf-field-mapping">
                <h3><?php esc_html_e( 'Field Mapping', 'bits-accessible-forms' ); ?></h3>
                <p class="description">
                    <?php esc_html_e( 'Review and customize how each CSV column maps to a form field. Uncheck columns you want to exclude.', 'bits-accessible-forms' ); ?>
                </p>

                <table class="wp-list-table widefat fixed striped" id="field-mapping-table">
                    <thead>
                        <tr>
                            <th scope="col" class="column-include" style="width:40px;">
                                <span class="screen-reader-text"><?php esc_html_e( 'Include', 'bits-accessible-forms' ); ?></span>
                            </th>
                            <th scope="col" class="column-header"><?php esc_html_e( 'CSV Column', 'bits-accessible-forms' ); ?></th>
                            <th scope="col" class="column-type"><?php esc_html_e( 'Field Type', 'bits-accessible-forms' ); ?></th>
                            <th scope="col" class="column-confidence"><?php esc_html_e( 'Confidence', 'bits-accessible-forms' ); ?></th>
                            <th scope="col" class="column-label"><?php esc_html_e( 'Label', 'bits-accessible-forms' ); ?></th>
                            <th scope="col" class="column-required"><?php esc_html_e( 'Required', 'bits-accessible-forms' ); ?></th>
                            <th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'bits-accessible-forms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="field-mapping-body">
                        <!-- Filled by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Cascade Relationships -->
            <div class="baf-cascade-info" id="cascade-info" style="display:none;">
                <h3><?php esc_html_e( 'Detected Cascade Relationships', 'bits-accessible-forms' ); ?></h3>
                <div id="cascade-list">
                    <!-- Filled by JavaScript -->
                </div>
            </div>

            <div class="baf-wizard-actions">
                <button type="button" class="button" id="btn-back-upload">
                    <?php esc_html_e( '← Back', 'bits-accessible-forms' ); ?>
                </button>
                <button type="button" class="button button-primary" id="btn-preview">
                    <?php esc_html_e( 'Preview Form →', 'bits-accessible-forms' ); ?>
                    <span class="spinner"></span>
                </button>
            </div>
        </div>

        <!-- Step 3: Preview & Create -->
        <div class="baf-wizard-step" id="step-preview" style="display:none;" aria-labelledby="step-3-heading">
            <h2 id="step-3-heading"><?php esc_html_e( 'Step 3: Preview & Create Form', 'bits-accessible-forms' ); ?></h2>

            <div class="baf-preview-tabs">
                <button type="button" class="tab-button active" data-tab="visual" aria-selected="true">
                    <?php esc_html_e( 'Visual Preview', 'bits-accessible-forms' ); ?>
                </button>
                <button type="button" class="tab-button" data-tab="bfl" aria-selected="false">
                    <?php esc_html_e( 'BFL Code', 'bits-accessible-forms' ); ?>
                </button>
            </div>

            <div class="baf-preview-content">
                <div class="tab-panel active" id="preview-visual">
                    <div class="baf-form-preview" id="form-preview">
                        <!-- Filled by JavaScript -->
                    </div>
                </div>
                <div class="tab-panel" id="preview-bfl" style="display:none;">
                    <textarea id="bfl-output" class="code" rows="20" readonly></textarea>
                    <button type="button" class="button" id="btn-copy-bfl">
                        <?php esc_html_e( 'Copy BFL Code', 'bits-accessible-forms' ); ?>
                    </button>
                </div>
            </div>

            <div class="baf-wizard-actions">
                <button type="button" class="button" id="btn-back-configure">
                    <?php esc_html_e( '← Back', 'bits-accessible-forms' ); ?>
                </button>
                <button type="button" class="button button-primary button-hero" id="btn-create-form">
                    <?php esc_html_e( 'Create Form', 'bits-accessible-forms' ); ?>
                    <span class="spinner"></span>
                </button>
            </div>
        </div>

        <!-- Success Message -->
        <div class="baf-wizard-step" id="step-success" style="display:none;">
            <div class="baf-success-message">
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <h2><?php esc_html_e( 'Form Created Successfully!', 'bits-accessible-forms' ); ?></h2>
                <p id="success-message"></p>
                <div class="baf-success-actions">
                    <a href="#" id="edit-form-link" class="button button-primary">
                        <?php esc_html_e( 'Edit Form', 'bits-accessible-forms' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=baf-forms' ) ); ?>" class="button">
                        <?php esc_html_e( 'View All Forms', 'bits-accessible-forms' ); ?>
                    </a>
                    <button type="button" class="button" id="btn-import-another">
                        <?php esc_html_e( 'Import Another CSV', 'bits-accessible-forms' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Field Edit Modal -->
<div id="field-edit-modal" class="baf-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="baf-modal-content">
        <div class="baf-modal-header">
            <h3 id="modal-title"><?php esc_html_e( 'Edit Field Settings', 'bits-accessible-forms' ); ?></h3>
            <button type="button" class="baf-modal-close" aria-label="<?php esc_attr_e( 'Close', 'bits-accessible-forms' ); ?>">
                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
            </button>
        </div>
        <div class="baf-modal-body">
            <input type="hidden" id="modal-column-index">
            
            <div class="baf-form-row">
                <label for="modal-field-type"><?php esc_html_e( 'Field Type:', 'bits-accessible-forms' ); ?></label>
                <select id="modal-field-type">
                    <option value="text"><?php esc_html_e( 'Text', 'bits-accessible-forms' ); ?></option>
                    <option value="email"><?php esc_html_e( 'Email', 'bits-accessible-forms' ); ?></option>
                    <option value="phone"><?php esc_html_e( 'Phone', 'bits-accessible-forms' ); ?></option>
                    <option value="number"><?php esc_html_e( 'Number', 'bits-accessible-forms' ); ?></option>
                    <option value="url"><?php esc_html_e( 'URL', 'bits-accessible-forms' ); ?></option>
                    <option value="date"><?php esc_html_e( 'Date', 'bits-accessible-forms' ); ?></option>
                    <option value="textarea"><?php esc_html_e( 'Textarea', 'bits-accessible-forms' ); ?></option>
                    <option value="select"><?php esc_html_e( 'Dropdown', 'bits-accessible-forms' ); ?></option>
                    <option value="radio"><?php esc_html_e( 'Radio Buttons', 'bits-accessible-forms' ); ?></option>
                    <option value="checkbox"><?php esc_html_e( 'Checkboxes', 'bits-accessible-forms' ); ?></option>
                </select>
            </div>

            <div class="baf-form-row">
                <label for="modal-field-id"><?php esc_html_e( 'Field ID:', 'bits-accessible-forms' ); ?></label>
                <input type="text" id="modal-field-id" class="regular-text" pattern="[a-z0-9_]+">
                <p class="description"><?php esc_html_e( 'Lowercase letters, numbers, and underscores only.', 'bits-accessible-forms' ); ?></p>
            </div>

            <div class="baf-form-row">
                <label for="modal-field-label"><?php esc_html_e( 'Label:', 'bits-accessible-forms' ); ?></label>
                <input type="text" id="modal-field-label" class="regular-text">
            </div>

            <div class="baf-form-row">
                <label>
                    <input type="checkbox" id="modal-field-required">
                    <?php esc_html_e( 'Required', 'bits-accessible-forms' ); ?>
                </label>
            </div>

            <div class="baf-choices-section" id="modal-choices-section" style="display:none;">
                <h4><?php esc_html_e( 'Choices', 'bits-accessible-forms' ); ?></h4>
                <div id="modal-choices-list">
                    <!-- Filled by JavaScript -->
                </div>
                <div class="baf-choices-actions">
                    <button type="button" class="button" id="btn-add-choice">
                        <?php esc_html_e( '+ Add Choice', 'bits-accessible-forms' ); ?>
                    </button>
                    <button type="button" class="button" id="btn-load-lookup">
                        <?php esc_html_e( 'Load from CSV', 'bits-accessible-forms' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="baf-modal-footer">
            <button type="button" class="button" id="btn-modal-cancel">
                <?php esc_html_e( 'Cancel', 'bits-accessible-forms' ); ?>
            </button>
            <button type="button" class="button button-primary" id="btn-modal-save">
                <?php esc_html_e( 'Save Changes', 'bits-accessible-forms' ); ?>
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">
// Field types for the type selector
var bafFieldTypes = <?php
    $field_registry = BAF_Field_Registry::get_instance();
    echo wp_json_encode( $field_registry->get_for_builder() );
?>;
</script>

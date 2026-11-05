<?php
/**
 * Form Builder Page Template
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
$form = $form_id ? new BAF_Form( $form_id ) : new BAF_Form();
$form_builder = new BAF_Form_Builder();
$structure_mode = new BAF_Structure_Mode();
require_once BAF_PLUGIN_DIR . 'admin/partials/model-screens.php';
?>
<div class="wrap baf-wrap">
    <!-- Skip Links for Accessibility -->
    <nav class="baf-skip-links" aria-label="<?php esc_attr_e( 'Skip links', 'bits-accessible-forms' ); ?>">
        <a href="#baf-form-canvas" class="baf-skip-link"><?php esc_html_e( 'Skip to Form Canvas', 'bits-accessible-forms' ); ?></a>
        <a href="#baf-field-palette" class="baf-skip-link"><?php esc_html_e( 'Skip to Field Palette', 'bits-accessible-forms' ); ?></a>
        <a href="#baf-field-settings" class="baf-skip-link"><?php esc_html_e( 'Skip to Field Settings', 'bits-accessible-forms' ); ?></a>
    </nav>

    <header class="baf-builder-header">
        <h1 class="baf-sr-only">
            <?php
            if ( $form_id ) {
                echo esc_html( sprintf( __( 'Edit Form: %s', 'bits-accessible-forms' ), $form->title ) );
            } else {
                esc_html_e( 'Create New Form', 'bits-accessible-forms' );
            }
            ?>
        </h1>
        
        <div class="baf-header-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=baf-forms' ) ); ?>" class="baf-back-link" aria-label="<?php esc_attr_e( 'Back to forms list', 'bits-accessible-forms' ); ?>">
                <span aria-hidden="true">←</span>
                <?php esc_html_e( 'Back', 'bits-accessible-forms' ); ?>
            </a>
            
            <input type="text" 
                   id="baf-form-title" 
                   class="baf-form-title-input" 
                   value="<?php echo esc_attr( $form->title ?: __( 'Untitled Form', 'bits-accessible-forms' ) ); ?>"
                   placeholder="<?php esc_attr_e( 'Form Title', 'bits-accessible-forms' ); ?>"
                   aria-label="<?php esc_attr_e( 'Form title', 'bits-accessible-forms' ); ?>">
        </div>
        
        <div class="baf-header-center">
            <div class="baf-mode-switcher" role="tablist" aria-label="<?php esc_attr_e( 'Builder mode', 'bits-accessible-forms' ); ?>">
                <button type="button" 
                        role="tab" 
                        aria-selected="true" 
                        aria-controls="baf-visual-panel"
                        id="baf-mode-visual"
                        class="baf-mode-btn active">
                    <?php esc_html_e( 'Visual', 'bits-accessible-forms' ); ?>
                </button>
                <button type="button" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="baf-structure-panel"
                        id="baf-mode-structure"
                        class="baf-mode-btn">
                    <?php esc_html_e( 'Structure', 'bits-accessible-forms' ); ?>
                </button>
                <button type="button" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="baf-bfl-panel"
                        id="baf-mode-bfl"
                        class="baf-mode-btn">
                    <?php esc_html_e( 'BFL Code', 'bits-accessible-forms' ); ?>
                </button>
                <?php baf_model_screen_tabs(); ?>
            </div>
        </div>
        
        <div class="baf-header-right">
            <button type="button" class="baf-btn baf-btn-secondary" id="baf-preview-btn">
                <span aria-hidden="true">👁️</span>
                <?php esc_html_e( 'Preview', 'bits-accessible-forms' ); ?>
            </button>
            <button type="button" class="baf-btn baf-btn-secondary" id="baf-save-draft-btn">
                <?php esc_html_e( 'Save Draft', 'bits-accessible-forms' ); ?>
            </button>
            <button type="button" class="baf-btn baf-btn-primary" id="baf-publish-btn">
                <?php esc_html_e( 'Publish', 'bits-accessible-forms' ); ?>
            </button>
            <p id="baf-save-status" class="baf-save-status" role="status" aria-live="polite" aria-atomic="true"><?php echo esc_html( $form_id ? ( $form->status === 'active' ? __( 'Published.', 'bits-accessible-forms' ) : __( 'Draft.', 'bits-accessible-forms' ) ) : '' ); ?></p>
        </div>
    </header>

    <main class="baf-builder-main" id="baf-builder-main">
        <!-- Visual Mode Panel -->
        <div id="baf-visual-panel" role="tabpanel" aria-labelledby="baf-mode-visual" class="baf-mode-panel active">
            <div class="baf-builder-layout">
                <!-- Field Palette -->
                <aside class="baf-palette-panel" id="baf-field-palette" aria-label="<?php esc_attr_e( 'Field palette', 'bits-accessible-forms' ); ?>">
                    <?php echo $form_builder->render_field_palette(); ?>
                </aside>
                
                <!-- Form Canvas -->
                <section class="baf-canvas-panel" id="baf-form-canvas" aria-label="<?php esc_attr_e( 'Form canvas', 'bits-accessible-forms' ); ?>">
                    <div class="baf-canvas-header">
                        <h2 id="baf-canvas-title" class="baf-canvas-title">
                            <?php echo esc_html( $form->title ?: __( 'Form Canvas', 'bits-accessible-forms' ) ); ?>
                        </h2>
                        <p id="baf-canvas-instructions" class="baf-canvas-instructions">
                            <?php esc_html_e( 'Each question is a card, inside its section or column. Every change is saved as it is made. Use the move buttons, or Control with the up and down arrows, to reorder; a question moves among its neighbours, and between pages in Structure mode.', 'bits-accessible-forms' ); ?>
                        </p>
                    </div>

                    <!-- The cards, drawn from the form model (BAF_Form_Builder::render_canvas). -->
                    <div class="baf-canvas-dropzone"
                         id="baf-canvas-dropzone"
                         aria-describedby="baf-canvas-instructions">
                        <?php echo $form_builder->render_canvas( $form_id ? $form->get_model() : BAF_Builder_Field_Ops::new_model( '' ), $form_id ); ?>
                    </div>

                    <!-- Add Field Button (keyboard accessible); F does the same while focus is in the canvas. -->
                    <div class="baf-canvas-footer">
                        <button type="button" class="baf-btn baf-btn-dashed baf-add-field-btn" id="baf-add-field-btn" aria-haspopup="dialog" aria-keyshortcuts="F">
                            <span aria-hidden="true">+</span>
                            <?php esc_html_e( 'Add Field', 'bits-accessible-forms' ); ?>
                            <span class="baf-shortcut-hint" aria-hidden="true">(F)</span>
                        </button>
                    </div>
                </section>
                
                <!-- Field Settings Panel -->
                <aside class="baf-settings-panel" id="baf-field-settings" aria-label="<?php esc_attr_e( 'Field settings', 'bits-accessible-forms' ); ?>" hidden>
                    <div class="baf-settings-header">
                        <h3 id="baf-settings-title"><?php esc_html_e( 'Field Settings', 'bits-accessible-forms' ); ?></h3>
                        <button type="button" class="baf-close-settings" aria-label="<?php esc_attr_e( 'Close settings panel', 'bits-accessible-forms' ); ?>">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    
                    <div class="baf-settings-body" id="baf-settings-body">
                        <p class="baf-settings-placeholder"><?php esc_html_e( 'Select a field to edit its settings.', 'bits-accessible-forms' ); ?></p>
                    </div>
                </aside>
            </div>
        </div>
        
        <!-- Structure Mode Panel -->
        <div id="baf-structure-panel" role="tabpanel" aria-labelledby="baf-mode-structure" class="baf-mode-panel" hidden>
            <?php echo $structure_mode->render( $form->config, $form_id, $form->get_model() ); ?>
        </div>
        
        <!-- BFL Code Mode Panel -->
        <div id="baf-bfl-panel" role="tabpanel" aria-labelledby="baf-mode-bfl" class="baf-mode-panel" hidden>
            <div class="baf-bfl-editor-container">
                <div class="baf-bfl-toolbar">
                    <button type="button" class="baf-btn baf-btn-secondary" id="baf-bfl-sync">
                        <?php esc_html_e( 'Sync to Visual', 'bits-accessible-forms' ); ?>
                    </button>
                    <button type="button" class="baf-btn baf-btn-secondary" id="baf-bfl-format">
                        <?php esc_html_e( 'Format Code', 'bits-accessible-forms' ); ?>
                    </button>
                    <span class="baf-bfl-status" id="baf-bfl-status" role="status" aria-live="polite"></span>
                </div>
                
                <label for="baf-bfl-editor" class="baf-sr-only"><?php esc_html_e( 'BFL Code Editor', 'bits-accessible-forms' ); ?></label>
                <textarea id="baf-bfl-editor" 
                          class="baf-bfl-textarea" 
                          aria-describedby="baf-bfl-help"
                          spellcheck="false"><?php echo esc_textarea( $form->bfl_source ? $form->bfl_source : ( $form_id ? BAF_BFL_Writer::write( $form->get_model() ) : '' ) ); ?></textarea>
                
                <div id="baf-bfl-help" class="baf-bfl-help">
                    <p><?php esc_html_e( 'Write form definitions in BITS Form Language. Changes will sync when you switch modes.', 'bits-accessible-forms' ); ?></p>
                </div>
                
                <div class="baf-bfl-errors" id="baf-bfl-errors" role="alert" aria-live="polite"></div>
            </div>
        </div>

        <!-- Rules, Quiz outcomes, Translations, Choice libraries -->
        <?php baf_model_screen_panels(); ?>
    </main>
    
    <!-- Hidden form data -->
    <input type="hidden" id="baf-form-id" value="<?php echo esc_attr( $form_id ); ?>">
    <input type="hidden" id="baf-form-config" value="<?php echo esc_attr( wp_json_encode( $form->config ) ); ?>">
    <input type="hidden" id="baf-form-settings" value="<?php echo esc_attr( wp_json_encode( $form->settings ) ); ?>">
</div>

<?php baf_model_screen_dialogs(); ?>

<!-- Add Field (F) and Convert Type: modal dialogs, drawn from the field registry. -->
<?php baf_builder_type_dialogs( BAF_Field_Registry::get_instance()->get_for_builder() ); ?>

<!-- Confirm Delete Dialog -->
<div id="baf-confirm-dialog" 
     class="baf-modal baf-confirm-dialog" 
     role="alertdialog" 
     aria-labelledby="baf-confirm-title" 
     aria-describedby="baf-confirm-message"
     aria-modal="true" 
     hidden>
    <div class="baf-modal-backdrop"></div>
    <div class="baf-modal-content" data-baf-focus-trap="true">
        <div class="baf-modal-header">
            <h2 id="baf-confirm-title"><?php esc_html_e( 'Confirm Delete', 'bits-accessible-forms' ); ?></h2>
        </div>
        <div class="baf-modal-body">
            <p id="baf-confirm-message"><?php esc_html_e( 'Are you sure you want to delete this field? This action cannot be undone.', 'bits-accessible-forms' ); ?></p>
        </div>
        <div class="baf-modal-footer">
            <button type="button" class="baf-btn baf-btn-secondary" data-action="cancel">
                <?php esc_html_e( 'Cancel', 'bits-accessible-forms' ); ?>
            </button>
            <button type="button" class="baf-btn baf-btn-danger" data-action="confirm">
                <?php esc_html_e( 'Delete', 'bits-accessible-forms' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- Live Region for Announcements -->
<div id="baf-live-region" class="baf-sr-only" aria-live="polite" aria-atomic="true"></div>
<div id="baf-live-region-assertive" class="baf-sr-only" aria-live="assertive" aria-atomic="true"></div>

<?php
/**
 * Form Builder Page Template
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
$form = $form_id ? new QAF_Form( $form_id ) : new QAF_Form();
$form_builder = new QAF_Form_Builder();
$structure_mode = new QAF_Structure_Mode();
require_once QAF_PLUGIN_DIR . 'admin/partials/model-screens.php';
?>
<div class="wrap qaf-wrap">
    <!-- Skip Links for Accessibility -->
    <nav class="qaf-skip-links" aria-label="<?php esc_attr_e( 'Skip links', 'quill-accessible-forms' ); ?>">
        <a href="#qaf-form-canvas" class="qaf-skip-link"><?php esc_html_e( 'Skip to Form Canvas', 'quill-accessible-forms' ); ?></a>
        <a href="#qaf-field-palette" class="qaf-skip-link"><?php esc_html_e( 'Skip to Field Palette', 'quill-accessible-forms' ); ?></a>
        <a href="#qaf-field-settings" class="qaf-skip-link"><?php esc_html_e( 'Skip to Field Settings', 'quill-accessible-forms' ); ?></a>
    </nav>

    <header class="qaf-builder-header">
        <h1 class="qaf-sr-only">
            <?php
            if ( $form_id ) {
                echo esc_html( sprintf( __( 'Edit Form: %s', 'quill-accessible-forms' ), $form->title ) );
            } else {
                esc_html_e( 'Create New Form', 'quill-accessible-forms' );
            }
            ?>
        </h1>
        
        <div class="qaf-header-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=qaf-forms' ) ); ?>" class="qaf-back-link" aria-label="<?php esc_attr_e( 'Back to forms list', 'quill-accessible-forms' ); ?>">
                <span aria-hidden="true">←</span>
                <?php esc_html_e( 'Back', 'quill-accessible-forms' ); ?>
            </a>
            
            <input type="text" 
                   id="qaf-form-title" 
                   class="qaf-form-title-input" 
                   value="<?php echo esc_attr( $form->title ?: __( 'Untitled Form', 'quill-accessible-forms' ) ); ?>"
                   placeholder="<?php esc_attr_e( 'Form Title', 'quill-accessible-forms' ); ?>"
                   aria-label="<?php esc_attr_e( 'Form title', 'quill-accessible-forms' ); ?>">
        </div>
        
        <div class="qaf-header-center">
            <div class="qaf-mode-switcher" role="tablist" aria-label="<?php esc_attr_e( 'Builder mode', 'quill-accessible-forms' ); ?>">
                <button type="button" 
                        role="tab" 
                        aria-selected="true" 
                        aria-controls="qaf-visual-panel"
                        id="qaf-mode-visual"
                        class="qaf-mode-btn active">
                    <?php esc_html_e( 'Visual', 'quill-accessible-forms' ); ?>
                </button>
                <button type="button" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="qaf-structure-panel"
                        id="qaf-mode-structure"
                        class="qaf-mode-btn">
                    <?php esc_html_e( 'Structure', 'quill-accessible-forms' ); ?>
                </button>
                <button type="button" 
                        role="tab" 
                        aria-selected="false" 
                        aria-controls="qaf-bfl-panel"
                        id="qaf-mode-bfl"
                        class="qaf-mode-btn">
                    <?php esc_html_e( 'QAF Form Language', 'quill-accessible-forms' ); ?>
                </button>
                <?php qaf_model_screen_tabs(); ?>
            </div>
        </div>
        
        <div class="qaf-header-right">
            <button type="button" class="qaf-btn qaf-btn-secondary" id="qaf-preview-btn">
                <span aria-hidden="true">👁️</span>
                <?php esc_html_e( 'Preview', 'quill-accessible-forms' ); ?>
            </button>
            <button type="button" class="qaf-btn qaf-btn-secondary" id="qaf-save-draft-btn">
                <?php esc_html_e( 'Save Draft', 'quill-accessible-forms' ); ?>
            </button>
            <button type="button" class="qaf-btn qaf-btn-primary" id="qaf-publish-btn">
                <?php esc_html_e( 'Publish', 'quill-accessible-forms' ); ?>
            </button>
            <p id="qaf-save-status" class="qaf-save-status" role="status" aria-live="polite" aria-atomic="true"><?php echo esc_html( $form_id ? ( $form->status === 'active' ? __( 'Published.', 'quill-accessible-forms' ) : __( 'Draft.', 'quill-accessible-forms' ) ) : '' ); ?></p>
        </div>
    </header>

    <main class="qaf-builder-main" id="qaf-builder-main">
        <!-- Visual Mode Panel -->
        <div id="qaf-visual-panel" role="tabpanel" aria-labelledby="qaf-mode-visual" class="qaf-mode-panel active">
            <div class="qaf-builder-layout">
                <!-- Field Palette -->
                <aside class="qaf-palette-panel" id="qaf-field-palette" aria-label="<?php esc_attr_e( 'Field palette', 'quill-accessible-forms' ); ?>">
                    <?php echo $form_builder->render_field_palette(); ?>
                </aside>
                
                <!-- Form Canvas -->
                <section class="qaf-canvas-panel" id="qaf-form-canvas" aria-label="<?php esc_attr_e( 'Form canvas', 'quill-accessible-forms' ); ?>">
                    <div class="qaf-canvas-header">
                        <h2 id="qaf-canvas-title" class="qaf-canvas-title">
                            <?php echo esc_html( $form->title ?: __( 'Form Canvas', 'quill-accessible-forms' ) ); ?>
                        </h2>
                        <p id="qaf-canvas-instructions" class="qaf-canvas-instructions">
                            <?php esc_html_e( 'Each question is a card, inside its section or column. Every change is saved as it is made. Use the move buttons, or Control with the up and down arrows, to reorder; a question moves among its neighbours, and between pages in Structure mode.', 'quill-accessible-forms' ); ?>
                        </p>
                    </div>

                    <!-- The cards, drawn from the form model (QAF_Form_Builder::render_canvas). -->
                    <div class="qaf-canvas-dropzone"
                         id="qaf-canvas-dropzone"
                         aria-describedby="qaf-canvas-instructions">
                        <?php echo $form_builder->render_canvas( $form_id ? $form->get_model() : QAF_Builder_Field_Ops::new_model( '' ), $form_id ); ?>
                    </div>

                    <!-- Add Field Button (keyboard accessible); F does the same while focus is in the canvas. -->
                    <div class="qaf-canvas-footer">
                        <button type="button" class="qaf-btn qaf-btn-dashed qaf-add-field-btn" id="qaf-add-field-btn" aria-haspopup="dialog" aria-keyshortcuts="F">
                            <span aria-hidden="true">+</span>
                            <?php esc_html_e( 'Add Field', 'quill-accessible-forms' ); ?>
                            <span class="qaf-shortcut-hint" aria-hidden="true">(F)</span>
                        </button>
                    </div>
                </section>
                
                <!-- Field Settings Panel -->
                <aside class="qaf-settings-panel" id="qaf-field-settings" aria-label="<?php esc_attr_e( 'Field settings', 'quill-accessible-forms' ); ?>" hidden>
                    <div class="qaf-settings-header">
                        <h3 id="qaf-settings-title"><?php esc_html_e( 'Field Settings', 'quill-accessible-forms' ); ?></h3>
                        <button type="button" class="qaf-close-settings" aria-label="<?php esc_attr_e( 'Close settings panel', 'quill-accessible-forms' ); ?>">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    
                    <div class="qaf-settings-body" id="qaf-settings-body">
                        <p class="qaf-settings-placeholder"><?php esc_html_e( 'Select a field to edit its settings.', 'quill-accessible-forms' ); ?></p>
                    </div>
                </aside>
            </div>
        </div>
        
        <!-- Structure Mode Panel -->
        <div id="qaf-structure-panel" role="tabpanel" aria-labelledby="qaf-mode-structure" class="qaf-mode-panel" hidden>
            <?php echo $structure_mode->render( $form->config, $form_id, $form->get_model() ); ?>
        </div>
        
        <!-- BFL Code Mode Panel -->
        <div id="qaf-bfl-panel" role="tabpanel" aria-labelledby="qaf-mode-bfl" class="qaf-mode-panel" hidden>
            <div class="qaf-bfl-editor-container">
                <div class="qaf-bfl-toolbar">
                    <button type="button" class="qaf-btn qaf-btn-secondary" id="qaf-bfl-sync">
                        <?php esc_html_e( 'Sync to Visual', 'quill-accessible-forms' ); ?>
                    </button>
                    <button type="button" class="qaf-btn qaf-btn-secondary" id="qaf-bfl-format">
                        <?php esc_html_e( 'Format Code', 'quill-accessible-forms' ); ?>
                    </button>
                    <span class="qaf-bfl-status" id="qaf-bfl-status" role="status" aria-live="polite"></span>
                </div>
                
                <label for="qaf-bfl-editor" class="qaf-sr-only"><?php esc_html_e( 'QAF form language editor', 'quill-accessible-forms' ); ?></label>
                <textarea id="qaf-bfl-editor" 
                          class="qaf-bfl-textarea" 
                          aria-describedby="qaf-bfl-help"
                          spellcheck="false"><?php echo esc_textarea( $form->bfl_source ? $form->bfl_source : ( $form_id ? QAF_BFL_Writer::write( $form->get_model() ) : '' ) ); ?></textarea>
                
                <div id="qaf-bfl-help" class="qaf-bfl-help">
                    <p><?php esc_html_e( 'Write the form in the QAF form language. Changes sync when you switch modes.', 'quill-accessible-forms' ); ?></p>
                </div>
                
                <div class="qaf-bfl-errors" id="qaf-bfl-errors" role="alert" aria-live="polite"></div>
            </div>
        </div>

        <!-- Rules, Quiz outcomes, Translations, Choice libraries -->
        <?php qaf_model_screen_panels(); ?>
    </main>
    
    <!-- Hidden form data -->
    <input type="hidden" id="qaf-form-id" value="<?php echo esc_attr( $form_id ); ?>">
    <input type="hidden" id="qaf-form-config" value="<?php echo esc_attr( wp_json_encode( $form->config ) ); ?>">
    <input type="hidden" id="qaf-form-settings" value="<?php echo esc_attr( wp_json_encode( $form->settings ) ); ?>">
</div>

<?php qaf_model_screen_dialogs(); ?>

<!-- Add Field (F) and Convert Type: modal dialogs, drawn from the field registry. -->
<?php qaf_builder_type_dialogs( QAF_Field_Registry::get_instance()->get_for_builder() ); ?>

<!-- Confirm Delete Dialog -->
<div id="qaf-confirm-dialog" 
     class="qaf-modal qaf-confirm-dialog" 
     role="alertdialog" 
     aria-labelledby="qaf-confirm-title" 
     aria-describedby="qaf-confirm-message"
     aria-modal="true" 
     hidden>
    <div class="qaf-modal-backdrop"></div>
    <div class="qaf-modal-content" data-qaf-focus-trap="true">
        <div class="qaf-modal-header">
            <h2 id="qaf-confirm-title"><?php esc_html_e( 'Confirm Delete', 'quill-accessible-forms' ); ?></h2>
        </div>
        <div class="qaf-modal-body">
            <p id="qaf-confirm-message"><?php esc_html_e( 'Are you sure you want to delete this field? This action cannot be undone.', 'quill-accessible-forms' ); ?></p>
        </div>
        <div class="qaf-modal-footer">
            <button type="button" class="qaf-btn qaf-btn-secondary" data-action="cancel">
                <?php esc_html_e( 'Cancel', 'quill-accessible-forms' ); ?>
            </button>
            <button type="button" class="qaf-btn qaf-btn-danger" data-action="confirm">
                <?php esc_html_e( 'Delete', 'quill-accessible-forms' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- Live Region for Announcements -->
<div id="qaf-live-region" class="qaf-sr-only" aria-live="polite" aria-atomic="true"></div>
<div id="qaf-live-region-assertive" class="qaf-sr-only" aria-live="assertive" aria-atomic="true"></div>

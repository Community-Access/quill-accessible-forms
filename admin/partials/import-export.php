<?php
/**
 * Import and Export: forms from and to Gravity Forms, WPForms, Contact
 * Form 7, Ninja Forms and Google Forms, each with its report.
 *
 * Everything works from the keyboard alone: real labels on every control,
 * help joined to its control, a status region that says what happened, and
 * the report as headings and lists.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-importers.php';
require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-exporters.php';

$qaf_help = array(
    'gravity-forms'  => __( 'In Gravity Forms, choose Forms, Import/Export, Export Forms, pick one form and download the file.', 'quill-accessible-forms' ),
    'wpforms'        => __( 'In WPForms, choose Tools, Export, Form Export, pick one form and download the file.', 'quill-accessible-forms' ),
    'contact-form-7' => __( 'In Contact Form 7, open the form and copy everything in its Form tab. The JSON from a Contact Form 7 export tool also works.', 'quill-accessible-forms' ),
    'ninja-forms'    => __( 'In Ninja Forms, choose Tools, Import/Export, Export Forms, pick the form and download the file.', 'quill-accessible-forms' ),
    'google-forms'   => __( 'Paste the form as the Google Forms API returns it (forms.get), or give the form\'s ID and an access token to fetch it.', 'quill-accessible-forms' ),
);
$qaf_forms = QAF_Form::get_all( array( 'orderby' => 'title', 'order' => 'ASC' ) );
?>
<div class="wrap qaf-wrap qaf-import-export">
    <h1><?php esc_html_e( 'Import and Export Forms', 'quill-accessible-forms' ); ?></h1>
    <p><?php esc_html_e( 'Bring a form in from another form builder, or send one of yours out to one. Each import and export ends with a report of anything that did not come across exactly, so nothing is lost without your knowing.', 'quill-accessible-forms' ); ?></p>

    <section class="qaf-import-section" aria-labelledby="qaf-import-heading">
        <h2 id="qaf-import-heading"><?php esc_html_e( 'Import a form', 'quill-accessible-forms' ); ?></h2>

        <form id="qaf-import-form" novalidate>
            <fieldset>
                <legend><?php esc_html_e( 'Where the form comes from', 'quill-accessible-forms' ); ?></legend>
                <?php foreach ( QAF_Importers::SOURCES as $key => $name ) : ?>
                    <p>
                        <input type="radio" name="source" id="qaf-import-source-<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $key ); ?>" aria-describedby="qaf-import-source-<?php echo esc_attr( $key ); ?>-help"<?php checked( $key, 'gravity-forms' ); ?>>
                        <label for="qaf-import-source-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></label>
                        <span id="qaf-import-source-<?php echo esc_attr( $key ); ?>-help" class="description"><?php echo esc_html( $qaf_help[ $key ] ); ?></span>
                    </p>
                <?php endforeach; ?>
            </fieldset>

            <p>
                <label for="qaf-import-text"><?php esc_html_e( 'The exported form', 'quill-accessible-forms' ); ?></label><br>
                <textarea id="qaf-import-text" name="export" rows="10" class="large-text code" spellcheck="false" aria-describedby="qaf-import-text-help qaf-import-error"></textarea>
                <span id="qaf-import-text-help" class="description"><?php esc_html_e( 'Paste the export here, or choose its file below; the file\'s text is put here for you to check.', 'quill-accessible-forms' ); ?></span>
            </p>
            <p>
                <label for="qaf-import-file"><?php esc_html_e( 'Export file', 'quill-accessible-forms' ); ?></label><br>
                <input type="file" id="qaf-import-file" accept=".json,.txt,.html,application/json,text/plain">
            </p>

            <div id="qaf-import-google" hidden>
                <p>
                    <label for="qaf-import-google-id"><?php esc_html_e( 'Google Form ID (instead of pasting)', 'quill-accessible-forms' ); ?></label><br>
                    <input type="text" id="qaf-import-google-id" class="regular-text" autocomplete="off" aria-describedby="qaf-import-google-id-help">
                    <span id="qaf-import-google-id-help" class="description"><?php esc_html_e( 'The long code in the form\'s address, between /d/ and /edit.', 'quill-accessible-forms' ); ?></span>
                </p>
                <p>
                    <label for="qaf-import-google-token"><?php esc_html_e( 'Google access token', 'quill-accessible-forms' ); ?></label><br>
                    <input type="password" id="qaf-import-google-token" class="regular-text" autocomplete="off" aria-describedby="qaf-import-google-token-help">
                    <span id="qaf-import-google-token-help" class="description"><?php esc_html_e( 'A token with permission to read the form. It is used once, to fetch the form, and is not stored.', 'quill-accessible-forms' ); ?></span>
                </p>
            </div>

            <p id="qaf-import-error" class="qaf-field-error" hidden></p>
            <p><button type="submit" class="button button-primary" id="qaf-import-button"><?php esc_html_e( 'Import', 'quill-accessible-forms' ); ?></button></p>
        </form>

        <div id="qaf-import-status" class="qaf-status" role="status" aria-live="polite" aria-atomic="true"></div>
        <div id="qaf-import-result" class="qaf-report" hidden></div>
    </section>

    <section class="qaf-export-section" aria-labelledby="qaf-export-heading">
        <h2 id="qaf-export-heading"><?php esc_html_e( 'Export a form', 'quill-accessible-forms' ); ?></h2>

        <?php if ( ! $qaf_forms ) : ?>
            <p><?php esc_html_e( 'There are no forms to export yet.', 'quill-accessible-forms' ); ?></p>
        <?php else : ?>
            <form id="qaf-export-form" novalidate>
                <p>
                    <label for="qaf-export-form-id"><?php esc_html_e( 'Form', 'quill-accessible-forms' ); ?></label><br>
                    <select id="qaf-export-form-id" name="form_id">
                        <?php foreach ( $qaf_forms as $qaf_form ) : ?>
                            <option value="<?php echo esc_attr( $qaf_form->id ); ?>"><?php echo esc_html( $qaf_form->title !== '' ? $qaf_form->title : sprintf( __( 'Form %d', 'quill-accessible-forms' ), $qaf_form->id ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="qaf-export-target"><?php esc_html_e( 'Export to', 'quill-accessible-forms' ); ?></label><br>
                    <select id="qaf-export-target" name="target">
                        <?php foreach ( QAF_Exporters::TARGETS as $key => $name ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p><button type="submit" class="button button-primary" id="qaf-export-button"><?php esc_html_e( 'Export', 'quill-accessible-forms' ); ?></button></p>
            </form>

            <div id="qaf-export-status" class="qaf-status" role="status" aria-live="polite" aria-atomic="true"></div>
            <div id="qaf-export-result" class="qaf-report" hidden>
                <div id="qaf-export-report"></div>
                <p>
                    <label for="qaf-export-text"><?php esc_html_e( 'The export', 'quill-accessible-forms' ); ?></label><br>
                    <textarea id="qaf-export-text" rows="12" class="large-text code" readonly spellcheck="false"></textarea>
                </p>
                <p>
                    <button type="button" class="button" id="qaf-export-download"><?php esc_html_e( 'Download the export', 'quill-accessible-forms' ); ?></button>
                    <button type="button" class="button" id="qaf-export-copy"><?php esc_html_e( 'Copy the export', 'quill-accessible-forms' ); ?></button>
                </p>
            </div>
        <?php endif; ?>
    </section>
</div>

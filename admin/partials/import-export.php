<?php
/**
 * Import and Export: forms from and to Gravity Forms, WPForms, Contact
 * Form 7, Ninja Forms and Google Forms, each with its report.
 *
 * Everything works from the keyboard alone: real labels on every control,
 * help joined to its control, a status region that says what happened, and
 * the report as headings and lists.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-importers.php';
require_once BAF_PLUGIN_DIR . 'includes/model/class-baf-exporters.php';

$baf_help = array(
    'gravity-forms'  => __( 'In Gravity Forms, choose Forms, Import/Export, Export Forms, pick one form and download the file.', 'bits-accessible-forms' ),
    'wpforms'        => __( 'In WPForms, choose Tools, Export, Form Export, pick one form and download the file.', 'bits-accessible-forms' ),
    'contact-form-7' => __( 'In Contact Form 7, open the form and copy everything in its Form tab. The JSON from a Contact Form 7 export tool also works.', 'bits-accessible-forms' ),
    'ninja-forms'    => __( 'In Ninja Forms, choose Tools, Import/Export, Export Forms, pick the form and download the file.', 'bits-accessible-forms' ),
    'google-forms'   => __( 'Paste the form as the Google Forms API returns it (forms.get), or give the form\'s ID and an access token to fetch it.', 'bits-accessible-forms' ),
);
$baf_forms = BAF_Form::get_all( array( 'orderby' => 'title', 'order' => 'ASC' ) );
?>
<div class="wrap baf-wrap baf-import-export">
    <h1><?php esc_html_e( 'Import and Export Forms', 'bits-accessible-forms' ); ?></h1>
    <p><?php esc_html_e( 'Bring a form in from another form builder, or send one of yours out to one. Each import and export ends with a report of anything that did not come across exactly, so nothing is lost without your knowing.', 'bits-accessible-forms' ); ?></p>

    <section class="baf-import-section" aria-labelledby="baf-import-heading">
        <h2 id="baf-import-heading"><?php esc_html_e( 'Import a form', 'bits-accessible-forms' ); ?></h2>

        <form id="baf-import-form" novalidate>
            <fieldset>
                <legend><?php esc_html_e( 'Where the form comes from', 'bits-accessible-forms' ); ?></legend>
                <?php foreach ( BAF_Importers::SOURCES as $key => $name ) : ?>
                    <p>
                        <input type="radio" name="source" id="baf-import-source-<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $key ); ?>" aria-describedby="baf-import-source-<?php echo esc_attr( $key ); ?>-help"<?php checked( $key, 'gravity-forms' ); ?>>
                        <label for="baf-import-source-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></label>
                        <span id="baf-import-source-<?php echo esc_attr( $key ); ?>-help" class="description"><?php echo esc_html( $baf_help[ $key ] ); ?></span>
                    </p>
                <?php endforeach; ?>
            </fieldset>

            <p>
                <label for="baf-import-text"><?php esc_html_e( 'The exported form', 'bits-accessible-forms' ); ?></label><br>
                <textarea id="baf-import-text" name="export" rows="10" class="large-text code" spellcheck="false" aria-describedby="baf-import-text-help baf-import-error"></textarea>
                <span id="baf-import-text-help" class="description"><?php esc_html_e( 'Paste the export here, or choose its file below; the file\'s text is put here for you to check.', 'bits-accessible-forms' ); ?></span>
            </p>
            <p>
                <label for="baf-import-file"><?php esc_html_e( 'Export file', 'bits-accessible-forms' ); ?></label><br>
                <input type="file" id="baf-import-file" accept=".json,.txt,.html,application/json,text/plain">
            </p>

            <div id="baf-import-google" hidden>
                <p>
                    <label for="baf-import-google-id"><?php esc_html_e( 'Google Form ID (instead of pasting)', 'bits-accessible-forms' ); ?></label><br>
                    <input type="text" id="baf-import-google-id" class="regular-text" autocomplete="off" aria-describedby="baf-import-google-id-help">
                    <span id="baf-import-google-id-help" class="description"><?php esc_html_e( 'The long code in the form\'s address, between /d/ and /edit.', 'bits-accessible-forms' ); ?></span>
                </p>
                <p>
                    <label for="baf-import-google-token"><?php esc_html_e( 'Google access token', 'bits-accessible-forms' ); ?></label><br>
                    <input type="password" id="baf-import-google-token" class="regular-text" autocomplete="off" aria-describedby="baf-import-google-token-help">
                    <span id="baf-import-google-token-help" class="description"><?php esc_html_e( 'A token with permission to read the form. It is used once, to fetch the form, and is not stored.', 'bits-accessible-forms' ); ?></span>
                </p>
            </div>

            <p id="baf-import-error" class="baf-field-error" hidden></p>
            <p><button type="submit" class="button button-primary" id="baf-import-button"><?php esc_html_e( 'Import', 'bits-accessible-forms' ); ?></button></p>
        </form>

        <div id="baf-import-status" class="baf-status" role="status" aria-live="polite" aria-atomic="true"></div>
        <div id="baf-import-result" class="baf-report" hidden></div>
    </section>

    <section class="baf-export-section" aria-labelledby="baf-export-heading">
        <h2 id="baf-export-heading"><?php esc_html_e( 'Export a form', 'bits-accessible-forms' ); ?></h2>

        <?php if ( ! $baf_forms ) : ?>
            <p><?php esc_html_e( 'There are no forms to export yet.', 'bits-accessible-forms' ); ?></p>
        <?php else : ?>
            <form id="baf-export-form" novalidate>
                <p>
                    <label for="baf-export-form-id"><?php esc_html_e( 'Form', 'bits-accessible-forms' ); ?></label><br>
                    <select id="baf-export-form-id" name="form_id">
                        <?php foreach ( $baf_forms as $baf_form ) : ?>
                            <option value="<?php echo esc_attr( $baf_form->id ); ?>"><?php echo esc_html( $baf_form->title !== '' ? $baf_form->title : sprintf( __( 'Form %d', 'bits-accessible-forms' ), $baf_form->id ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="baf-export-target"><?php esc_html_e( 'Export to', 'bits-accessible-forms' ); ?></label><br>
                    <select id="baf-export-target" name="target">
                        <?php foreach ( BAF_Exporters::TARGETS as $key => $name ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p><button type="submit" class="button button-primary" id="baf-export-button"><?php esc_html_e( 'Export', 'bits-accessible-forms' ); ?></button></p>
            </form>

            <div id="baf-export-status" class="baf-status" role="status" aria-live="polite" aria-atomic="true"></div>
            <div id="baf-export-result" class="baf-report" hidden>
                <div id="baf-export-report"></div>
                <p>
                    <label for="baf-export-text"><?php esc_html_e( 'The export', 'bits-accessible-forms' ); ?></label><br>
                    <textarea id="baf-export-text" rows="12" class="large-text code" readonly spellcheck="false"></textarea>
                </p>
                <p>
                    <button type="button" class="button" id="baf-export-download"><?php esc_html_e( 'Download the export', 'bits-accessible-forms' ); ?></button>
                    <button type="button" class="button" id="baf-export-copy"><?php esc_html_e( 'Copy the export', 'bits-accessible-forms' ); ?></button>
                </p>
            </div>
        <?php endif; ?>
    </section>
</div>

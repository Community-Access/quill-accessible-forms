<?php
/**
 * The builder's model screens: Rules, Quiz outcomes, Translations and
 * Choice libraries. Each is a tab panel with a heading, a short paragraph,
 * a table (caption, column headers, a row header per row), an Add button
 * and a status region; each edits in a modal dialog with real labels, help
 * and errors joined by aria-describedby, and an error summary that takes
 * focus. The rows and lists are filled by admin/js/baf-model-screens.js.
 *
 * form-builder.php calls baf_model_screen_panels() inside <main> and
 * baf_model_screen_dialogs() after the page, so the dialogs sit outside the
 * content that is made inert while one is open.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'baf_model_screen_tabs' ) ) {

    /**
     * The screens, in tab order: key => array( tab label ).
     *
     * @return array Screens.
     */
    function baf_model_screens() {
        return array(
            'rules'     => __( 'Rules', 'bits-accessible-forms' ),
            'libraries' => __( 'Choice libraries', 'bits-accessible-forms' ),
        );
    }

    /** The screens' tabs, after Visual, Structure and BFL Code. */
    function baf_model_screen_tabs() {
        foreach ( baf_model_screens() as $key => $label ) {
            printf(
                '<button type="button" role="tab" aria-selected="false" aria-controls="baf-%1$s-panel" id="baf-mode-%1$s" class="baf-mode-btn">%2$s</button>' . "\n",
                esc_attr( $key ),
                esc_html( $label )
            );
        }
    }

    /**
     * The start of a screen: panel, heading, paragraph, the note shown for a
     * form not yet saved, the error summary and the status region.
     *
     * @param string $key   Screen key.
     * @param string $title Heading.
     * @param string $intro Paragraph.
     */
    function baf_model_panel_start( $key, $title, $intro ) {
        $k = esc_attr( $key );
        ?>
        <div id="baf-<?php echo $k; ?>-panel" role="tabpanel" aria-labelledby="baf-mode-<?php echo $k; ?>" class="baf-mode-panel baf-model-panel" hidden>
            <h2 id="baf-<?php echo $k; ?>-heading"><?php echo esc_html( $title ); ?></h2>
            <p><?php echo esc_html( $intro ); ?></p>
            <p class="baf-model-needs-save" hidden><?php esc_html_e( 'Save the form first, with Save Draft or Publish; then this screen can edit it.', 'bits-accessible-forms' ); ?></p>
            <div id="baf-<?php echo $k; ?>-summary" class="baf-error-summary" tabindex="-1" hidden></div>
            <div id="baf-<?php echo $k; ?>-status" class="baf-model-status baf-status" role="status" aria-live="polite" aria-atomic="true"></div>
        <?php
    }

    /**
     * A screen's table, its "nothing yet" line and its Add button.
     *
     * @param string $key     Screen key.
     * @param string $caption Caption.
     * @param array  $columns Column headings after the number (the last is Actions).
     * @param string $empty   Shown when the list is empty.
     * @param string $add     The Add button's text.
     */
    function baf_model_table( $key, $caption, $columns, $empty, $add ) {
        $k = esc_attr( $key );
        ?>
        <div class="baf-model-body" hidden>
            <table class="widefat striped baf-model-table" id="baf-<?php echo $k; ?>-table" hidden>
                <caption><?php echo esc_html( $caption ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Number', 'bits-accessible-forms' ); ?></th>
                        <?php foreach ( $columns as $col ) : ?>
                            <th scope="col"><?php echo esc_html( $col ); ?></th>
                        <?php endforeach; ?>
                        <th scope="col"><?php esc_html_e( 'Actions', 'bits-accessible-forms' ); ?></th>
                    </tr>
                </thead>
                <tbody id="baf-<?php echo $k; ?>-rows"></tbody>
            </table>
            <p id="baf-<?php echo $k; ?>-empty"><?php echo esc_html( $empty ); ?></p>
            <p><button type="button" class="button button-primary" id="baf-<?php echo $k; ?>-add"><?php echo esc_html( $add ); ?></button></p>
        </div>
        <?php
    }

    /**
     * An expression text box: label, help, the live check line (the sentence
     * or the error), the error line, a polite region that says the check
     * once typing pauses, and "Insert a field".
     *
     * @param string $id    Input ID.
     * @param string $label Label.
     * @param string $help  Help, with an example.
     */
    function baf_model_expression_field( $id, $label, $help ) {
        $i = esc_attr( $id );
        ?>
        <div class="baf-expression-field">
            <label for="<?php echo $i; ?>"><?php echo esc_html( $label ); ?></label><br>
            <input type="text" id="<?php echo $i; ?>" class="large-text code" spellcheck="false" autocomplete="off" aria-describedby="<?php echo $i; ?>-help <?php echo $i; ?>-check <?php echo $i; ?>-error">
            <span id="<?php echo $i; ?>-help" class="description"><?php echo esc_html( $help ); ?></span>
            <span id="<?php echo $i; ?>-check" class="baf-expression-check"></span>
            <span id="<?php echo $i; ?>-error" class="baf-field-error" hidden></span>
            <span id="<?php echo $i; ?>-live" class="baf-sr-only" role="status" aria-live="polite" aria-atomic="true"></span>
            <br><button type="button" class="button button-small" id="<?php echo $i; ?>-insert" aria-expanded="false" aria-controls="<?php echo $i; ?>-fields"><?php esc_html_e( 'Insert a field', 'bits-accessible-forms' ); ?></button>
            <div id="<?php echo $i; ?>-fields" class="baf-fields-list" hidden>
                <label for="<?php echo $i; ?>-fields-filter"><?php esc_html_e( 'Find a field by its label', 'bits-accessible-forms' ); ?></label>
                <input type="search" id="<?php echo $i; ?>-fields-filter" autocomplete="off" aria-describedby="<?php echo $i; ?>-fields-help">
                <span id="<?php echo $i; ?>-fields-help" class="description"><?php esc_html_e( 'Choose a field to put its ID in at the cursor. Enter takes the first one; Escape closes the list.', 'bits-accessible-forms' ); ?></span>
                <span class="baf-sr-only" role="status" aria-live="polite"></span>
                <ul></ul>
            </div>
        </div>
        <?php
    }

    /**
     * The start of a dialog: the wrapper, the [role=dialog] box, its heading,
     * the form and the error summary.
     *
     * @param string $id    Dialog ID.
     * @param string $title Heading (the script sets "Add" or "Edit").
     * @param string $size  Optional class modifier: "wide".
     */
    function baf_model_dialog_start( $id, $title, $size = '' ) {
        $i = esc_attr( $id );
        ?>
        <div id="<?php echo $i; ?>" class="baf-model-modal" hidden>
            <div class="baf-model-backdrop"></div>
            <div class="baf-modal-content baf-model-dialog<?php echo $size ? ' baf-model-dialog-' . esc_attr( $size ) : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo $i; ?>-title">
                <form novalidate>
                    <div class="baf-modal-header">
                        <h2 id="<?php echo $i; ?>-title"><?php echo esc_html( $title ); ?></h2>
                    </div>
                    <div class="baf-modal-body">
                        <div id="<?php echo $i; ?>-summary" class="baf-error-summary" tabindex="-1" hidden></div>
        <?php
    }

    /**
     * The end of a dialog: Cancel and the save button.
     *
     * @param string $save The save button's text.
     */
    function baf_model_dialog_end( $save ) {
        ?>
                    </div>
                    <div class="baf-modal-footer">
                        <button type="button" class="button" data-baf-cancel><?php esc_html_e( 'Cancel', 'bits-accessible-forms' ); ?></button>
                        <button type="submit" class="button button-primary"><?php echo esc_html( $save ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /** The screens' panels, inside the builder's <main>. */
    function baf_model_screen_panels() {
        // Rules.
        baf_model_panel_start(
            'rules',
            __( 'Rules', 'bits-accessible-forms' ),
            __( 'Rules show, hide, require or set questions, or skip pages, when a condition holds. They apply in the order listed. Each is read as a sentence.', 'bits-accessible-forms' )
        );
        baf_model_table(
            'rules',
            __( 'Rules, in the order they apply', 'bits-accessible-forms' ),
            array( __( 'Rule', 'bits-accessible-forms' ), __( 'Targets', 'bits-accessible-forms' ) ),
            __( 'There are no rules yet.', 'bits-accessible-forms' ),
            __( 'Add rule', 'bits-accessible-forms' )
        );
        echo "</div>\n";

        // Choice libraries.
        baf_model_panel_start(
            'libraries',
            __( 'Choice libraries', 'bits-accessible-forms' ),
            __( 'A library is a list of choices that several questions can share. Change it here and every question that uses it changes too.', 'bits-accessible-forms' )
        );
        baf_model_table(
            'libraries',
            __( 'This form\'s libraries', 'bits-accessible-forms' ),
            array( __( 'Name', 'bits-accessible-forms' ), __( 'Choices', 'bits-accessible-forms' ), __( 'Used by', 'bits-accessible-forms' ) ),
            __( 'This form has no libraries of its own yet.', 'bits-accessible-forms' ),
            __( 'Add library', 'bits-accessible-forms' )
        );
        ?>
            <div class="baf-model-body" hidden>
                <h3 id="baf-builtin-heading"><?php esc_html_e( 'BAF\'s built-in libraries', 'bits-accessible-forms' ); ?></h3>
                <p id="baf-builtin-intro"><?php esc_html_e( 'Any question can use these by name. Copy one into this form when the form needs its own version, for example a shorter list of countries.', 'bits-accessible-forms' ); ?></p>
                <table class="widefat striped baf-model-table" id="baf-builtin-table" aria-describedby="baf-builtin-intro">
                    <caption><?php esc_html_e( 'Built-in libraries', 'bits-accessible-forms' ); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Library', 'bits-accessible-forms' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Name in BFL', 'bits-accessible-forms' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Actions', 'bits-accessible-forms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="baf-builtin-rows"></tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /** The screens' dialogs, after the builder's page. */
    function baf_model_screen_dialogs() {
        ?>
        <div id="baf-model-confirm" class="baf-model-modal" hidden>
            <div class="baf-model-backdrop"></div>
            <div class="baf-modal-content baf-model-dialog" role="alertdialog" aria-modal="true" aria-labelledby="baf-model-confirm-title" aria-describedby="baf-model-confirm-message">
                <div class="baf-modal-header">
                    <h2 id="baf-model-confirm-title"><?php esc_html_e( 'Delete?', 'bits-accessible-forms' ); ?></h2>
                </div>
                <div class="baf-modal-body">
                    <p id="baf-model-confirm-message"></p>
                </div>
                <div class="baf-modal-footer">
                    <button type="button" class="button" data-baf-cancel><?php esc_html_e( 'Cancel', 'bits-accessible-forms' ); ?></button>
                    <button type="button" class="button button-primary button-link-delete" data-baf-confirm><?php esc_html_e( 'Delete', 'bits-accessible-forms' ); ?></button>
                </div>
            </div>
        </div>

        <?php baf_model_dialog_start( 'baf-rule-dialog', __( 'Edit rule', 'bits-accessible-forms' ) ); ?>
            <p>
                <label for="baf-rule-action"><?php esc_html_e( 'What the rule does', 'bits-accessible-forms' ); ?></label><br>
                <select id="baf-rule-action" aria-describedby="baf-rule-action-error">
                    <option value="show"><?php esc_html_e( 'Show', 'bits-accessible-forms' ); ?></option>
                    <option value="hide"><?php esc_html_e( 'Hide', 'bits-accessible-forms' ); ?></option>
                    <option value="require"><?php esc_html_e( 'Require', 'bits-accessible-forms' ); ?></option>
                    <option value="optional"><?php esc_html_e( 'Make optional', 'bits-accessible-forms' ); ?></option>
                    <option value="enable"><?php esc_html_e( 'Enable', 'bits-accessible-forms' ); ?></option>
                    <option value="disable"><?php esc_html_e( 'Disable', 'bits-accessible-forms' ); ?></option>
                    <option value="set"><?php esc_html_e( 'Set a value', 'bits-accessible-forms' ); ?></option>
                    <option value="skip_to"><?php esc_html_e( 'Skip to a page', 'bits-accessible-forms' ); ?></option>
                </select>
                <span id="baf-rule-action-error" class="baf-field-error" hidden></span>
            </p>
            <fieldset id="baf-rule-targets" aria-describedby="baf-rule-targets-help baf-rule-targets-error">
                <legend id="baf-rule-targets-legend"><?php esc_html_e( 'Targets', 'bits-accessible-forms' ); ?></legend>
                <span id="baf-rule-targets-help" class="description"><?php esc_html_e( 'The questions, sections or pages the rule acts on. For Skip to a page, choose one page.', 'bits-accessible-forms' ); ?></span>
                <p>
                    <label for="baf-rule-targets-filter"><?php esc_html_e( 'Filter the targets', 'bits-accessible-forms' ); ?></label><br>
                    <input type="search" id="baf-rule-targets-filter" autocomplete="off" aria-controls="baf-rule-targets-list">
                    <span class="baf-sr-only" id="baf-rule-targets-count" role="status" aria-live="polite"></span>
                </p>
                <ul id="baf-rule-targets-list" class="baf-check-list"></ul>
                <span id="baf-rule-targets-error" class="baf-field-error" hidden></span>
            </fieldset>
            <?php
            baf_model_expression_field(
                'baf-rule-when',
                __( 'When', 'bits-accessible-forms' ),
                __( 'The condition, for example #contact_by is "phone". The line after the box reads it back as a sentence, or says what is wrong.', 'bits-accessible-forms' )
            );
            ?>
            <div id="baf-rule-value-row" hidden>
                <?php
                baf_model_expression_field(
                    'baf-rule-value',
                    __( 'Value', 'bits-accessible-forms' ),
                    __( 'What to set the targets to: a value such as "none" or 10, or a calculation such as #price * #quantity.', 'bits-accessible-forms' )
                );
                ?>
            </div>
        <?php
        baf_model_dialog_end( __( 'Save rule', 'bits-accessible-forms' ) );

        baf_model_dialog_start( 'baf-library-dialog', __( 'Edit library', 'bits-accessible-forms' ), 'wide' );
        ?>
            <div id="baf-library-dialog-status" class="baf-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
            <p>
                <label for="baf-library-name"><?php esc_html_e( 'Name', 'bits-accessible-forms' ); ?></label><br>
                <input type="text" id="baf-library-name" class="regular-text code" autocomplete="off" spellcheck="false" aria-describedby="baf-library-name-help baf-library-name-error">
                <span id="baf-library-name-help" class="description"><?php esc_html_e( 'Letters, digits and underscores, such as sizes. Questions name the library this way; renaming it here renames it in them too.', 'bits-accessible-forms' ); ?></span>
                <span id="baf-library-name-error" class="baf-field-error" hidden></span>
            </p>
            <table class="widefat baf-model-table baf-choice-grid" id="baf-library-choices" aria-describedby="baf-library-choices-help">
                <caption><?php esc_html_e( 'Choices', 'bits-accessible-forms' ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Number', 'bits-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Label', 'bits-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Value', 'bits-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Price', 'bits-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Points', 'bits-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Actions', 'bits-accessible-forms' ); ?></th>
                    </tr>
                </thead>
                <tbody id="baf-library-choice-rows"></tbody>
            </table>
            <span id="baf-library-choices-help" class="description"><?php esc_html_e( 'The label is what people see; the value is what is stored. A value left empty is made from the label. Price and points may be left empty.', 'bits-accessible-forms' ); ?></span>
            <p><button type="button" class="button" id="baf-library-add-choice"><?php esc_html_e( 'Add choice', 'bits-accessible-forms' ); ?></button></p>
            <fieldset>
                <legend><?php esc_html_e( 'Paste choices', 'bits-accessible-forms' ); ?></legend>
                <label for="baf-library-paste"><?php esc_html_e( 'Choices, one a line', 'bits-accessible-forms' ); ?></label><br>
                <textarea id="baf-library-paste" rows="5" class="large-text code" spellcheck="false" aria-describedby="baf-library-paste-help baf-library-paste-error"></textarea>
                <span id="baf-library-paste-help" class="description"><?php esc_html_e( 'As BFL writes them: Small, or s|Small for a value and a label, or Small:10 for a price. The + at the start may be left off.', 'bits-accessible-forms' ); ?></span>
                <span id="baf-library-paste-error" class="baf-field-error" hidden></span>
                <br><button type="button" class="button" id="baf-library-paste-add"><?php esc_html_e( 'Add the pasted choices', 'bits-accessible-forms' ); ?></button>
            </fieldset>
        <?php
        baf_model_dialog_end( __( 'Save library', 'bits-accessible-forms' ) );
    }
}

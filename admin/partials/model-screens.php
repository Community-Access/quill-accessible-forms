<?php
/**
 * The builder's model screens: Rules, Quiz outcomes, Translations and
 * Choice libraries. Each is a tab panel with a heading, a short paragraph,
 * a table (caption, column headers, a row header per row), an Add button
 * and a status region; each edits in a modal dialog with real labels, help
 * and errors joined by aria-describedby, and an error summary that takes
 * focus. The rows and lists are filled by admin/js/qaf-model-screens.js.
 *
 * form-builder.php calls qaf_model_screen_panels() inside <main> and
 * qaf_model_screen_dialogs() after the page, so the dialogs sit outside the
 * content that is made inert while one is open.
 *
 * @package QAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'qaf_builder_type_dialogs' ) ) {

    /**
     * The Visual mode's two type dialogs: Add a question (the Add Field
     * button, or F in the canvas) and Convert type. Both list the field
     * registry's types by category; both are modal, with the page behind
     * made inert, Escape to close and focus returned to the opener.
     *
     * @param array $categories QAF_Field_Registry::get_for_builder(): id => array( label, fields => list of array( type, label ) ).
     */
    function qaf_builder_type_dialogs( $categories ) {
        ?>
        <div id="qaf-picker-dialog" class="qaf-model-modal" hidden>
            <div class="qaf-model-backdrop"></div>
            <div class="qaf-modal-content qaf-model-dialog" role="dialog" aria-modal="true" aria-labelledby="qaf-picker-dialog-title" aria-describedby="qaf-picker-intro">
                <div class="qaf-modal-header">
                    <h2 id="qaf-picker-dialog-title"><?php esc_html_e( 'Add a question', 'quill-accessible-forms' ); ?></h2>
                </div>
                <div class="qaf-modal-body">
                    <p id="qaf-picker-intro"><?php esc_html_e( 'Choose the type of question. It is added at the end of the last page and saved straight away.', 'quill-accessible-forms' ); ?></p>
                    <p>
                        <label for="qaf-picker-filter"><?php esc_html_e( 'Find a type', 'quill-accessible-forms' ); ?></label><br>
                        <input type="search" id="qaf-picker-filter" class="regular-text" autocomplete="off" aria-controls="qaf-picker-types" aria-describedby="qaf-picker-count">
                        <span id="qaf-picker-count" class="qaf-sr-only" role="status" aria-live="polite"></span>
                    </p>
                    <div id="qaf-picker-types" class="qaf-picker-types">
                        <?php foreach ( $categories as $cat_id => $category ) : ?>
                            <div class="qaf-picker-category" data-category="<?php echo esc_attr( $cat_id ); ?>">
                                <h3 id="qaf-picker-cat-<?php echo esc_attr( $cat_id ); ?>"><?php echo esc_html( $category['label'] ); ?></h3>
                                <ul aria-labelledby="qaf-picker-cat-<?php echo esc_attr( $cat_id ); ?>">
                                    <?php foreach ( $category['fields'] as $type ) : ?>
                                        <li><button type="button" class="button-link qaf-picker-type" data-field-type="<?php echo esc_attr( $type['type'] ); ?>"><?php echo esc_html( $type['label'] ); ?></button></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="qaf-modal-footer">
                    <button type="button" class="button" data-qaf-cancel><?php esc_html_e( 'Cancel', 'quill-accessible-forms' ); ?></button>
                </div>
            </div>
        </div>

        <?php qaf_model_dialog_start( 'qaf-convert-dialog', __( 'Convert question', 'quill-accessible-forms' ) ); ?>
            <p id="qaf-convert-current"></p>
            <p>
                <label for="qaf-convert-type"><?php esc_html_e( 'New type', 'quill-accessible-forms' ); ?></label><br>
                <select id="qaf-convert-type" aria-describedby="qaf-convert-type-help qaf-convert-type-error">
                    <option value=""><?php esc_html_e( 'Choose a type', 'quill-accessible-forms' ); ?></option>
                    <?php foreach ( $categories as $category ) : ?>
                        <optgroup label="<?php echo esc_attr( $category['label'] ); ?>">
                            <?php foreach ( $category['fields'] as $type ) : ?>
                                <option value="<?php echo esc_attr( $type['type'] ); ?>"><?php echo esc_html( $type['label'] ); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <span id="qaf-convert-type-help" class="description"><?php esc_html_e( 'Its label, help, ID and the rules that name it are kept. What else changes is listed after you choose.', 'quill-accessible-forms' ); ?></span>
                <span id="qaf-convert-type-error" class="qaf-field-error" hidden></span>
            </p>
            <div id="qaf-convert-preview" aria-live="polite" aria-atomic="true">
                <h3 id="qaf-convert-preview-heading" hidden><?php esc_html_e( 'What will change', 'quill-accessible-forms' ); ?></h3>
                <ul id="qaf-convert-changes" aria-labelledby="qaf-convert-preview-heading"></ul>
            </div>
        <?php
        qaf_model_dialog_end( __( 'Convert', 'quill-accessible-forms' ) );
    }
}

if ( ! function_exists( 'qaf_model_screen_tabs' ) ) {

    /**
     * The screens, in tab order: key => array( tab label ).
     *
     * @return array Screens.
     */
    function qaf_model_screens() {
        return array(
            'rules'        => __( 'Rules', 'quill-accessible-forms' ),
            'outcomes'     => __( 'Quiz outcomes', 'quill-accessible-forms' ),
            'translations' => __( 'Translations', 'quill-accessible-forms' ),
            'libraries'    => __( 'Choice libraries', 'quill-accessible-forms' ),
        );
    }

    /** The screens' tabs, after Visual, Structure and BFL Code. */
    function qaf_model_screen_tabs() {
        foreach ( qaf_model_screens() as $key => $label ) {
            printf(
                '<button type="button" role="tab" aria-selected="false" aria-controls="qaf-%1$s-panel" id="qaf-mode-%1$s" class="qaf-mode-btn">%2$s</button>' . "\n",
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
    function qaf_model_panel_start( $key, $title, $intro ) {
        $k = esc_attr( $key );
        ?>
        <div id="qaf-<?php echo $k; ?>-panel" role="tabpanel" aria-labelledby="qaf-mode-<?php echo $k; ?>" class="qaf-mode-panel qaf-model-panel" hidden>
            <h2 id="qaf-<?php echo $k; ?>-heading"><?php echo esc_html( $title ); ?></h2>
            <p><?php echo esc_html( $intro ); ?></p>
            <p class="qaf-model-needs-save" hidden><?php esc_html_e( 'This form is not made yet. Add a question in the Visual mode, or choose Save Draft, and it is made; then this screen can edit it.', 'quill-accessible-forms' ); ?></p>
            <div id="qaf-<?php echo $k; ?>-summary" class="qaf-error-summary" tabindex="-1" hidden></div>
            <div id="qaf-<?php echo $k; ?>-status" class="qaf-model-status qaf-status" role="status" aria-live="polite" aria-atomic="true"></div>
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
    function qaf_model_table( $key, $caption, $columns, $empty, $add ) {
        $k = esc_attr( $key );
        ?>
        <div class="qaf-model-body" hidden>
            <table class="widefat striped qaf-model-table" id="qaf-<?php echo $k; ?>-table" hidden>
                <caption><?php echo esc_html( $caption ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Number', 'quill-accessible-forms' ); ?></th>
                        <?php foreach ( $columns as $col ) : ?>
                            <th scope="col"><?php echo esc_html( $col ); ?></th>
                        <?php endforeach; ?>
                        <th scope="col"><?php esc_html_e( 'Actions', 'quill-accessible-forms' ); ?></th>
                    </tr>
                </thead>
                <tbody id="qaf-<?php echo $k; ?>-rows"></tbody>
            </table>
            <p id="qaf-<?php echo $k; ?>-empty"><?php echo esc_html( $empty ); ?></p>
            <p><button type="button" class="button button-primary" id="qaf-<?php echo $k; ?>-add"><?php echo esc_html( $add ); ?></button></p>
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
    function qaf_model_expression_field( $id, $label, $help ) {
        $i = esc_attr( $id );
        ?>
        <div class="qaf-expression-field">
            <label for="<?php echo $i; ?>"><?php echo esc_html( $label ); ?></label><br>
            <input type="text" id="<?php echo $i; ?>" class="large-text code" spellcheck="false" autocomplete="off" aria-describedby="<?php echo $i; ?>-help <?php echo $i; ?>-check <?php echo $i; ?>-error">
            <span id="<?php echo $i; ?>-help" class="description"><?php echo esc_html( $help ); ?></span>
            <span id="<?php echo $i; ?>-check" class="qaf-expression-check"></span>
            <span id="<?php echo $i; ?>-error" class="qaf-field-error" hidden></span>
            <span id="<?php echo $i; ?>-live" class="qaf-sr-only" role="status" aria-live="polite" aria-atomic="true"></span>
            <br><button type="button" class="button button-small" id="<?php echo $i; ?>-insert" aria-expanded="false" aria-controls="<?php echo $i; ?>-fields"><?php esc_html_e( 'Insert a field', 'quill-accessible-forms' ); ?></button>
            <div id="<?php echo $i; ?>-fields" class="qaf-fields-list" hidden>
                <label for="<?php echo $i; ?>-fields-filter"><?php esc_html_e( 'Find a field by its label', 'quill-accessible-forms' ); ?></label>
                <input type="search" id="<?php echo $i; ?>-fields-filter" autocomplete="off" aria-describedby="<?php echo $i; ?>-fields-help">
                <span id="<?php echo $i; ?>-fields-help" class="description"><?php esc_html_e( 'Choose a field to put its ID in at the cursor. Enter takes the first one; Escape closes the list.', 'quill-accessible-forms' ); ?></span>
                <span class="qaf-sr-only" role="status" aria-live="polite"></span>
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
    function qaf_model_dialog_start( $id, $title, $size = '' ) {
        $i = esc_attr( $id );
        ?>
        <div id="<?php echo $i; ?>" class="qaf-model-modal" hidden>
            <div class="qaf-model-backdrop"></div>
            <div class="qaf-modal-content qaf-model-dialog<?php echo $size ? ' qaf-model-dialog-' . esc_attr( $size ) : ''; ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo $i; ?>-title">
                <form novalidate>
                    <div class="qaf-modal-header">
                        <h2 id="<?php echo $i; ?>-title"><?php echo esc_html( $title ); ?></h2>
                    </div>
                    <div class="qaf-modal-body">
                        <div id="<?php echo $i; ?>-summary" class="qaf-error-summary" tabindex="-1" hidden></div>
        <?php
    }

    /**
     * The end of a dialog: Cancel and the save button.
     *
     * @param string $save The save button's text.
     */
    function qaf_model_dialog_end( $save ) {
        ?>
                    </div>
                    <div class="qaf-modal-footer">
                        <button type="button" class="button" data-qaf-cancel><?php esc_html_e( 'Cancel', 'quill-accessible-forms' ); ?></button>
                        <button type="submit" class="button button-primary"><?php echo esc_html( $save ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /** The screens' panels, inside the builder's <main>. */
    function qaf_model_screen_panels() {
        // Rules.
        qaf_model_panel_start(
            'rules',
            __( 'Rules', 'quill-accessible-forms' ),
            __( 'Rules show, hide, require or set questions, or skip pages, when a condition holds. They apply in the order listed. Each is read as a sentence.', 'quill-accessible-forms' )
        );
        qaf_model_table(
            'rules',
            __( 'Rules, in the order they apply', 'quill-accessible-forms' ),
            array( __( 'Rule', 'quill-accessible-forms' ), __( 'Targets', 'quill-accessible-forms' ) ),
            __( 'There are no rules yet.', 'quill-accessible-forms' ),
            __( 'Add rule', 'quill-accessible-forms' )
        );
        echo "</div>\n";

        // Quiz outcomes.
        qaf_model_panel_start(
            'outcomes',
            __( 'Quiz outcomes', 'quill-accessible-forms' ),
            __( 'When a quiz is sent, the first outcome whose condition holds gives the person its message. A condition can use the score, such as score >= 5.', 'quill-accessible-forms' )
        );
        ?>
            <div class="qaf-model-body" hidden>
                <form id="qaf-quiz-settings" novalidate>
                    <fieldset>
                        <legend><?php esc_html_e( 'Quiz settings', 'quill-accessible-forms' ); ?></legend>
                        <p>
                            <input type="checkbox" id="qaf-quiz-show-score">
                            <label for="qaf-quiz-show-score"><?php esc_html_e( 'Show the score', 'quill-accessible-forms' ); ?></label>
                        </p>
                        <p>
                            <label for="qaf-quiz-pass-mark"><?php esc_html_e( 'Pass mark', 'quill-accessible-forms' ); ?></label><br>
                            <input type="text" inputmode="decimal" id="qaf-quiz-pass-mark" class="small-text" autocomplete="off" aria-describedby="qaf-quiz-pass-mark-help qaf-quiz-pass-mark-error">
                            <span id="qaf-quiz-pass-mark-help" class="description"><?php esc_html_e( 'The score that counts as a pass. Leave it empty for none.', 'quill-accessible-forms' ); ?></span>
                            <span id="qaf-quiz-pass-mark-error" class="qaf-field-error" hidden></span>
                        </p>
                        <p><button type="submit" class="button" id="qaf-quiz-settings-save"><?php esc_html_e( 'Save quiz settings', 'quill-accessible-forms' ); ?></button></p>
                    </fieldset>
                </form>
            </div>
        <?php
        qaf_model_table(
            'outcomes',
            __( 'Outcomes, in the order they are tried', 'quill-accessible-forms' ),
            array( __( 'ID', 'quill-accessible-forms' ), __( 'When', 'quill-accessible-forms' ), __( 'Message', 'quill-accessible-forms' ) ),
            __( 'There are no outcomes yet.', 'quill-accessible-forms' ),
            __( 'Add outcome', 'quill-accessible-forms' )
        );
        ?>
            <div class="qaf-model-body" hidden>
                <h3 id="qaf-outcome-try-heading"><?php esc_html_e( 'Try it', 'quill-accessible-forms' ); ?></h3>
                <form id="qaf-outcome-try" novalidate>
                    <p>
                        <label for="qaf-outcome-try-score"><?php esc_html_e( 'A score to try', 'quill-accessible-forms' ); ?></label><br>
                        <input type="text" inputmode="decimal" id="qaf-outcome-try-score" class="small-text" autocomplete="off" aria-describedby="qaf-outcome-try-help">
                        <span id="qaf-outcome-try-help" class="description"><?php esc_html_e( 'Says which outcome that score would give, using the outcomes as saved.', 'quill-accessible-forms' ); ?></span>
                        <button type="submit" class="button"><?php esc_html_e( 'Try it', 'quill-accessible-forms' ); ?></button>
                    </p>
                    <p id="qaf-outcome-try-result" aria-live="polite" aria-atomic="true"></p>
                </form>
            </div>
        </div>
        <?php

        // Translations.
        qaf_model_panel_start(
            'translations',
            __( 'Translations', 'quill-accessible-forms' ),
            __( 'Each language holds the form\'s texts in that language: titles, labels, help, choices and messages. A text left empty shows the form\'s own text instead.', 'quill-accessible-forms' )
        );
        qaf_model_table(
            'translations',
            __( 'Languages', 'quill-accessible-forms' ),
            array( __( 'Code', 'quill-accessible-forms' ), __( 'Language', 'quill-accessible-forms' ), __( 'Translated', 'quill-accessible-forms' ) ),
            __( 'This form has no translations yet.', 'quill-accessible-forms' ),
            __( 'Add language', 'quill-accessible-forms' )
        );
        echo "</div>\n";

        // Choice libraries.
        qaf_model_panel_start(
            'libraries',
            __( 'Choice libraries', 'quill-accessible-forms' ),
            __( 'A library is a list of choices that several questions can share. Change it here and every question that uses it changes too.', 'quill-accessible-forms' )
        );
        qaf_model_table(
            'libraries',
            __( 'This form\'s libraries', 'quill-accessible-forms' ),
            array( __( 'Name', 'quill-accessible-forms' ), __( 'Choices', 'quill-accessible-forms' ), __( 'Used by', 'quill-accessible-forms' ) ),
            __( 'This form has no libraries of its own yet.', 'quill-accessible-forms' ),
            __( 'Add library', 'quill-accessible-forms' )
        );
        ?>
            <div class="qaf-model-body" hidden>
                <h3 id="qaf-builtin-heading"><?php esc_html_e( 'QAF\'s built-in libraries', 'quill-accessible-forms' ); ?></h3>
                <p id="qaf-builtin-intro"><?php esc_html_e( 'Any question can use these by name. Copy one into this form when the form needs its own version, for example a shorter list of countries.', 'quill-accessible-forms' ); ?></p>
                <table class="widefat striped qaf-model-table" id="qaf-builtin-table" aria-describedby="qaf-builtin-intro">
                    <caption><?php esc_html_e( 'Built-in libraries', 'quill-accessible-forms' ); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Library', 'quill-accessible-forms' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Name in the QAF form language', 'quill-accessible-forms' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Actions', 'quill-accessible-forms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="qaf-builtin-rows"></tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /** The screens' dialogs, after the builder's page. */
    function qaf_model_screen_dialogs() {
        ?>
        <div id="qaf-model-confirm" class="qaf-model-modal" hidden>
            <div class="qaf-model-backdrop"></div>
            <div class="qaf-modal-content qaf-model-dialog" role="alertdialog" aria-modal="true" aria-labelledby="qaf-model-confirm-title" aria-describedby="qaf-model-confirm-message">
                <div class="qaf-modal-header">
                    <h2 id="qaf-model-confirm-title"><?php esc_html_e( 'Delete?', 'quill-accessible-forms' ); ?></h2>
                </div>
                <div class="qaf-modal-body">
                    <p id="qaf-model-confirm-message"></p>
                </div>
                <div class="qaf-modal-footer">
                    <button type="button" class="button" data-qaf-cancel><?php esc_html_e( 'Cancel', 'quill-accessible-forms' ); ?></button>
                    <button type="button" class="button button-primary button-link-delete" data-qaf-confirm><?php esc_html_e( 'Delete', 'quill-accessible-forms' ); ?></button>
                </div>
            </div>
        </div>

        <?php qaf_model_dialog_start( 'qaf-rule-dialog', __( 'Edit rule', 'quill-accessible-forms' ) ); ?>
            <p>
                <label for="qaf-rule-action"><?php esc_html_e( 'What the rule does', 'quill-accessible-forms' ); ?></label><br>
                <select id="qaf-rule-action" aria-describedby="qaf-rule-action-error">
                    <option value="show"><?php esc_html_e( 'Show', 'quill-accessible-forms' ); ?></option>
                    <option value="hide"><?php esc_html_e( 'Hide', 'quill-accessible-forms' ); ?></option>
                    <option value="require"><?php esc_html_e( 'Require', 'quill-accessible-forms' ); ?></option>
                    <option value="optional"><?php esc_html_e( 'Make optional', 'quill-accessible-forms' ); ?></option>
                    <option value="enable"><?php esc_html_e( 'Enable', 'quill-accessible-forms' ); ?></option>
                    <option value="disable"><?php esc_html_e( 'Disable', 'quill-accessible-forms' ); ?></option>
                    <option value="set"><?php esc_html_e( 'Set a value', 'quill-accessible-forms' ); ?></option>
                    <option value="skip_to"><?php esc_html_e( 'Skip to a page', 'quill-accessible-forms' ); ?></option>
                </select>
                <span id="qaf-rule-action-error" class="qaf-field-error" hidden></span>
            </p>
            <fieldset id="qaf-rule-targets" aria-describedby="qaf-rule-targets-help qaf-rule-targets-error">
                <legend id="qaf-rule-targets-legend"><?php esc_html_e( 'Targets', 'quill-accessible-forms' ); ?></legend>
                <span id="qaf-rule-targets-help" class="description"><?php esc_html_e( 'The questions, sections or pages the rule acts on. For Skip to a page, choose one page.', 'quill-accessible-forms' ); ?></span>
                <p>
                    <label for="qaf-rule-targets-filter"><?php esc_html_e( 'Filter the targets', 'quill-accessible-forms' ); ?></label><br>
                    <input type="search" id="qaf-rule-targets-filter" autocomplete="off" aria-controls="qaf-rule-targets-list">
                    <span class="qaf-sr-only" id="qaf-rule-targets-count" role="status" aria-live="polite"></span>
                </p>
                <ul id="qaf-rule-targets-list" class="qaf-check-list"></ul>
                <span id="qaf-rule-targets-error" class="qaf-field-error" hidden></span>
            </fieldset>
            <?php
            qaf_model_expression_field(
                'qaf-rule-when',
                __( 'When', 'quill-accessible-forms' ),
                __( 'The condition, for example #contact_by is "phone". The line after the box reads it back as a sentence, or says what is wrong.', 'quill-accessible-forms' )
            );
            ?>
            <div id="qaf-rule-value-row" hidden>
                <?php
                qaf_model_expression_field(
                    'qaf-rule-value',
                    __( 'Value', 'quill-accessible-forms' ),
                    __( 'What to set the targets to: a value such as "none" or 10, or a calculation such as #price * #quantity.', 'quill-accessible-forms' )
                );
                ?>
            </div>
        <?php
        qaf_model_dialog_end( __( 'Save rule', 'quill-accessible-forms' ) );

        qaf_model_dialog_start( 'qaf-outcome-dialog', __( 'Edit outcome', 'quill-accessible-forms' ) );
        ?>
            <p>
                <label for="qaf-outcome-id"><?php esc_html_e( 'ID', 'quill-accessible-forms' ); ?></label><br>
                <input type="text" id="qaf-outcome-id" class="regular-text code" autocomplete="off" spellcheck="false" aria-describedby="qaf-outcome-id-help qaf-outcome-id-error">
                <span id="qaf-outcome-id-help" class="description"><?php esc_html_e( 'A short name for the outcome, such as pass. Letters, digits and underscores.', 'quill-accessible-forms' ); ?></span>
                <span id="qaf-outcome-id-error" class="qaf-field-error" hidden></span>
            </p>
            <?php
            qaf_model_expression_field(
                'qaf-outcome-when',
                __( 'When', 'quill-accessible-forms' ),
                __( 'The condition, for example score >= 5. Insert a field offers the score too.', 'quill-accessible-forms' )
            );
            ?>
            <p>
                <label for="qaf-outcome-message"><?php esc_html_e( 'Message', 'quill-accessible-forms' ); ?></label><br>
                <textarea id="qaf-outcome-message" rows="4" class="large-text" aria-describedby="qaf-outcome-message-help qaf-outcome-message-error"></textarea>
                <span id="qaf-outcome-message-help" class="description"><?php esc_html_e( 'What the person sees after sending the quiz.', 'quill-accessible-forms' ); ?></span>
                <span id="qaf-outcome-message-error" class="qaf-field-error" hidden></span>
            </p>
        <?php
        qaf_model_dialog_end( __( 'Save outcome', 'quill-accessible-forms' ) );

        qaf_model_dialog_start( 'qaf-language-dialog', __( 'Edit translation', 'quill-accessible-forms' ), 'wide' );
        ?>
            <div id="qaf-language-dialog-status" class="qaf-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
            <p>
                <label for="qaf-language-code"><?php esc_html_e( 'Language code', 'quill-accessible-forms' ); ?></label><br>
                <input type="text" id="qaf-language-code" class="small-text code" autocomplete="off" spellcheck="false" aria-describedby="qaf-language-code-help qaf-language-code-error">
                <span id="qaf-language-code-help" class="description"><?php esc_html_e( 'Such as es for Spanish, fr for French or pt-BR for Brazilian Portuguese. A visitor gets this language from ?qaf_lang=es or from the site\'s language.', 'quill-accessible-forms' ); ?></span>
                <span id="qaf-language-code-error" class="qaf-field-error" hidden></span>
            </p>
            <p>
                <input type="checkbox" id="qaf-language-untranslated">
                <label for="qaf-language-untranslated"><?php esc_html_e( 'Show only the texts not yet translated', 'quill-accessible-forms' ); ?></label>
            </p>
            <table class="widefat striped qaf-model-table qaf-translation-grid" id="qaf-language-texts" aria-describedby="qaf-language-texts-help">
                <caption id="qaf-language-texts-caption"><?php esc_html_e( 'Texts', 'quill-accessible-forms' ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Text', 'quill-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'The form\'s own text', 'quill-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Translation', 'quill-accessible-forms' ); ?></th>
                    </tr>
                </thead>
                <tbody id="qaf-language-text-rows"></tbody>
            </table>
            <span id="qaf-language-texts-help" class="description"><?php esc_html_e( 'One row per text. Built-in messages, such as the one for a required question, have no text of the form\'s own; empty, they use QAF\'s.', 'quill-accessible-forms' ); ?></span>
        <?php
        qaf_model_dialog_end( __( 'Save translation', 'quill-accessible-forms' ) );

        qaf_model_dialog_start( 'qaf-library-dialog', __( 'Edit library', 'quill-accessible-forms' ), 'wide' );
        ?>
            <div id="qaf-library-dialog-status" class="qaf-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
            <p>
                <label for="qaf-library-name"><?php esc_html_e( 'Name', 'quill-accessible-forms' ); ?></label><br>
                <input type="text" id="qaf-library-name" class="regular-text code" autocomplete="off" spellcheck="false" aria-describedby="qaf-library-name-help qaf-library-name-error">
                <span id="qaf-library-name-help" class="description"><?php esc_html_e( 'Letters, digits and underscores, such as sizes. Questions name the library this way; renaming it here renames it in them too.', 'quill-accessible-forms' ); ?></span>
                <span id="qaf-library-name-error" class="qaf-field-error" hidden></span>
            </p>
            <table class="widefat qaf-model-table qaf-choice-grid" id="qaf-library-choices" aria-describedby="qaf-library-choices-help">
                <caption><?php esc_html_e( 'Choices', 'quill-accessible-forms' ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Number', 'quill-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Label', 'quill-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Value', 'quill-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Price', 'quill-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Points', 'quill-accessible-forms' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Actions', 'quill-accessible-forms' ); ?></th>
                    </tr>
                </thead>
                <tbody id="qaf-library-choice-rows"></tbody>
            </table>
            <span id="qaf-library-choices-help" class="description"><?php esc_html_e( 'The label is what people see; the value is what is stored. A value left empty is made from the label. Price and points may be left empty.', 'quill-accessible-forms' ); ?></span>
            <p><button type="button" class="button" id="qaf-library-add-choice"><?php esc_html_e( 'Add choice', 'quill-accessible-forms' ); ?></button></p>
            <fieldset>
                <legend><?php esc_html_e( 'Paste choices', 'quill-accessible-forms' ); ?></legend>
                <label for="qaf-library-paste"><?php esc_html_e( 'Choices, one a line', 'quill-accessible-forms' ); ?></label><br>
                <textarea id="qaf-library-paste" rows="5" class="large-text code" spellcheck="false" aria-describedby="qaf-library-paste-help qaf-library-paste-error"></textarea>
                <span id="qaf-library-paste-help" class="description"><?php esc_html_e( 'As the QAF form language writes them: Small, or s|Small for a value and a label, or Small:10 for a price. The + at the start may be left off.', 'quill-accessible-forms' ); ?></span>
                <span id="qaf-library-paste-error" class="qaf-field-error" hidden></span>
                <br><button type="button" class="button" id="qaf-library-paste-add"><?php esc_html_e( 'Add the pasted choices', 'quill-accessible-forms' ); ?></button>
            </fieldset>
        <?php
        qaf_model_dialog_end( __( 'Save library', 'quill-accessible-forms' ) );
    }
}

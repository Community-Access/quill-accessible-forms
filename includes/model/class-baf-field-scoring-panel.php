<?php
/**
 * The Scoring section of a question's settings in the builder's Visual
 * mode: points, the correct answers, partial credit, capitals, feedback,
 * and each choice's points, drawn on the server from the question in the
 * stored form model. admin/js/baf-field-scoring.js saves it back into the
 * model (only this question's scoring and its choices' points).
 *
 * Every control has a real label; the correct answers are a fieldset with
 * a legend; help and errors are joined with aria-describedby; an error
 * summary takes focus; a status region says the save.
 *
 * Uses only __(), esc_html() and esc_attr() from WordPress, so the tests
 * run it with the mocks.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Scoring_Panel class.
 */
class BAF_Field_Scoring_Panel {

    /** Types that can be scored: choices, or typed text. */
    const CHOICE_TYPES = array( 'quiz', 'radio', 'checkbox', 'select', 'multiselect' );
    const TEXT_TYPES   = array( 'text' );

    /** Types where more than one answer can be chosen. */
    const MULTIPLE_TYPES = array( 'checkbox', 'multiselect' );

    /**
     * Whether a question of this type has a Scoring section.
     *
     * @param string $type Field type.
     * @return bool
     */
    public static function is_scored_type( $type ) {
        return in_array( $type, self::CHOICE_TYPES, true ) || in_array( $type, self::TEXT_TYPES, true );
    }

    /**
     * Whether more than one answer can be chosen (so partial credit applies).
     *
     * @param array $field Field.
     * @return bool
     */
    public static function is_multiple( $field ) {
        $type = $field['type'] ?? '';
        return in_array( $type, self::MULTIPLE_TYPES, true )
            || ( $type === 'quiz' && ( $field['props']['question_type'] ?? '' ) === 'multiple_answer' );
    }

    /**
     * A field by ID, from anywhere in the model.
     *
     * @param array  $model Model.
     * @param string $id    Field ID.
     * @return array|null Field.
     */
    public static function find( $model, $id ) {
        foreach ( BAF_Form_Model::items( $model ) as $entry ) {
            if ( ( $entry['item']['id'] ?? null ) === $id ) {
                return $entry['item'];
            }
        }
        return null;
    }

    /**
     * The field's choices and where they come from.
     *
     * @param array         $field    Field.
     * @param array         $model    Model.
     * @param callable|null $builtin  Name => choices, for BAF's built-in libraries.
     * @return array array( 'choices' => list, 'source' => own|library|builtin|none, 'library' => name ).
     */
    public static function choices( $field, $model, $builtin = null ) {
        if ( isset( $field['choices'] ) ) {
            return array( 'choices' => $field['choices'], 'source' => 'own', 'library' => null );
        }
        $name = $field['choice_library'] ?? null;
        if ( $name !== null && isset( $model['choice_libraries'][ $name ] ) ) {
            return array( 'choices' => $model['choice_libraries'][ $name ], 'source' => 'library', 'library' => $name );
        }
        if ( $name !== null && is_callable( $builtin ) ) {
            return array( 'choices' => (array) call_user_func( $builtin, $name ), 'source' => 'builtin', 'library' => $name );
        }
        return array( 'choices' => array(), 'source' => 'none', 'library' => $name );
    }

    /**
     * The Scoring section's HTML.
     *
     * @param array         $field   The question, from the model.
     * @param array         $model   The model (for its choice libraries).
     * @param callable|null $builtin Name => choices, for built-in libraries.
     * @return string HTML.
     */
    public static function render( $field, $model, $builtin = null ) {
        $type     = $field['type'] ?? '';
        $scoring  = isset( $field['scoring'] ) && is_array( $field['scoring'] ) ? $field['scoring'] : array();
        $label    = ( $field['label'] ?? '' ) !== '' ? $field['label'] : '#' . ( $field['id'] ?? '' );
        $is_text  = in_array( $type, self::TEXT_TYPES, true );
        $multiple = self::is_multiple( $field );
        $correct  = array_map( 'strval', (array) ( $scoring['correct'] ?? array() ) );
        $found    = self::choices( $field, $model, $builtin );

        ob_start();
        ?>
<section class="baf-scoring-section" aria-labelledby="baf-scoring-heading" data-field-id="<?php echo esc_attr( $field['id'] ?? '' ); ?>" data-kind="<?php echo $is_text ? 'text' : 'choice'; ?>" data-multiple="<?php echo $multiple ? '1' : '0'; ?>" data-choices="<?php echo esc_attr( $found['source'] ); ?>">
    <h3 id="baf-scoring-heading" tabindex="-1"><?php echo esc_html( sprintf( __( 'Scoring: %s', 'bits-accessible-forms' ), $label ) ); ?></h3>
    <p id="baf-scoring-intro"><?php echo esc_html( $is_text ? __( 'A typed answer scores its points when it matches one of the accepted answers.', 'bits-accessible-forms' ) : __( 'An answer scores the question\'s points when it is correct, plus the points of each choice picked.', 'bits-accessible-forms' ) ); ?></p>
    <div id="baf-scoring-summary" class="baf-error-summary" tabindex="-1" hidden></div>
    <div id="baf-scoring-status" class="baf-status" role="status" aria-live="polite" aria-atomic="true"></div>
    <form id="baf-scoring-form" novalidate>
        <p>
            <label for="baf-scoring-points"><?php esc_html_e( 'Points for a correct answer', 'bits-accessible-forms' ); ?></label><br>
            <input type="text" inputmode="decimal" id="baf-scoring-points" class="small-text" autocomplete="off" value="<?php echo esc_attr( isset( $scoring['points'] ) ? self::number( $scoring['points'] ) : '' ); ?>" aria-describedby="baf-scoring-points-help baf-scoring-points-error">
            <span id="baf-scoring-points-help" class="description"><?php esc_html_e( 'A number, such as 2. Leave it empty to give none.', 'bits-accessible-forms' ); ?></span>
            <span id="baf-scoring-points-error" class="baf-field-error" hidden></span>
        </p>
        <?php if ( $is_text ) : ?>
            <p>
                <label for="baf-scoring-accepted"><?php esc_html_e( 'Accepted answers, one a line', 'bits-accessible-forms' ); ?></label><br>
                <textarea id="baf-scoring-accepted" rows="3" class="large-text" aria-describedby="baf-scoring-accepted-help baf-scoring-accepted-error"><?php echo esc_html( implode( "\n", $correct ) ); ?></textarea>
                <span id="baf-scoring-accepted-help" class="description"><?php esc_html_e( 'Spaces at either end are ignored. Leave it empty if no answer is correct.', 'bits-accessible-forms' ); ?></span>
                <span id="baf-scoring-accepted-error" class="baf-field-error" hidden></span>
            </p>
            <p>
                <input type="checkbox" id="baf-scoring-case-sensitive"<?php echo ! empty( $scoring['case_sensitive'] ) ? ' checked' : ''; ?>>
                <label for="baf-scoring-case-sensitive"><?php esc_html_e( 'Capitals must match', 'bits-accessible-forms' ); ?></label>
            </p>
        <?php else : ?>
            <fieldset id="baf-scoring-correct" aria-describedby="baf-scoring-correct-help baf-scoring-correct-error">
                <legend><?php echo esc_html( $multiple ? __( 'Correct answers', 'bits-accessible-forms' ) : __( 'Correct answer', 'bits-accessible-forms' ) ); ?></legend>
                <span id="baf-scoring-correct-help" class="description"><?php echo esc_html( $multiple ? __( 'Check every correct answer. All of them, and nothing else, must be picked to score.', 'bits-accessible-forms' ) : __( 'Choose the correct answer, or none if this question is scored by its choices\' points alone.', 'bits-accessible-forms' ) ); ?></span>
                <?php if ( ! $found['choices'] ) : ?>
                    <p><?php esc_html_e( 'This question has no choices to mark yet.', 'bits-accessible-forms' ); ?></p>
                <?php else : ?>
                    <ul class="baf-check-list">
                        <?php if ( ! $multiple ) : ?>
                            <li>
                                <input type="radio" name="baf-scoring-correct" id="baf-scoring-correct-none" value=""<?php echo $correct ? '' : ' checked'; ?>>
                                <label for="baf-scoring-correct-none"><?php esc_html_e( 'None', 'bits-accessible-forms' ); ?></label>
                            </li>
                        <?php endif; ?>
                        <?php foreach ( $found['choices'] as $i => $c ) : ?>
                            <li>
                                <input type="<?php echo $multiple ? 'checkbox' : 'radio'; ?>" name="baf-scoring-correct" id="baf-scoring-correct-<?php echo (int) $i; ?>" value="<?php echo esc_attr( (string) $c['value'] ); ?>"<?php echo in_array( (string) $c['value'], $correct, true ) ? ' checked' : ''; ?>>
                                <label for="baf-scoring-correct-<?php echo (int) $i; ?>"><?php echo esc_html( (string) $c['label'] ); ?></label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <span id="baf-scoring-correct-error" class="baf-field-error" hidden></span>
            </fieldset>
            <?php if ( $multiple ) : ?>
                <p>
                    <input type="checkbox" id="baf-scoring-partial" aria-describedby="baf-scoring-partial-help"<?php echo ! empty( $scoring['partial'] ) ? ' checked' : ''; ?>>
                    <label for="baf-scoring-partial"><?php esc_html_e( 'Give partial credit', 'bits-accessible-forms' ); ?></label>
                    <span id="baf-scoring-partial-help" class="description"><?php esc_html_e( 'A share of the points for each correct answer picked, and none if a wrong one is picked.', 'bits-accessible-forms' ); ?></span>
                </p>
            <?php endif; ?>
            <?php if ( $found['choices'] ) : ?>
                <?php if ( $found['source'] === 'own' ) : ?>
                    <table class="widefat striped baf-model-table" id="baf-scoring-choice-points" aria-describedby="baf-scoring-choice-points-help">
                        <caption><?php esc_html_e( 'Points for each choice', 'bits-accessible-forms' ); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e( 'Choice', 'bits-accessible-forms' ); ?></th>
                                <th scope="col"><?php esc_html_e( 'Points', 'bits-accessible-forms' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $found['choices'] as $i => $c ) : ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html( (string) $c['label'] ); ?></th>
                                    <td>
                                        <label for="baf-scoring-choice-<?php echo (int) $i; ?>-points" class="baf-sr-only"><?php echo esc_html( sprintf( __( 'Points for %s', 'bits-accessible-forms' ), (string) $c['label'] ) ); ?></label>
                                        <input type="text" inputmode="decimal" class="small-text" autocomplete="off" id="baf-scoring-choice-<?php echo (int) $i; ?>-points" data-index="<?php echo (int) $i; ?>" value="<?php echo esc_attr( isset( $c['points'] ) ? self::number( $c['points'] ) : '' ); ?>" aria-describedby="baf-scoring-choice-<?php echo (int) $i; ?>-points-error">
                                        <span id="baf-scoring-choice-<?php echo (int) $i; ?>-points-error" class="baf-field-error" hidden></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p id="baf-scoring-choice-points-help" class="description"><?php esc_html_e( 'Points a choice adds when it is picked, right or wrong, such as for a survey of how sure someone is. Leave a box empty for none.', 'bits-accessible-forms' ); ?></p>
                <?php else : ?>
                    <p><?php echo esc_html( $found['source'] === 'library' ? sprintf( __( 'The choices come from this form\'s library %s; their points are set on the Choice libraries tab.', 'bits-accessible-forms' ), $found['library'] ) : sprintf( __( 'The choices come from BAF\'s built-in library %s, which has no points. Copy it into the form on the Choice libraries tab to give its choices points.', 'bits-accessible-forms' ), $found['library'] ) ); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
        <fieldset>
            <legend><?php esc_html_e( 'Feedback', 'bits-accessible-forms' ); ?></legend>
            <?php
            $texts = array(
                'feedback_correct'   => __( 'When the answer is correct', 'bits-accessible-forms' ),
                'feedback_incorrect' => __( 'When the answer is wrong', 'bits-accessible-forms' ),
            );
            if ( $multiple ) {
                $texts['feedback_partial'] = __( 'When the answer is partly right', 'bits-accessible-forms' );
            }
            $texts['explanation'] = __( 'Explanation, shown after either', 'bits-accessible-forms' );
            foreach ( $texts as $key => $text ) :
                $id = 'baf-scoring-' . str_replace( '_', '-', $key );
                ?>
                <p>
                    <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $text ); ?></label><br>
                    <textarea id="<?php echo esc_attr( $id ); ?>" data-key="<?php echo esc_attr( $key ); ?>" rows="2" class="large-text"><?php echo esc_html( (string) ( $scoring[ $key ] ?? '' ) ); ?></textarea>
                </p>
            <?php endforeach; ?>
        </fieldset>
        <p>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save scoring', 'bits-accessible-forms' ); ?></button>
            <button type="button" class="button" data-baf-scoring-close><?php esc_html_e( 'Close', 'bits-accessible-forms' ); ?></button>
        </p>
    </form>
</section>
        <?php
        return ob_get_clean();
    }

    /**
     * A number as a person would type it: 2, 1.5, -3.
     *
     * @param int|float $n Number.
     * @return string Text.
     */
    private static function number( $n ) {
        if ( is_int( $n ) || ( is_float( $n ) && floor( $n ) === $n && abs( $n ) < 1e15 ) ) {
            return (string) (int) $n;
        }
        return rtrim( rtrim( sprintf( '%.10F', (float) $n ), '0' ), '.' );
    }
}

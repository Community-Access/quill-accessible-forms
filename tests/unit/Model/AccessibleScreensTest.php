<?php
/**
 * Static checks on the admin screens added with the form model: every
 * control has a label, results are spoken, and the page has headings.
 *
 * @package QAF
 */

class QAF_Accessible_Screens_Test extends QAF_Unit_Test_Case {

    const SCREENS = array( 'admin/partials/import-export.php' );

    // ------------------------------------------------------------------
    // The Visual mode's Scoring section (QAF_Field_Scoring_Panel)
    // ------------------------------------------------------------------

    private static function quiz_model() {
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model.php';
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-field-scoring-panel.php';
        $m = json_decode( file_get_contents( QAF_PLUGIN_DIR . 'tests/conformance/models/valid/quiz-and-translations.json' ), true );
        $m['pages'][0]['items'][] = array( 'id' => 'size', 'type' => 'select', 'label' => 'Size', 'choice_library' => 'sizes' );
        $m['pages'][0]['items'][] = array( 'id' => 'month', 'type' => 'select', 'label' => 'Month', 'choice_library' => 'months' );
        $m['choice_libraries'] = array( 'sizes' => array( array( 'value' => 's', 'label' => 'Small', 'points' => 1 ) ) );
        return $m;
    }

    /** One question's Scoring section as a DOM. */
    private static function scoring( $id ) {
        $m     = self::quiz_model();
        $field = QAF_Field_Scoring_Panel::find( $m, $id );
        $html  = QAF_Field_Scoring_Panel::render( $field, $m, function ( $name ) {
            return $name === 'months' ? array( array( 'value' => 'jan', 'label' => 'January' ) ) : array();
        } );
        $doc = new DOMDocument();
        libxml_use_internal_errors( true );
        $doc->loadHTML( '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>' );
        $errors = array_filter( libxml_get_errors(), function ( $e ) {
            return ! preg_match( '/Tag (section|main) invalid/', $e->message );
        } );
        libxml_clear_errors();
        return array( $doc, new DOMXPath( $doc ), array_values( $errors ) );
    }

    public function test_scoring_sections_are_labelled_and_joined() {
        foreach ( array( 'q1', 'q2', 'q3', 'feel', 'size', 'month' ) as $id ) {
            list( $doc, $xp, $errors ) = self::scoring( $id );
            $this->assertSame( array(), array_map( function ( $e ) { return trim( $e->message ); }, $errors ), $id );
            $seen = array();
            foreach ( $xp->query( '//*[@id]' ) as $e ) {
                $this->assertArrayNotHasKey( $e->getAttribute( 'id' ), $seen, $id . ': an id used twice' );
                $seen[ $e->getAttribute( 'id' ) ] = true;
            }
            foreach ( $xp->query( '//input | //select | //textarea' ) as $c ) {
                $this->assertSame( 1, $xp->query( '//label[@for="' . $c->getAttribute( 'id' ) . '"]' )->length, $id . ': no label for ' . $c->getAttribute( 'id' ) );
            }
            foreach ( array( 'aria-describedby', 'aria-labelledby' ) as $attr ) {
                foreach ( $xp->query( '//*[@' . $attr . ']' ) as $e ) {
                    foreach ( preg_split( '/\s+/', $e->getAttribute( $attr ) ) as $ref ) {
                        $this->assertNotNull( $doc->getElementById( $ref ), $id . ': ' . $attr . ' names ' . $ref );
                    }
                }
            }
            foreach ( $xp->query( '//fieldset' ) as $fs ) {
                $this->assertSame( 1, $xp->query( './legend', $fs )->length, $id . ': a fieldset without a legend' );
            }
            $h = $doc->getElementById( 'qaf-scoring-heading' );
            $this->assertSame( 'h3', $h->nodeName );
            $this->assertSame( '-1', $h->getAttribute( 'tabindex' ), $id . ': the heading takes focus when the section opens' );
            $this->assertSame( 1, $xp->query( '//*[@role="status"]' )->length, $id . ': one status region' );
            $this->assertSame( '-1', $doc->getElementById( 'qaf-scoring-summary' )->getAttribute( 'tabindex' ) );
            foreach ( $xp->query( '//table' ) as $table ) {
                $this->assertSame( 1, $xp->query( './caption', $table )->length );
                $this->assertGreaterThan( 0, $xp->query( './/th[@scope="row"]', $table )->length );
            }
        }
    }

    public function test_scoring_section_shows_what_the_model_holds() {
        list( $doc, $xp ) = self::scoring( 'q1' );
        $this->assertSame( 'Scoring: How many dots are in a braille cell?', $doc->getElementById( 'qaf-scoring-heading' )->textContent );
        $this->assertSame( '2', $doc->getElementById( 'qaf-scoring-points' )->getAttribute( 'value' ) );
        $checked = $xp->query( '//input[@name="qaf-scoring-correct"][@checked]' );
        $this->assertSame( 1, $checked->length );
        $this->assertSame( '6', $checked->item( 0 )->getAttribute( 'value' ) );
        $this->assertSame( 'radio', $checked->item( 0 )->getAttribute( 'type' ), 'one correct answer: radio buttons, with None' );
        $this->assertNotNull( $doc->getElementById( 'qaf-scoring-correct-none' ) );
        $this->assertNull( $doc->getElementById( 'qaf-scoring-partial' ), 'no partial credit for one answer' );
        $this->assertStringContainsString( 'six-dot', $doc->getElementById( 'qaf-scoring-explanation' )->textContent );

        list( $doc, $xp ) = self::scoring( 'q2' );
        $this->assertSame( 'Correct answers', $xp->query( '//fieldset[@id="qaf-scoring-correct"]/legend' )->item( 0 )->textContent );
        $this->assertSame( 2, $xp->query( '//input[@type="checkbox"][@name="qaf-scoring-correct"][@checked]' )->length );
        $this->assertTrue( $doc->getElementById( 'qaf-scoring-partial' )->hasAttribute( 'checked' ) );
        $this->assertSame( 'Partly right.', $doc->getElementById( 'qaf-scoring-feedback-partial' )->textContent );

        list( $doc ) = self::scoring( 'q3' );
        $this->assertSame( 'Braille', $doc->getElementById( 'qaf-scoring-accepted' )->textContent );
        $this->assertFalse( $doc->getElementById( 'qaf-scoring-case-sensitive' )->hasAttribute( 'checked' ) );
        $this->assertNull( $doc->getElementById( 'qaf-scoring-correct' ) );

        list( $doc, $xp ) = self::scoring( 'feel' );
        $this->assertSame( array( '0', '1', '2' ), array_map( function ( $i ) { return $i->getAttribute( 'value' ); }, iterator_to_array( $xp->query( '//table[@id="qaf-scoring-choice-points"]//input' ) ) ) );
        $this->assertSame( 'Points for Fairly', $xp->query( '//label[@for="qaf-scoring-choice-1-points"]' )->item( 0 )->textContent );

        list( $doc, $xp ) = self::scoring( 'size' );
        $this->assertNull( $doc->getElementById( 'qaf-scoring-choice-points' ), 'a library\'s points are set on its own tab' );
        $this->assertStringContainsString( 'library sizes', $doc->textContent );
        $this->assertSame( 1, $xp->query( '//input[@name="qaf-scoring-correct"][@value="s"]' )->length );

        list( $doc, $xp ) = self::scoring( 'month' );
        $this->assertSame( 1, $xp->query( '//input[@name="qaf-scoring-correct"][@value="jan"]' )->length, 'a built-in library\'s choices can be marked correct' );
        $this->assertStringContainsString( 'built-in library months', $doc->textContent );
    }

    // ------------------------------------------------------------------
    // The Visual mode's field settings panel and its cards
    // ------------------------------------------------------------------

    public function test_field_settings_are_labelled_and_show_the_model() {
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-builder-field-ops.php';
        $m = self::quiz_model();
        foreach ( array( 'q1' => false, 'q3' => true ) as $id => $has_placeholder ) {
            $field = QAF_Field_Scoring_Panel::find( $m, $id );
            $doc   = new DOMDocument();
            libxml_use_internal_errors( true );
            $doc->loadHTML( '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . QAF_Builder_Field_Ops::render_settings( $field, 'Quiz' ) . '</body></html>' );
            $errors = array_filter( libxml_get_errors(), function ( $e ) { return ! preg_match( '/Tag section invalid/', $e->message ); } );
            libxml_clear_errors();
            $this->assertSame( array(), array_values( $errors ), $id );
            $xp = new DOMXPath( $doc );
            foreach ( $xp->query( '//input | //textarea | //select' ) as $c ) {
                $this->assertSame( 1, $xp->query( '//label[@for="' . $c->getAttribute( 'id' ) . '"]' )->length, $id . ': no label for ' . $c->getAttribute( 'id' ) );
            }
            foreach ( $xp->query( '//*[@aria-describedby]' ) as $e ) {
                foreach ( preg_split( '/\s+/', $e->getAttribute( 'aria-describedby' ) ) as $ref ) {
                    $this->assertNotNull( $doc->getElementById( $ref ), $id . ': aria-describedby names ' . $ref );
                }
            }
            $h = $doc->getElementById( 'qaf-fs-heading' );
            $this->assertSame( 'Settings: ' . $field['label'], $h->textContent );
            $this->assertSame( '-1', $h->getAttribute( 'tabindex' ) );
            $this->assertSame( 1, $xp->query( '//*[@role="status"]' )->length );
            $this->assertSame( '-1', $doc->getElementById( 'qaf-fs-summary' )->getAttribute( 'tabindex' ) );
            $this->assertSame( $field['label'], $doc->getElementById( 'qaf-fs-label' )->getAttribute( 'value' ) );
            $this->assertSame( $has_placeholder, $doc->getElementById( 'qaf-fs-placeholder' ) !== null, $id . ': a placeholder only where a box shows one' );
            $this->assertSame( 1, $xp->query( '//button[@type="submit"][contains(@class,"qaf-settings-save")]' )->length );
            $this->assertSame( 1, $xp->query( '//button[contains(@class,"qaf-settings-close")]' )->length );
        }
    }

    public function test_card_buttons_carry_the_classes_the_script_listens_for() {
        $card = file_get_contents( QAF_PLUGIN_DIR . 'admin/class-qaf-form-builder.php' );
        $js   = file_get_contents( QAF_PLUGIN_DIR . 'admin/js/qaf-form-builder.js' );
        preg_match_all( "/on\\('click', '\\.(qaf-field-[a-z-]+)'/", $js, $m );
        $this->assertGreaterThanOrEqual( 5, count( $m[1] ) );
        foreach ( $m[1] as $class ) {
            $this->assertMatchesRegularExpression( '/class="[^"]*\b' . preg_quote( $class, '/' ) . '\b/', $card, 'no card button has ' . $class );
        }
        $this->assertStringContainsString( "getElementById('qaf-form-canvas')", $js );
        $this->assertStringContainsString( "getElementById('qaf-canvas-dropzone')", $js );
        $page = file_get_contents( QAF_PLUGIN_DIR . 'admin/partials/form-builder.php' );
        $this->assertStringContainsString( 'id="qaf-canvas-dropzone"', $page );
        $this->assertStringContainsString( 'id="qaf-live-region"', $page, 'the announcer has a region to speak in' );
        $this->assertStringNotContainsString( 'qafL10n', $js, 'the page localises qafBuilder' );
        $this->assertStringNotContainsString( 'window.confirm(', $js );
        $this->assertStringContainsString( 'const onCard = target === $activeCard[0];', $js, 'Enter and Delete act only on the card itself, so its buttons answer Enter' );
        $this->assertStringContainsString( 'self.canvas.contains(target)', $js, 'F adds a question only while focus is in the canvas (WCAG 2.1.4)' );
        $this->assertStringNotContainsString( "prop('disabled', index", $js, 'a move button at either end keeps focus' );
        $this->assertStringNotContainsString( 'disabled aria-disabled', $card );
        $this->assertStringContainsString( "'(prefers-reduced-motion: reduce)'", $js );
        $this->assertStringContainsString( 'draggable="true"', $card, 'a card can be dragged; the move buttons are the keyboard way' );
        $this->assertStringNotContainsString( 'qaf-field-action-convert"', $card, 'the card\'s Convert is not the old Structure-mode converter\'s' );
        $this->assertStringNotContainsString( 'data-action="convert"', $card );
        $this->assertStringContainsString( 'render_canvas(', $page, 'the canvas is drawn from the model' );
        $this->assertStringNotContainsString( 'get_fields()', $page, 'not from the flat builder config, which has no nested questions' );
    }

    // ------------------------------------------------------------------
    // The canvas and the type dialogs
    // ------------------------------------------------------------------

    /** A DOM of some HTML, and libxml's complaints that are not about HTML5 tags. */
    private static function dom( $html ) {
        $doc = new DOMDocument();
        libxml_use_internal_errors( true );
        $doc->loadHTML( '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>' );
        $errors = array_values( array_filter( libxml_get_errors(), function ( $e ) { return ! preg_match( '/Tag (section|main|search) invalid/', $e->message ); } ) );
        libxml_clear_errors();
        return array( $doc, new DOMXPath( $doc ), $errors );
    }

    private static function categories() {
        return array(
            'basic'   => array( 'label' => 'Basic', 'icon' => '*', 'fields' => array( array( 'type' => 'text', 'label' => 'Text Field', 'icon' => '*' ), array( 'type' => 'email', 'label' => 'Email', 'icon' => '*' ) ) ),
            'choices' => array( 'label' => 'Choices', 'icon' => '*', 'fields' => array( array( 'type' => 'select', 'label' => 'Dropdown', 'icon' => '*' ) ) ),
        );
    }

    public function test_type_dialogs_are_modal_labelled_and_list_every_type() {
        require_once QAF_PLUGIN_DIR . 'admin/partials/model-screens.php';
        ob_start();
        qaf_builder_type_dialogs( self::categories() );
        list( $doc, $xp, $errors ) = self::dom( ob_get_clean() );
        $this->assertSame( array(), array_map( function ( $e ) { return trim( $e->message ); }, $errors ) );
        foreach ( array( 'qaf-picker-dialog', 'qaf-convert-dialog' ) as $id ) {
            $box = $xp->query( '//*[@id="' . $id . '"]//*[@role="dialog"]' )->item( 0 );
            $this->assertNotNull( $box, $id );
            $this->assertSame( 'true', $box->getAttribute( 'aria-modal' ) );
            $this->assertSame( 'h2', $doc->getElementById( $box->getAttribute( 'aria-labelledby' ) )->nodeName );
            $this->assertSame( 1, $xp->query( './/*[@data-qaf-cancel]', $box )->length );
        }
        foreach ( $xp->query( '//input | //select' ) as $c ) {
            $this->assertSame( 1, $xp->query( '//label[@for="' . $c->getAttribute( 'id' ) . '"]' )->length, 'no label for ' . $c->getAttribute( 'id' ) );
        }
        foreach ( array( 'aria-describedby', 'aria-labelledby', 'aria-controls' ) as $attr ) {
            foreach ( $xp->query( '//*[@' . $attr . ']' ) as $e ) {
                foreach ( preg_split( '/\s+/', $e->getAttribute( $attr ) ) as $ref ) {
                    $this->assertNotNull( $doc->getElementById( $ref ), $attr . ' names ' . $ref );
                }
            }
        }
        // The picker: a heading per category and a real button per type.
        $this->assertSame( array( 'Basic', 'Choices' ), array_map( function ( $h ) { return $h->textContent; }, iterator_to_array( $xp->query( '//*[@id="qaf-picker-types"]//h3' ) ) ) );
        $this->assertSame( array( 'text', 'email', 'select' ), array_map( function ( $b ) { return $b->getAttribute( 'data-field-type' ); }, iterator_to_array( $xp->query( '//button[contains(@class,"qaf-picker-type")]' ) ) ) );
        $this->assertSame( 'polite', $doc->getElementById( 'qaf-picker-count' )->getAttribute( 'aria-live' ) );
        // Convert: one option per type, grouped, after a "Choose" prompt; the preview is said.
        $this->assertSame( array( '', 'text', 'email', 'select' ), array_map( function ( $o ) { return $o->getAttribute( 'value' ); }, iterator_to_array( $xp->query( '//select[@id="qaf-convert-type"]//option' ) ) ) );
        $this->assertSame( 2, $xp->query( '//select[@id="qaf-convert-type"]/optgroup' )->length );
        $this->assertSame( 'polite', $doc->getElementById( 'qaf-convert-preview' )->getAttribute( 'aria-live' ) );
        $this->assertSame( '-1', $doc->getElementById( 'qaf-convert-dialog-summary' )->getAttribute( 'tabindex' ) );
    }

    public function test_the_canvas_gives_every_question_a_card_in_its_own_list() {
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-form-model.php';
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-field-scoring-panel.php';
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-builder-field-ops.php';
        require_once QAF_PLUGIN_DIR . 'includes/accessibility/class-qaf-aria-generator.php';
        require_once QAF_PLUGIN_DIR . 'admin/class-qaf-form-builder.php';
        if ( ! class_exists( 'QAF_Field_Registry' ) ) {
            eval( 'class QAF_Field_Registry { public static function get_instance() { return new self(); } public function get_field_type( $t ) { return array( "label" => ucfirst( $t ), "icon" => "*" ); } }' );
        }
        $m = json_decode( file_get_contents( QAF_PLUGIN_DIR . 'tests/conformance/models/valid/logic-and-layout.json' ), true );
        $builder = new QAF_Form_Builder();
        list( $doc, $xp, $errors ) = self::dom( '<div id="qaf-canvas-title">T</div><div id="qaf-canvas-dropzone">' . $builder->render_canvas( $m, 3 ) . '</div>' );
        $this->assertSame( array(), array_map( function ( $e ) { return trim( $e->message ); }, $errors ) );

        // A card for every question the canvas tree reaches, in order.
        $want = array();
        $walk = function ( $nodes ) use ( &$walk, &$want ) {
            foreach ( $nodes as $n ) {
                if ( $n['kind'] === 'field' ) {
                    $want[] = $n['item']['id'];
                } elseif ( $n['kind'] === 'section' ) {
                    $walk( $n['items'] );
                } else {
                    foreach ( $n['columns'] as $c ) {
                        $walk( $c );
                    }
                }
            }
        };
        foreach ( QAF_Builder_Field_Ops::canvas_tree( $m ) as $page ) {
            $walk( $page['items'] );
        }
        $cards = array_map( function ( $c ) { return $c->getAttribute( 'data-field-id' ); }, iterator_to_array( $xp->query( '//div[contains(concat(" ",normalize-space(@class)," ")," qaf-field-card ")]' ) ) );
        $this->assertSame( $want, $cards );
        $this->assertGreaterThan( 0, $xp->query( '//*[contains(@class,"qaf-group-card")]//div[contains(concat(" ",normalize-space(@class)," ")," qaf-field-card ")]' )->length, 'a nested question has its card inside its group' );

        // Every list is labelled; every card is a list item of one; pages have headings.
        foreach ( $xp->query( '//*[@role="list"]' ) as $list ) {
            $this->assertNotNull( $doc->getElementById( $list->getAttribute( 'aria-labelledby' ) ), 'a list without its label' );
        }
        foreach ( $xp->query( '//*[@role="listitem"]' ) as $li ) {
            $this->assertSame( 'list', $li->parentNode->getAttribute( 'role' ) );
        }
        $this->assertSame( count( $m['pages'] ), $xp->query( '//h3[contains(@class,"qaf-canvas-page")]' )->length );
        // Move buttons count their own list: a card first in its section says position 1.
        $nested = $xp->query( '//*[contains(@class,"qaf-group-card")]//div[contains(concat(" ",normalize-space(@class)," ")," qaf-field-card ")]' )->item( 0 );
        $up     = $xp->query( './/button[contains(@class,"qaf-field-move-up")]', $nested )->item( 0 );
        $this->assertSame( 'true', $up->getAttribute( 'aria-disabled' ) );
        $this->assertNull( $up->attributes->getNamedItem( 'disabled' ) );

        // No questions: the canvas says so, with how to add one.
        $empty = $builder->render_canvas( QAF_Builder_Field_Ops::new_model( '' ), 0 );
        $this->assertStringContainsString( 'No questions yet', $empty );
    }

    public function test_every_card_action_has_a_handler_and_every_string_is_localised() {
        $js    = file_get_contents( QAF_PLUGIN_DIR . 'admin/js/qaf-form-builder.js' );
        $boot  = file_get_contents( QAF_PLUGIN_DIR . 'quill-accessible-forms.php' );
        $admin = file_get_contents( QAF_PLUGIN_DIR . 'admin/class-qaf-admin.php' );
        preg_match_all( "/post\\('(qaf_[a-z_]+)'/", $js, $m );
        $this->assertGreaterThanOrEqual( 6, count( array_unique( $m[1] ) ) );
        foreach ( array_unique( $m[1] ) as $action ) {
            $this->assertStringContainsString( "'wp_ajax_" . $action . "'", $boot, $action . ' has no handler' );
        }
        preg_match_all( "/\\bL\\('(\\w+)',\\s*'((?:[^'\\\\]|\\\\.)*)'/", $js, $s, PREG_SET_ORDER );
        $this->assertNotEmpty( $s );
        foreach ( $s as $x ) {
            $this->assertMatchesRegularExpression( "/'" . $x[1] . "'\\s*=> __\\( '" . preg_quote( $x[2], '/' ) . "'/", $admin, $x[1] . ' is not in qafBuilder.i18n, or says something else' );
        }
    }

    public function test_only_scorable_types_get_a_scoring_button() {
        require_once QAF_PLUGIN_DIR . 'includes/model/class-qaf-field-scoring-panel.php';
        foreach ( array( 'quiz', 'radio', 'checkbox', 'select', 'multiselect', 'text' ) as $type ) {
            $this->assertTrue( QAF_Field_Scoring_Panel::is_scored_type( $type ), $type );
        }
        foreach ( array( 'date', 'section', 'email', 'file' ) as $type ) {
            $this->assertFalse( QAF_Field_Scoring_Panel::is_scored_type( $type ), $type );
        }
        $card = file_get_contents( QAF_PLUGIN_DIR . 'admin/class-qaf-form-builder.php' );
        $this->assertStringContainsString( "'Scoring for %s'", $card, 'the button is named for its question' );
        $this->assertStringContainsString( 'aria-controls="qaf-field-settings"', $card );
        $js = file_get_contents( QAF_PLUGIN_DIR . 'admin/js/qaf-field-scoring.js' );
        $this->assertStringContainsString( "'Escape'", $js );
        $this->assertStringContainsString( "$('qaf-scoring-heading').focus()", $js );
        $this->assertStringContainsString( 'back.focus()', $js, 'Close returns focus to the Scoring button' );
        $this->assertStringNotContainsString( 'alert(', $js );
    }

    /** The screen's source, each PHP block replaced by a token (the same code, the same token). */
    private static function source( $rel ) {
        return preg_replace_callback( '/<\?php.*?\?>/s', function ( $m ) {
            return 'php' . substr( md5( $m[0] ), 0, 8 );
        }, file_get_contents( QAF_PLUGIN_DIR . $rel ) );
    }

    public function test_every_control_has_a_label() {
        foreach ( self::SCREENS as $rel ) {
            $src = self::source( $rel );
            preg_match_all( '/<(input|select|textarea)\b([^>]*)>/s', $src, $m, PREG_SET_ORDER );
            $this->assertNotEmpty( $m, $rel );
            foreach ( $m as $tag ) {
                if ( preg_match( '/type="(hidden|submit|button)"/', $tag[2] ) ) {
                    continue;
                }
                $this->assertSame( 1, preg_match( '/\bid="([^"]+)"/', $tag[2], $id ), $rel . ': a control without an id: ' . $tag[0] );
                $this->assertStringContainsString( 'for="' . $id[1] . '"', $src, $rel . ': no label for ' . $id[1] );
            }
        }
    }

    public function test_described_by_names_elements_that_exist() {
        foreach ( self::SCREENS as $rel ) {
            $src = self::source( $rel );
            preg_match_all( '/aria-describedby="([^"]+)"/', $src, $m );
            foreach ( $m[1] as $list ) {
                foreach ( preg_split( '/\s+/', $list ) as $ref ) {
                    $this->assertStringContainsString( 'id="' . $ref . '"', $src, $rel . ': aria-describedby names ' . $ref );
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // The builder's model screens, rendered (admin/partials/model-screens.php)
    // ------------------------------------------------------------------

    /** The screens' panels and dialogs as a DOM, rendered with the mocks. */
    private static function model_screens() {
        require_once QAF_PLUGIN_DIR . 'admin/partials/model-screens.php';
        ob_start();
        echo '<div role="tablist">';
        qaf_model_screen_tabs();
        echo '</div><main>';
        qaf_model_screen_panels();
        echo '</main>';
        qaf_model_screen_dialogs();
        $html = ob_get_clean();
        $doc  = new DOMDocument();
        libxml_use_internal_errors( true );
        $doc->loadHTML( '<!DOCTYPE html><html><body>' . $html . '</body></html>' );
        $errors = array_filter( libxml_get_errors(), function ( $e ) {
            // HTML5 tags libxml does not know are not mistakes.
            return ! preg_match( '/Tag (main|search) invalid/', $e->message );
        } );
        libxml_clear_errors();
        return array( $doc, new DOMXPath( $doc ), $errors );
    }

    public function test_model_screens_are_well_formed_with_unique_ids() {
        list( $doc, $xp, $errors ) = self::model_screens();
        $this->assertSame( array(), array_values( array_map( function ( $e ) { return trim( $e->message ); }, $errors ) ) );
        $seen = array();
        foreach ( $xp->query( '//*[@id]' ) as $e ) {
            $id = $e->getAttribute( 'id' );
            $this->assertArrayNotHasKey( $id, $seen, 'the id ' . $id . ' is used twice' );
            $seen[ $id ] = true;
        }
    }

    public function test_model_screen_controls_have_labels() {
        list( $doc, $xp ) = self::model_screens();
        $controls = $xp->query( '//input[not(@type="hidden")] | //select | //textarea' );
        $this->assertGreaterThan( 3, $controls->length );
        foreach ( $controls as $c ) {
            $id = $c->getAttribute( 'id' );
            $this->assertNotSame( '', $id, 'a control without an id' );
            $named = $xp->query( '//label[@for="' . $id . '"]' )->length > 0 || $c->getAttribute( 'aria-labelledby' ) !== '';
            $this->assertTrue( $named, 'no label for ' . $id );
        }
        foreach ( $xp->query( '//button' ) as $b ) {
            $this->assertNotSame( '', trim( $b->textContent . $b->getAttribute( 'aria-label' ) ), 'a button without a name' );
        }
    }

    public function test_model_screen_references_name_elements_that_exist() {
        list( $doc, $xp ) = self::model_screens();
        foreach ( array( 'aria-describedby', 'aria-labelledby', 'aria-controls' ) as $attr ) {
            foreach ( $xp->query( '//*[@' . $attr . ']' ) as $e ) {
                foreach ( preg_split( '/\s+/', trim( $e->getAttribute( $attr ) ) ) as $ref ) {
                    $this->assertNotNull( $doc->getElementById( $ref ), $attr . ' names ' . $ref . ', which is not there' );
                }
            }
        }
    }

    public function test_every_model_screen_has_a_tab_a_heading_a_table_and_a_status_region() {
        list( $doc, $xp ) = self::model_screens();
        $keys = array_keys( qaf_model_screens() );
        $this->assertContains( 'rules', $keys );
        foreach ( $keys as $key ) {
            $tab   = $doc->getElementById( 'qaf-mode-' . $key );
            $panel = $doc->getElementById( 'qaf-' . $key . '-panel' );
            $this->assertSame( 'tab', $tab->getAttribute( 'role' ) );
            $this->assertSame( 'qaf-' . $key . '-panel', $tab->getAttribute( 'aria-controls' ) );
            $this->assertSame( 'tabpanel', $panel->getAttribute( 'role' ) );
            $this->assertSame( 'qaf-mode-' . $key, $panel->getAttribute( 'aria-labelledby' ) );
            $this->assertSame( 1, $xp->query( './/h2', $panel )->length, $key . ': one heading' );
            $this->assertSame( 1, $xp->query( './/*[@role="status"]', $panel )->length, $key . ': one status region' );
            $summary = $doc->getElementById( 'qaf-' . $key . '-summary' );
            $this->assertSame( '-1', $summary->getAttribute( 'tabindex' ), $key . ': the error summary can take focus' );
            foreach ( $xp->query( './/table', $panel ) as $table ) {
                $this->assertSame( 1, $xp->query( './caption', $table )->length, $key . ': the table has a caption' );
                $cols = $xp->query( './thead//th', $table );
                $this->assertGreaterThan( 2, $cols->length );
                foreach ( $cols as $th ) {
                    $this->assertSame( 'col', $th->getAttribute( 'scope' ) );
                }
            }
            $this->assertNotNull( $doc->getElementById( 'qaf-' . $key . '-add' ), $key . ': an Add button' );
        }
    }

    public function test_model_dialogs_are_modal_with_headings_and_summaries() {
        list( $doc, $xp ) = self::model_screens();
        $dialogs = $xp->query( '//*[@role="dialog" or @role="alertdialog"]' );
        $this->assertGreaterThan( 1, $dialogs->length );
        foreach ( $dialogs as $d ) {
            $this->assertSame( 'true', $d->getAttribute( 'aria-modal' ) );
            $title = $doc->getElementById( $d->getAttribute( 'aria-labelledby' ) );
            $this->assertSame( 'h2', $title->nodeName );
            $this->assertSame( 1, $xp->query( './/*[@data-qaf-cancel]', $d )->length, 'every dialog can be cancelled' );
            if ( $d->getAttribute( 'role' ) === 'dialog' ) {
                $this->assertSame( 1, $xp->query( './/*[contains(@class,"qaf-error-summary") and @tabindex="-1"]', $d )->length );
            }
        }
        foreach ( $xp->query( '//fieldset' ) as $fs ) {
            $this->assertSame( 1, $xp->query( './legend', $fs )->length, 'a fieldset without a legend' );
        }
    }

    public function test_expression_fields_have_their_check_line_and_error_joined() {
        list( $doc, $xp ) = self::model_screens();
        $found = 0;
        foreach ( $xp->query( '//div[contains(@class,"qaf-expression-field")]/input' ) as $input ) {
            $found++;
            $id = $input->getAttribute( 'id' );
            $this->assertSame( $id . '-help ' . $id . '-check ' . $id . '-error', $input->getAttribute( 'aria-describedby' ) );
            $live = $doc->getElementById( $id . '-live' );
            $this->assertSame( 'polite', $live->getAttribute( 'aria-live' ) );
            $insert = $doc->getElementById( $id . '-insert' );
            $this->assertSame( 'false', $insert->getAttribute( 'aria-expanded' ) );
        }
        $this->assertGreaterThan( 1, $found );
    }

    public function test_model_scripts_announce_and_never_alert() {
        foreach ( array( 'admin/js/qaf-model-editor.js', 'admin/js/qaf-model-screens.js' ) as $rel ) {
            $js = file_get_contents( QAF_PLUGIN_DIR . $rel );
            $this->assertStringNotContainsString( 'alert(', $js, $rel );
            $this->assertStringNotContainsString( 'window.confirm(', $js, $rel );
            $this->assertStringNotContainsString( 'draggable', $js, $rel );
        }
        $editor = file_get_contents( QAF_PLUGIN_DIR . 'admin/js/qaf-model-editor.js' );
        $this->assertStringContainsString( "'ArrowRight'", $editor, 'the tabs answer the arrow keys' );
        $this->assertStringContainsString( "'Escape'", $editor, 'Escape closes a dialog' );
        $this->assertStringContainsString( "setAttribute('aria-invalid', 'true')", $editor );
        $this->assertStringContainsString( 'summary.focus()', $editor, 'the error summary takes focus' );
    }

    public function test_every_script_string_is_localised() {
        $i18n = require QAF_PLUGIN_DIR . 'admin/model-editor-i18n.php';
        foreach ( array( 'qaf-model-editor-core.js', 'qaf-model-editor.js', 'qaf-model-screens.js', 'qaf-field-scoring.js' ) as $f ) {
            preg_match_all( "/\\bt\\('(\\w+)',\\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents( QAF_PLUGIN_DIR . 'admin/js/' . $f ), $m, PREG_SET_ORDER );
            foreach ( $m as $x ) {
                $this->assertArrayHasKey( $x[1], $i18n, $f . ': ' . $x[1] . ' is not in admin/model-editor-i18n.php' );
                $this->assertSame( stripslashes( $x[2] ), $i18n[ $x[1] ], $f . ': ' . $x[1] . ' differs' );
            }
        }
    }

    public function test_results_are_spoken_and_the_page_has_headings() {
        $src = self::source( 'admin/partials/import-export.php' );
        $this->assertSame( 1, substr_count( $src, '<h1' ) );
        $this->assertSame( 2, substr_count( $src, '<h2' ) );
        $this->assertSame( 2, substr_count( $src, 'role="status"' ), 'one status region for import, one for export' );
        $this->assertStringContainsString( '<legend>', $src );
        $js = self::source( 'admin/js/qaf-import-export.js' );
        $this->assertStringContainsString( "createElement('h3')", $js, 'the report has a heading' );
        $this->assertStringContainsString( "createElement('ul')", $js, 'the report is a list' );
        $this->assertStringNotContainsString( 'alert(', $js );
    }
}

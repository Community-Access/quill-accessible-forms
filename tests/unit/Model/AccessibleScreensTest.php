<?php
/**
 * Static checks on the admin screens added with the form model: every
 * control has a label, results are spoken, and the page has headings.
 *
 * @package BAF
 */

class BAF_Accessible_Screens_Test extends BAF_Unit_Test_Case {

    const SCREENS = array( 'admin/partials/import-export.php' );

    /** The screen's source, each PHP block replaced by a token (the same code, the same token). */
    private static function source( $rel ) {
        return preg_replace_callback( '/<\?php.*?\?>/s', function ( $m ) {
            return 'php' . substr( md5( $m[0] ), 0, 8 );
        }, file_get_contents( BAF_PLUGIN_DIR . $rel ) );
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
        require_once BAF_PLUGIN_DIR . 'admin/partials/model-screens.php';
        ob_start();
        echo '<div role="tablist">';
        baf_model_screen_tabs();
        echo '</div><main>';
        baf_model_screen_panels();
        echo '</main>';
        baf_model_screen_dialogs();
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
        $keys = array_keys( baf_model_screens() );
        $this->assertContains( 'rules', $keys );
        foreach ( $keys as $key ) {
            $tab   = $doc->getElementById( 'baf-mode-' . $key );
            $panel = $doc->getElementById( 'baf-' . $key . '-panel' );
            $this->assertSame( 'tab', $tab->getAttribute( 'role' ) );
            $this->assertSame( 'baf-' . $key . '-panel', $tab->getAttribute( 'aria-controls' ) );
            $this->assertSame( 'tabpanel', $panel->getAttribute( 'role' ) );
            $this->assertSame( 'baf-mode-' . $key, $panel->getAttribute( 'aria-labelledby' ) );
            $this->assertSame( 1, $xp->query( './/h2', $panel )->length, $key . ': one heading' );
            $this->assertSame( 1, $xp->query( './/*[@role="status"]', $panel )->length, $key . ': one status region' );
            $summary = $doc->getElementById( 'baf-' . $key . '-summary' );
            $this->assertSame( '-1', $summary->getAttribute( 'tabindex' ), $key . ': the error summary can take focus' );
            foreach ( $xp->query( './/table', $panel ) as $table ) {
                $this->assertSame( 1, $xp->query( './caption', $table )->length, $key . ': the table has a caption' );
                $cols = $xp->query( './thead//th', $table );
                $this->assertGreaterThan( 2, $cols->length );
                foreach ( $cols as $th ) {
                    $this->assertSame( 'col', $th->getAttribute( 'scope' ) );
                }
            }
            $this->assertNotNull( $doc->getElementById( 'baf-' . $key . '-add' ), $key . ': an Add button' );
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
            $this->assertSame( 1, $xp->query( './/*[@data-baf-cancel]', $d )->length, 'every dialog can be cancelled' );
            if ( $d->getAttribute( 'role' ) === 'dialog' ) {
                $this->assertSame( 1, $xp->query( './/*[contains(@class,"baf-error-summary") and @tabindex="-1"]', $d )->length );
            }
        }
        foreach ( $xp->query( '//fieldset' ) as $fs ) {
            $this->assertSame( 1, $xp->query( './legend', $fs )->length, 'a fieldset without a legend' );
        }
    }

    public function test_expression_fields_have_their_check_line_and_error_joined() {
        list( $doc, $xp ) = self::model_screens();
        $found = 0;
        foreach ( $xp->query( '//div[contains(@class,"baf-expression-field")]/input' ) as $input ) {
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
        foreach ( array( 'admin/js/baf-model-editor.js', 'admin/js/baf-model-screens.js' ) as $rel ) {
            $js = file_get_contents( BAF_PLUGIN_DIR . $rel );
            $this->assertStringNotContainsString( 'alert(', $js, $rel );
            $this->assertStringNotContainsString( 'window.confirm(', $js, $rel );
            $this->assertStringNotContainsString( 'draggable', $js, $rel );
        }
        $editor = file_get_contents( BAF_PLUGIN_DIR . 'admin/js/baf-model-editor.js' );
        $this->assertStringContainsString( "'ArrowRight'", $editor, 'the tabs answer the arrow keys' );
        $this->assertStringContainsString( "'Escape'", $editor, 'Escape closes a dialog' );
        $this->assertStringContainsString( "setAttribute('aria-invalid', 'true')", $editor );
        $this->assertStringContainsString( 'summary.focus()', $editor, 'the error summary takes focus' );
    }

    public function test_every_script_string_is_localised() {
        $i18n = require BAF_PLUGIN_DIR . 'admin/model-editor-i18n.php';
        foreach ( array( 'baf-model-editor-core.js', 'baf-model-editor.js', 'baf-model-screens.js' ) as $f ) {
            preg_match_all( "/\\bt\\('(\\w+)',\\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents( BAF_PLUGIN_DIR . 'admin/js/' . $f ), $m, PREG_SET_ORDER );
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
        $js = self::source( 'admin/js/baf-import-export.js' );
        $this->assertStringContainsString( "createElement('h3')", $js, 'the report has a heading' );
        $this->assertStringContainsString( "createElement('ul')", $js, 'the report is a list' );
        $this->assertStringNotContainsString( 'alert(', $js );
    }
}

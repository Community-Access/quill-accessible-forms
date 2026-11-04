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

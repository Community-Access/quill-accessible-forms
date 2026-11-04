<?php
/**
 * Import report.
 *
 * Every conversion into the form model (a stored BAF form, another plugin's
 * export, Google Forms) returns one of these. Anything the source has that
 * the model cannot hold is listed here, never silently dropped.
 *
 * @package BAF
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Import_Report class.
 */
class BAF_Import_Report {

    /** Something in the source was not carried over. */
    const DROPPED = 'dropped';

    /** Something was carried over in a different form. */
    const CHANGED = 'changed';

    /** The result does not pass the model's own checks. */
    const ERROR = 'error';

    /** @var string Where the form came from. */
    public $source;

    /** @var int Items (fields, sections, columns) carried over. */
    public $carried = 0;

    /** @var array Entries: array( 'level', 'where', 'message' ). */
    public $entries = array();

    /**
     * Constructor.
     *
     * @param string $source Source name, such as "Gravity Forms".
     */
    public function __construct( $source ) {
        $this->source = $source;
    }

    /**
     * Record that something was not carried over.
     *
     * @param string $where   Which part of the source.
     * @param string $message What and why.
     */
    public function dropped( $where, $message ) {
        $this->entries[] = array( 'level' => self::DROPPED, 'where' => $where, 'message' => $message );
    }

    /**
     * Record that something was carried over in a different form.
     *
     * @param string $where   Which part of the source.
     * @param string $message What changed.
     */
    public function changed( $where, $message ) {
        $this->entries[] = array( 'level' => self::CHANGED, 'where' => $where, 'message' => $message );
    }

    /**
     * Record a problem with the result.
     *
     * @param string $where   Path in the model.
     * @param string $message Problem.
     */
    public function error( $where, $message ) {
        $this->entries[] = array( 'level' => self::ERROR, 'where' => $where, 'message' => $message );
    }

    /**
     * Entries of one level.
     *
     * @param string $level Level.
     * @return array Entries.
     */
    public function of( $level ) {
        return array_values( array_filter( $this->entries, function ( $e ) use ( $level ) {
            return $e['level'] === $level;
        } ) );
    }

    /**
     * Whether everything came across unchanged.
     *
     * @return bool Complete.
     */
    public function is_complete() {
        return ! $this->entries;
    }

    /**
     * As an array, for JSON responses and the conformance files.
     *
     * @return array Report.
     */
    public function to_array() {
        return array(
            'source'  => $this->source,
            'carried' => $this->carried,
            'entries' => $this->entries,
        );
    }

    /**
     * As plain sentences, one per line, for a screen or an email.
     *
     * @return string Text.
     */
    public function to_text() {
        $lines   = array();
        $lines[] = sprintf( 'Imported %d item(s) from %s.', $this->carried, $this->source );
        if ( ! $this->entries ) {
            $lines[] = 'Everything came across.';
        }
        $words = array( self::DROPPED => 'Not carried over', self::CHANGED => 'Changed', self::ERROR => 'Problem' );
        foreach ( $this->entries as $e ) {
            $lines[] = $words[ $e['level'] ] . ': ' . $e['where'] . ': ' . $e['message'];
        }
        return implode( "\n", $lines );
    }
}

<?php
/**
 * Shared setup for the view tests: the view classes, get_option(), and a
 * stand-in entry exposing the parts of QAF_Entry the renderers read.
 *
 * @package QAF
 */

require_once QAF_PLUGIN_DIR . 'tests/mocks/payments-mocks.php'; // get_option()
require_once QAF_PLUGIN_DIR . 'includes/core/class-qaf-view.php';
require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-renderer.php';
require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-table.php';
require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-cards.php';
require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-list.php';
require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-map.php';
require_once QAF_PLUGIN_DIR . 'includes/views/class-qaf-view-calendar.php';

if ( ! class_exists( 'QAF_Test_View_Entry' ) ) {
    /**
     * The parts of QAF_Entry the renderers read.
     */
    class QAF_Test_View_Entry {
        public $id;
        public $status     = 'active';
        public $is_read    = true;
        public $is_starred = false;
        public $user_id    = 0;
        public $created_at = '2026-11-13 10:00:00';
        public $values     = array();

        public function __construct( $id, $values = array(), $props = array() ) {
            $this->id     = $id;
            $this->values = $values;
            foreach ( $props as $key => $value ) {
                $this->$key = $value;
            }
        }

        public function get_field_value( $field_id ) {
            return $this->values[ $field_id ] ?? null;
        }
    }
}

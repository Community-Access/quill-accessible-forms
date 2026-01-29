<?php
/**
 * View Renderer Base
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_View_Renderer class
 * 
 * Base class for rendering entry views.
 */
class BAF_View_Renderer {

    /**
     * View configuration
     *
     * @var array
     */
    protected $config = array();

    /**
     * Form object
     *
     * @var BAF_Form
     */
    protected $form;

    /**
     * Entries to render
     *
     * @var array
     */
    protected $entries = array();

    /**
     * Constructor
     *
     * @param array    $config View configuration.
     * @param BAF_Form $form   Form object.
     */
    public function __construct( $config = array(), $form = null ) {
        $this->config = wp_parse_args( $config, $this->get_default_config() );
        $this->form = $form;
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    protected function get_default_config() {
        return array(
            'columns'         => array(),
            'pagination'      => 25,
            'search'          => true,
            'sort'            => true,
            'filter'          => true,
            'export'          => array( 'csv' ),
            'show_entry_link' => true,
            'css_class'       => '',
        );
    }

    /**
     * Render the view
     *
     * @param array $entries Entries to render.
     * @return string HTML output.
     */
    public function render( $entries ) {
        $this->entries = $entries;

        $output = '<div class="baf-view baf-view-' . esc_attr( $this->get_type() ) . ' ' . esc_attr( $this->config['css_class'] ) . '">';
        $output .= $this->render_header();
        $output .= $this->render_content();
        $output .= $this->render_footer();
        $output .= '</div>';

        return $output;
    }

    /**
     * Get view type
     *
     * @return string View type identifier.
     */
    protected function get_type() {
        return 'base';
    }

    /**
     * Render view header
     *
     * @return string HTML output.
     */
    protected function render_header() {
        $output = '<div class="baf-view-header">';

        // Search
        if ( $this->config['search'] ) {
            $output .= $this->render_search();
        }

        // Export buttons
        if ( ! empty( $this->config['export'] ) ) {
            $output .= $this->render_export_buttons();
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render search box
     *
     * @return string HTML output.
     */
    protected function render_search() {
        $search_id = 'baf-search-' . uniqid();
        
        return sprintf(
            '<div class="baf-view-search">
                <label for="%s" class="screen-reader-text">%s</label>
                <input type="search" id="%s" class="baf-search-input" placeholder="%s" aria-label="%s">
                <button type="button" class="baf-search-button" aria-label="%s">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                </button>
            </div>',
            esc_attr( $search_id ),
            esc_html__( 'Search entries', 'bits-accessible-forms' ),
            esc_attr( $search_id ),
            esc_attr__( 'Search...', 'bits-accessible-forms' ),
            esc_attr__( 'Search entries', 'bits-accessible-forms' ),
            esc_attr__( 'Submit search', 'bits-accessible-forms' )
        );
    }

    /**
     * Render export buttons
     *
     * @return string HTML output.
     */
    protected function render_export_buttons() {
        $output = '<div class="baf-view-export">';

        foreach ( $this->config['export'] as $format ) {
            $output .= sprintf(
                '<button type="button" class="baf-export-button" data-format="%s">
                    %s <span class="screen-reader-text">%s</span>
                </button>',
                esc_attr( $format ),
                esc_html( strtoupper( $format ) ),
                esc_html( sprintf( __( 'Export as %s', 'bits-accessible-forms' ), strtoupper( $format ) ) )
            );
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render view content - override in subclasses
     *
     * @return string HTML output.
     */
    protected function render_content() {
        return '<div class="baf-view-content"></div>';
    }

    /**
     * Render view footer
     *
     * @return string HTML output.
     */
    protected function render_footer() {
        $output = '<div class="baf-view-footer">';
        
        // Entry count
        $count = count( $this->entries );
        $output .= sprintf(
            '<div class="baf-entry-count" aria-live="polite">%s</div>',
            sprintf(
                _n( '%d entry', '%d entries', $count, 'bits-accessible-forms' ),
                $count
            )
        );

        // Pagination
        if ( $this->config['pagination'] > 0 ) {
            $output .= $this->render_pagination();
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render pagination
     *
     * @return string HTML output.
     */
    protected function render_pagination() {
        $total = count( $this->entries );
        $per_page = $this->config['pagination'];
        $total_pages = ceil( $total / $per_page );

        if ( $total_pages <= 1 ) {
            return '';
        }

        $output = '<nav class="baf-pagination" aria-label="' . esc_attr__( 'Entry pagination', 'bits-accessible-forms' ) . '">';
        $output .= '<ul class="baf-pagination-list">';

        // Previous
        $output .= '<li><button type="button" class="baf-page-prev" aria-label="' . esc_attr__( 'Previous page', 'bits-accessible-forms' ) . '">&laquo;</button></li>';

        // Page numbers
        for ( $i = 1; $i <= min( $total_pages, 5 ); $i++ ) {
            $aria_current = $i === 1 ? ' aria-current="page"' : '';
            $output .= sprintf(
                '<li><button type="button" class="baf-page-number"%s data-page="%d">%d</button></li>',
                $aria_current,
                $i,
                $i
            );
        }

        // Next
        $output .= '<li><button type="button" class="baf-page-next" aria-label="' . esc_attr__( 'Next page', 'bits-accessible-forms' ) . '">&raquo;</button></li>';

        $output .= '</ul>';
        $output .= '</nav>';

        return $output;
    }

    /**
     * Get field value for display
     *
     * @param BAF_Entry $entry    Entry object.
     * @param string    $field_id Field ID.
     * @return string Formatted value.
     */
    protected function get_display_value( $entry, $field_id ) {
        $value = $entry->get_field_value( $field_id );

        if ( $value === null ) {
            return '';
        }

        if ( is_array( $value ) ) {
            return esc_html( implode( ', ', $value ) );
        }

        return esc_html( $value );
    }

    /**
     * Get columns to display
     *
     * @return array Columns configuration.
     */
    protected function get_columns() {
        if ( ! empty( $this->config['columns'] ) ) {
            return $this->config['columns'];
        }

        // Auto-generate from form fields
        $columns = array();

        if ( $this->form ) {
            foreach ( $this->form->get_fields() as $field ) {
                // Skip layout/non-data fields
                if ( in_array( $field['type'], array( 'section', 'html', 'submit', 'page' ), true ) ) {
                    continue;
                }

                $columns[ $field['id'] ] = array(
                    'label'    => $field['label'] ?? $field['id'],
                    'sortable' => true,
                );
            }
        }

        // Add meta columns
        $columns['created_at'] = array(
            'label'    => __( 'Date', 'bits-accessible-forms' ),
            'sortable' => true,
        );

        return $columns;
    }

    /**
     * Create a view renderer based on type
     *
     * @param string   $type   View type.
     * @param array    $config View configuration.
     * @param BAF_Form $form   Form object.
     * @return BAF_View_Renderer View renderer instance.
     */
    public static function factory( $type, $config = array(), $form = null ) {
        switch ( $type ) {
            case 'table':
                return new BAF_View_Table( $config, $form );
            case 'cards':
                return new BAF_View_Cards( $config, $form );
            case 'list':
                return new BAF_View_List( $config, $form );
            case 'map':
                return new BAF_View_Map( $config, $form );
            case 'calendar':
                return new BAF_View_Calendar( $config, $form );
            default:
                return new BAF_View_Table( $config, $form );
        }
    }
}

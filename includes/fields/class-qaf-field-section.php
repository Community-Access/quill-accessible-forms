<?php
/**
 * Section Break Field - Layout element with collapsible support
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_Field_Section class
 */
class QAF_Field_Section extends QAF_Field {

    /**
     * Field type
     *
     * @var string
     */
    protected $type = 'section';

    /**
     * Field category
     *
     * @var string
     */
    protected $category = 'layout';

    /**
     * Whether field stores data
     *
     * @var bool
     */
    protected $has_value = false;

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    public function get_default_config() {
        return array(
            'title'               => '',
            'description'         => '',
            'heading_level'       => 'h2',
            'show_divider'        => true,
            'collapsible'         => false,
            'collapsed_default'   => false,
            'collapse_icon'       => 'chevron',
            'animation'           => 'smooth',
            'conditional'         => array(),
            'css_class'           => '',
        );
    }

    /**
     * Get field settings for admin UI
     *
     * @return array Settings configuration.
     */
    public function get_field_settings() {
        return array(
            'general' => array(
                'title' => array(
                    'type'  => 'text',
                    'label' => __( 'Section Title', 'quill-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Section Description', 'quill-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'heading_level' => array(
                    'type'    => 'select',
                    'label'   => __( 'Heading Level', 'quill-accessible-forms' ),
                    'desc'    => __( 'Choose appropriate level for document hierarchy.', 'quill-accessible-forms' ),
                    'options' => array(
                        'h1' => 'H1 - Main Heading',
                        'h2' => 'H2 - Section Heading',
                        'h3' => 'H3 - Subsection',
                        'h4' => 'H4 - Sub-subsection',
                        'h5' => 'H5 - Minor Heading',
                        'h6' => 'H6 - Smallest Heading',
                    ),
                ),
                'show_divider' => array(
                    'type'  => 'toggle',
                    'label' => __( 'Show Divider Line', 'quill-accessible-forms' ),
                ),
            ),
            'collapsible' => array(
                'collapsible' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Make Section Collapsible', 'quill-accessible-forms' ),
                    'description' => __( 'Allow users to expand/collapse this section with Space or Enter.', 'quill-accessible-forms' ),
                ),
                'collapsed_default' => array(
                    'type'      => 'toggle',
                    'label'     => __( 'Start Collapsed', 'quill-accessible-forms' ),
                    'condition' => array( 'collapsible' => true ),
                ),
                'collapse_icon' => array(
                    'type'      => 'select',
                    'label'     => __( 'Collapse Icon Style', 'quill-accessible-forms' ),
                    'options'   => array(
                        'chevron'    => __( 'Chevron (▸ ▾)', 'quill-accessible-forms' ),
                        'plus_minus' => __( 'Plus/Minus (+ −)', 'quill-accessible-forms' ),
                        'caret'      => __( 'Caret (▶ ▼)', 'quill-accessible-forms' ),
                        'arrow'      => __( 'Arrow (→ ↓)', 'quill-accessible-forms' ),
                        'none'       => __( 'No Icon', 'quill-accessible-forms' ),
                    ),
                    'condition' => array( 'collapsible' => true ),
                ),
                'animation' => array(
                    'type'        => 'select',
                    'label'       => __( 'Animation', 'quill-accessible-forms' ),
                    'description' => __( 'Respects prefers-reduced-motion setting.', 'quill-accessible-forms' ),
                    'options'     => array(
                        'smooth'  => __( 'Smooth Slide', 'quill-accessible-forms' ),
                        'fade'    => __( 'Fade In/Out', 'quill-accessible-forms' ),
                        'instant' => __( 'Instant (No Animation)', 'quill-accessible-forms' ),
                    ),
                    'condition' => array( 'collapsible' => true ),
                ),
            ),
            'advanced' => array(
                'css_class' => array(
                    'type'  => 'text',
                    'label' => __( 'CSS Class', 'quill-accessible-forms' ),
                ),
            ),
        );
    }

    /**
     * Render the section HTML
     *
     * @return string Rendered HTML.
     */
    public function render() {
        $html_id     = $this->get_html_id();
        $title       = $this->config['title'] ?? '';
        $desc        = $this->config['description'] ?? '';
        $heading     = $this->config['heading_level'] ?? 'h2';
        $divider     = ! empty( $this->config['show_divider'] );
        $collapsible = ! empty( $this->config['collapsible'] );
        $collapsed   = ! empty( $this->config['collapsed_default'] );
        $icon_style  = $this->config['collapse_icon'] ?? 'chevron';
        $animation   = $this->config['animation'] ?? 'smooth';

        // Validate heading level
        $valid_headings = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
        if ( ! in_array( $heading, $valid_headings, true ) ) {
            $heading = 'h2';
        }

        $wrapper_class = array( 'qaf-section-break' );
        
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }
        
        if ( $divider ) {
            $wrapper_class[] = 'qaf-section-divider';
        }

        if ( $collapsible ) {
            $wrapper_class[] = 'qaf-section-collapsible';
            $wrapper_class[] = 'qaf-animation-' . esc_attr( $animation );
            if ( $collapsed ) {
                $wrapper_class[] = 'qaf-section-collapsed';
            }
        }

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_class ) ) . '"';
        $html .= ' data-field-id="' . esc_attr( $this->get_id() ) . '"';
        $html .= ' role="region"';
        
        if ( $title ) {
            $html .= ' aria-labelledby="' . esc_attr( $html_id ) . '-title"';
        }
        
        $html .= '>';

        // Section heading (with or without collapsible button)
        if ( $title ) {
            if ( $collapsible ) {
                $html .= $this->render_collapsible_header( $html_id, $title, $heading, $collapsed, $icon_style );
            } else {
                $html .= '<' . esc_attr( $heading ) . ' id="' . esc_attr( $html_id ) . '-title" class="qaf-section-title">';
                $html .= esc_html( $title );
                $html .= '</' . esc_attr( $heading ) . '>';
            }
        }

        // Section content (collapsible panel)
        if ( $collapsible ) {
            $html .= '<div id="' . esc_attr( $html_id ) . '-panel" class="qaf-section-content"';
            $html .= ' role="region"';
            $html .= ' aria-labelledby="' . esc_attr( $html_id ) . '-title"';
            if ( $collapsed ) {
                $html .= ' hidden';
            }
            $html .= '>';
        }

        // Section description
        if ( $desc ) {
            $html .= '<div class="qaf-section-description">';
            $html .= wp_kses_post( $desc );
            $html .= '</div>';
        }

        if ( $collapsible ) {
            $html .= '</div>'; // .qaf-section-content
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render collapsible header with button
     *
     * @param string $html_id    HTML ID.
     * @param string $title      Section title.
     * @param string $heading    Heading level.
     * @param bool   $collapsed  Whether collapsed by default.
     * @param string $icon_style Icon style.
     * @return string Header HTML.
     */
    private function render_collapsible_header( $html_id, $title, $heading, $collapsed, $icon_style ) {
        $expanded = $collapsed ? 'false' : 'true';
        
        $html = '<' . esc_attr( $heading ) . ' class="qaf-section-heading">';
        
        // Toggle button (Space and Enter work natively)
        $html .= '<button type="button"';
        $html .= ' id="' . esc_attr( $html_id ) . '-title"';
        $html .= ' class="qaf-section-toggle"';
        $html .= ' aria-expanded="' . esc_attr( $expanded ) . '"';
        $html .= ' aria-controls="' . esc_attr( $html_id ) . '-panel"';
        $html .= '>';

        // Icon (before title)
        if ( $icon_style !== 'none' ) {
            $html .= '<span class="qaf-section-icon qaf-icon-' . esc_attr( $icon_style ) . '" aria-hidden="true">';
            $html .= $this->get_collapse_icon( $icon_style, ! $collapsed );
            $html .= '</span>';
        }

        // Title text
        $html .= '<span class="qaf-section-title-text">' . esc_html( $title ) . '</span>';

        $html .= '</button>';
        $html .= '</' . esc_attr( $heading ) . '>';

        return $html;
    }

    /**
     * Get collapse icon HTML
     *
     * @param string $style    Icon style.
     * @param bool   $expanded Whether expanded.
     * @return string Icon HTML.
     */
    private function get_collapse_icon( $style, $expanded ) {
        $icons = array(
            'chevron' => array(
                'expanded'  => '▾',
                'collapsed' => '▸',
            ),
            'plus_minus' => array(
                'expanded'  => '−',
                'collapsed' => '+',
            ),
            'caret' => array(
                'expanded'  => '▼',
                'collapsed' => '▶',
            ),
            'arrow' => array(
                'expanded'  => '↓',
                'collapsed' => '→',
            ),
        );

        $state = $expanded ? 'expanded' : 'collapsed';
        return $icons[ $style ][ $state ] ?? $icons['chevron'][ $state ];
    }

    /**
     * Section fields don't need validation
     *
     * @return bool Always true.
     */
    public function validate() {
        return true;
    }

    /**
     * Section fields don't store values
     *
     * @param mixed $value Value.
     * @return null
     */
    public function sanitize( $value ) {
        return null;
    }
}

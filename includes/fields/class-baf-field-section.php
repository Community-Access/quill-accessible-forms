<?php
/**
 * Section Break Field - Layout element with collapsible support
 *
 * @package BAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BAF_Field_Section class
 */
class BAF_Field_Section extends BAF_Field {

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
                    'label' => __( 'Section Title', 'bits-accessible-forms' ),
                ),
                'description' => array(
                    'type'  => 'textarea',
                    'label' => __( 'Section Description', 'bits-accessible-forms' ),
                ),
            ),
            'appearance' => array(
                'heading_level' => array(
                    'type'    => 'select',
                    'label'   => __( 'Heading Level', 'bits-accessible-forms' ),
                    'desc'    => __( 'Choose appropriate level for document hierarchy.', 'bits-accessible-forms' ),
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
                    'label' => __( 'Show Divider Line', 'bits-accessible-forms' ),
                ),
            ),
            'collapsible' => array(
                'collapsible' => array(
                    'type'        => 'toggle',
                    'label'       => __( 'Make Section Collapsible', 'bits-accessible-forms' ),
                    'description' => __( 'Allow users to expand/collapse this section with Space or Enter.', 'bits-accessible-forms' ),
                ),
                'collapsed_default' => array(
                    'type'      => 'toggle',
                    'label'     => __( 'Start Collapsed', 'bits-accessible-forms' ),
                    'condition' => array( 'collapsible' => true ),
                ),
                'collapse_icon' => array(
                    'type'      => 'select',
                    'label'     => __( 'Collapse Icon Style', 'bits-accessible-forms' ),
                    'options'   => array(
                        'chevron'    => __( 'Chevron (▸ ▾)', 'bits-accessible-forms' ),
                        'plus_minus' => __( 'Plus/Minus (+ −)', 'bits-accessible-forms' ),
                        'caret'      => __( 'Caret (▶ ▼)', 'bits-accessible-forms' ),
                        'arrow'      => __( 'Arrow (→ ↓)', 'bits-accessible-forms' ),
                        'none'       => __( 'No Icon', 'bits-accessible-forms' ),
                    ),
                    'condition' => array( 'collapsible' => true ),
                ),
                'animation' => array(
                    'type'        => 'select',
                    'label'       => __( 'Animation', 'bits-accessible-forms' ),
                    'description' => __( 'Respects prefers-reduced-motion setting.', 'bits-accessible-forms' ),
                    'options'     => array(
                        'smooth'  => __( 'Smooth Slide', 'bits-accessible-forms' ),
                        'fade'    => __( 'Fade In/Out', 'bits-accessible-forms' ),
                        'instant' => __( 'Instant (No Animation)', 'bits-accessible-forms' ),
                    ),
                    'condition' => array( 'collapsible' => true ),
                ),
            ),
            'advanced' => array(
                'css_class' => array(
                    'type'  => 'text',
                    'label' => __( 'CSS Class', 'bits-accessible-forms' ),
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

        $wrapper_class = array( 'baf-section-break' );
        
        if ( ! empty( $this->config['css_class'] ) ) {
            $wrapper_class[] = $this->config['css_class'];
        }
        
        if ( $divider ) {
            $wrapper_class[] = 'baf-section-divider';
        }

        if ( $collapsible ) {
            $wrapper_class[] = 'baf-section-collapsible';
            $wrapper_class[] = 'baf-animation-' . esc_attr( $animation );
            if ( $collapsed ) {
                $wrapper_class[] = 'baf-section-collapsed';
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
                $html .= '<' . esc_attr( $heading ) . ' id="' . esc_attr( $html_id ) . '-title" class="baf-section-title">';
                $html .= esc_html( $title );
                $html .= '</' . esc_attr( $heading ) . '>';
            }
        }

        // Section content (collapsible panel)
        if ( $collapsible ) {
            $html .= '<div id="' . esc_attr( $html_id ) . '-panel" class="baf-section-content"';
            $html .= ' role="region"';
            $html .= ' aria-labelledby="' . esc_attr( $html_id ) . '-title"';
            if ( $collapsed ) {
                $html .= ' hidden';
            }
            $html .= '>';
        }

        // Section description
        if ( $desc ) {
            $html .= '<div class="baf-section-description">';
            $html .= wp_kses_post( $desc );
            $html .= '</div>';
        }

        if ( $collapsible ) {
            $html .= '</div>'; // .baf-section-content
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
        
        $html = '<' . esc_attr( $heading ) . ' class="baf-section-heading">';
        
        // Toggle button (Space and Enter work natively)
        $html .= '<button type="button"';
        $html .= ' id="' . esc_attr( $html_id ) . '-title"';
        $html .= ' class="baf-section-toggle"';
        $html .= ' aria-expanded="' . esc_attr( $expanded ) . '"';
        $html .= ' aria-controls="' . esc_attr( $html_id ) . '-panel"';
        $html .= '>';

        // Icon (before title)
        if ( $icon_style !== 'none' ) {
            $html .= '<span class="baf-section-icon baf-icon-' . esc_attr( $icon_style ) . '" aria-hidden="true">';
            $html .= $this->get_collapse_icon( $icon_style, ! $collapsed );
            $html .= '</span>';
        }

        // Title text
        $html .= '<span class="baf-section-title-text">' . esc_html( $title ) . '</span>';

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

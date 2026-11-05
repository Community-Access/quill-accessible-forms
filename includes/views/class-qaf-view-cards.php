<?php
/**
 * Cards View
 *
 * @package QAF
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * QAF_View_Cards class
 * 
 * Renders entries as accessible cards.
 */
class QAF_View_Cards extends QAF_View_Renderer {

    /**
     * Get view type
     *
     * @return string View type identifier.
     */
    protected function get_type() {
        return 'cards';
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration.
     */
    protected function get_default_config() {
        return array_merge( parent::get_default_config(), array(
            'columns'       => 3,
            'image_field'   => '',
            'title_field'   => '',
            'subtitle_field' => '',
            'body_fields'   => array(),
            'card_size'     => 'medium',
            'clickable'     => true,
        ) );
    }

    /**
     * Render view content
     *
     * @return string HTML output.
     */
    protected function render_content() {
        if ( empty( $this->entries ) ) {
            return $this->render_empty_state();
        }

        $grid_class = sprintf(
            'qaf-cards-grid qaf-cards-%s qaf-cols-%d',
            esc_attr( $this->config['card_size'] ),
            (int) $this->config['columns']
        );

        $output = sprintf(
            '<div class="%s" role="list" aria-label="%s">',
            esc_attr( $grid_class ),
            esc_attr__( 'Form entries', 'quill-accessible-forms' )
        );

        foreach ( $this->entries as $entry ) {
            $output .= $this->render_card( $entry );
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render single card
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_card( $entry ) {
        $card_class = 'qaf-card';
        if ( $entry->is_starred ) {
            $card_class .= ' qaf-starred';
        }
        if ( $this->config['clickable'] ) {
            $card_class .= ' qaf-clickable';
        }

        $output = sprintf(
            '<article class="%s" data-entry-id="%d" role="listitem">',
            esc_attr( $card_class ),
            (int) $entry->id
        );

        // Card image
        if ( $this->config['image_field'] ) {
            $output .= $this->render_card_image( $entry );
        }

        // Card body
        $output .= '<div class="qaf-card-body">';

        // Title
        $output .= $this->render_card_title( $entry );

        // Subtitle
        if ( $this->config['subtitle_field'] ) {
            $output .= $this->render_card_subtitle( $entry );
        }

        // Body content
        if ( ! empty( $this->config['body_fields'] ) ) {
            $output .= $this->render_card_content( $entry );
        }

        // Meta
        $output .= $this->render_card_meta( $entry );

        $output .= '</div>'; // .qaf-card-body

        // Card footer with actions
        $output .= $this->render_card_footer( $entry );

        $output .= '</article>';

        return $output;
    }

    /**
     * Render card image
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_card_image( $entry ) {
        $image_value = $entry->get_field_value( $this->config['image_field'] );

        if ( empty( $image_value ) ) {
            return '<div class="qaf-card-image qaf-no-image" aria-hidden="true"></div>';
        }

        // Handle various image value formats
        $image_url = '';
        if ( is_array( $image_value ) && isset( $image_value['url'] ) ) {
            $image_url = $image_value['url'];
        } elseif ( is_numeric( $image_value ) ) {
            $image_url = wp_get_attachment_url( $image_value );
        } elseif ( filter_var( $image_value, FILTER_VALIDATE_URL ) ) {
            $image_url = $image_value;
        }

        if ( $image_url ) {
            $alt_text = $this->get_display_value( $entry, $this->config['title_field'] ) ?: __( 'Entry image', 'quill-accessible-forms' );
            return sprintf(
                '<div class="qaf-card-image"><img src="%s" alt="%s" loading="lazy"></div>',
                esc_url( $image_url ),
                esc_attr( $alt_text )
            );
        }

        return '<div class="qaf-card-image qaf-no-image" aria-hidden="true"></div>';
    }

    /**
     * Render card title
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_card_title( $entry ) {
        $title = '';

        if ( $this->config['title_field'] ) {
            $title = $this->get_display_value( $entry, $this->config['title_field'] );
        }

        if ( empty( $title ) ) {
            $title = sprintf( __( 'Entry #%d', 'quill-accessible-forms' ), $entry->id );
        }

        if ( $this->config['clickable'] ) {
            return sprintf(
                '<h3 class="qaf-card-title"><a href="#" class="qaf-entry-link" data-entry-id="%d">%s</a></h3>',
                (int) $entry->id,
                esc_html( $title )
            );
        }

        return sprintf( '<h3 class="qaf-card-title">%s</h3>', esc_html( $title ) );
    }

    /**
     * Render card subtitle
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_card_subtitle( $entry ) {
        $subtitle = $this->get_display_value( $entry, $this->config['subtitle_field'] );

        if ( empty( $subtitle ) ) {
            return '';
        }

        return sprintf( '<p class="qaf-card-subtitle">%s</p>', esc_html( $subtitle ) );
    }

    /**
     * Render card content
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_card_content( $entry ) {
        $output = '<dl class="qaf-card-fields">';

        foreach ( $this->config['body_fields'] as $field_id ) {
            $value = $this->get_display_value( $entry, $field_id );
            
            if ( empty( $value ) ) {
                continue;
            }

            // Get field label
            $label = $field_id;
            if ( $this->form ) {
                $field_config = $this->form->get_field( $field_id );
                if ( $field_config && isset( $field_config['label'] ) ) {
                    $label = $field_config['label'];
                }
            }

            $output .= sprintf(
                '<div class="qaf-card-field"><dt>%s</dt><dd>%s</dd></div>',
                esc_html( $label ),
                esc_html( $value )
            );
        }

        $output .= '</dl>';

        return $output;
    }

    /**
     * Render card meta
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_card_meta( $entry ) {
        return sprintf(
            '<div class="qaf-card-meta">
                <time datetime="%s">%s</time>
            </div>',
            esc_attr( $entry->created_at ),
            esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry->created_at ) ) )
        );
    }

    /**
     * Render card footer
     *
     * @param QAF_Entry $entry Entry object.
     * @return string HTML output.
     */
    private function render_card_footer( $entry ) {
        $output = '<div class="qaf-card-footer">';

        // Star
        $star_label = $entry->is_starred ? __( 'Unstar', 'quill-accessible-forms' ) : __( 'Star', 'quill-accessible-forms' );
        $output .= sprintf(
            '<button type="button" class="qaf-action-star" data-entry-id="%d" aria-pressed="%s" aria-label="%s">
                <span class="dashicons %s" aria-hidden="true"></span>
            </button>',
            (int) $entry->id,
            $entry->is_starred ? 'true' : 'false',
            esc_attr( $star_label ),
            $entry->is_starred ? 'dashicons-star-filled' : 'dashicons-star-empty'
        );

        // View
        $output .= sprintf(
            '<button type="button" class="qaf-action-view" data-entry-id="%d" aria-label="%s">
                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
            </button>',
            (int) $entry->id,
            esc_attr( sprintf( __( 'View entry %d', 'quill-accessible-forms' ), $entry->id ) )
        );

        // Delete
        $output .= sprintf(
            '<button type="button" class="qaf-action-delete" data-entry-id="%d" aria-label="%s">
                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
            </button>',
            (int) $entry->id,
            esc_attr( sprintf( __( 'Delete entry %d', 'quill-accessible-forms' ), $entry->id ) )
        );

        $output .= '</div>';

        return $output;
    }

    /**
     * Render empty state
     *
     * @return string HTML output.
     */
    private function render_empty_state() {
        return sprintf(
            '<div class="qaf-empty-state" role="status">
                <span class="dashicons dashicons-grid-view" aria-hidden="true"></span>
                <p>%s</p>
            </div>',
            esc_html__( 'No entries found.', 'quill-accessible-forms' )
        );
    }
}
